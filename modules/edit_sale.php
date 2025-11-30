<?php
// modules/edit_sale.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once __DIR__ . "/../db_connection.php";

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// sanitize inputs
$saleId    = (int)($_POST['sale_id'] ?? 0);
$saleDate  = $_POST['sale_date'] ?? '';
$newProd   = (int)($_POST['product_id'] ?? 0);
$newQty    = (int)($_POST['quantity'] ?? 0);

// NEW: include customer_type
$newCustomerType = $_POST['customer_type'] ?? '';
$allowedTypes = ['Regular', 'Senior', 'PWD'];

// Fetch selling price of new product
$priceStmt = $conn->prepare("SELECT selling_price FROM products WHERE product_id = ?");
$priceStmt->bind_param("i", $newProd);
$priceStmt->execute();
$priceStmt->bind_result($sellingPriceRaw);
$priceStmt->fetch();
$priceStmt->close();

if (!$sellingPriceRaw) {
    echo json_encode(['success' => false, 'message' => 'Product price not found']);
    exit;
}

// Compute final unit_price based on customer type
$finalUnitPrice = (float)$sellingPriceRaw;
if ($newCustomerType === 'Senior' || $newCustomerType === 'PWD') {
    $finalUnitPrice = $finalUnitPrice * 0.8; // 20% discount
}


if (!in_array($newCustomerType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer type']);
    exit;
}

if ($saleId <= 0 || !$saleDate || $newProd <= 0 || $newQty <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$conn->begin_transaction();

try {
    // Fetch original sale including branch
    $q = "SELECT product_id, branch_id, quantity, status 
          FROM sales 
          WHERE sale_id = ? FOR UPDATE";
    $st = $conn->prepare($q);
    $st->bind_param("i", $saleId);
    $st->execute();
    $res = $st->get_result();

    if (!$res || $res->num_rows === 0) throw new Exception('Sale not found');
    $orig = $res->fetch_assoc();
    $st->close();

    if ($orig['status'] !== 'active') {
        throw new Exception('Only active sales can be edited');
    }

    // Always use original branch
    $branchId = (int)$orig['branch_id'];

    $oldProd = (int)$orig['product_id'];
    $oldQty  = (int)$orig['quantity'];

    // --- INVENTORY ADJUSTMENT (product/quantity only) ---

    if ($oldProd === $newProd) {
        // Same product → only apply quantity delta
        $delta = $newQty - $oldQty;

        if ($delta > 0) {
            // Need to deduct more stock
            $u = "UPDATE branchinventory 
                  SET quantity = quantity - ? 
                  WHERE branch_id = ? AND product_id = ? AND quantity >= ?";
            $ust = $conn->prepare($u);
            $ust->bind_param("iiii", $delta, $branchId, $newProd, $delta);
            $ust->execute();
            if ($ust->affected_rows === 0) {
                throw new Exception('Insufficient stock for quantity increase.');
            }
            $ust->close();
        } elseif ($delta < 0) {
            // Increase stock back
            $inc = -$delta;
            $u = "UPDATE branchinventory SET quantity = quantity + ? 
                  WHERE branch_id = ? AND product_id = ?";
            $ust = $conn->prepare($u);
            $ust->bind_param("iii", $inc, $branchId, $oldProd);
            $ust->execute();

            if ($ust->affected_rows === 0) {
                // If missing, insert
                $ins = $conn->prepare("INSERT INTO branchinventory 
                      (branch_id, product_id, quantity)
                      VALUES (?, ?, ?)
                      ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
                $ins->bind_param("iii", $branchId, $oldProd, $inc);
                $ins->execute();
                $ins->close();
            }
            $ust->close();
        }
    } 
    else {
        // Product changed

        // 1. Return old qty to inventory
        $u1 = "UPDATE branchinventory 
               SET quantity = quantity + ? 
               WHERE branch_id = ? AND product_id = ?";
        $st1 = $conn->prepare($u1);
        $st1->bind_param("iii", $oldQty, $branchId, $oldProd);
        $st1->execute();
        $st1->close();

        // 2. Deduct new qty from new product
        $u2 = "UPDATE branchinventory 
               SET quantity = quantity - ? 
               WHERE branch_id = ? AND product_id = ? AND quantity >= ?";
        $st2 = $conn->prepare($u2);
        $st2->bind_param("iiii", $newQty, $branchId, $newProd, $newQty);
        $st2->execute();

        if ($st2->affected_rows === 0) {
            throw new Exception("Insufficient stock for new product.");
        }
        $st2->close();
    }

    // --- UPDATE SALE ROW ---

    $updateSql = "
        UPDATE sales 
        SET 
            product_id = ?, 
            sale_date = ?, 
            quantity = ?, 
            customer_type = ?,
            unit_price = ?
        WHERE sale_id = ?
    ";

    $ust = $conn->prepare($updateSql);
    $ust->bind_param("isissd", $newProd, $saleDate, $newQty, $newCustomerType, $finalUnitPrice, $saleId);

    if (!$ust->execute()) {
        throw new Exception('Failed to update sale: ' . $ust->error);
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
