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
$newBranch = (int)($_POST['branch_id'] ?? 0);
$newProd   = (int)($_POST['product_id'] ?? 0);
$newQty    = (int)($_POST['quantity'] ?? 0);

if ($saleId <= 0 || !$saleDate || $newBranch <= 0 || $newProd <= 0 || $newQty <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// enforce shop users: cannot edit to other branch
$role = strtolower($_SESSION['role'] ?? '');
$userBranch = (int)($_SESSION['branch_id'] ?? 0);
if ($role === 'shop' && $userBranch > 0 && $newBranch !== $userBranch) {
    echo json_encode(['success' => false, 'message' => 'Shop users cannot change branch']);
    exit;
}

$conn->begin_transaction();

try {
    // lock and fetch original sale
    $q = "SELECT product_id, branch_id, quantity, status FROM sales WHERE sale_id = ? FOR UPDATE";
    $st = $conn->prepare($q);
    $st->bind_param("i", $saleId);
    $st->execute();
    $res = $st->get_result();
    if (!$res || $res->num_rows === 0) throw new Exception('Sale not found');
    $orig = $res->fetch_assoc();
    $st->close();

    if ($orig['status'] !== 'active') throw new Exception('Only active sales can be edited');

    $oldProd = (int)$orig['product_id'];
    $oldBranch = (int)$orig['branch_id'];
    $oldQty = (int)$orig['quantity'];

    // Case A: same product & same branch -> apply delta logic
    if ($oldProd === $newProd && $oldBranch === $newBranch) {
        $delta = $newQty - $oldQty;
        if ($delta > 0) {
            // need to deduct delta from branchinventory if available
            $u = "UPDATE branchinventory SET quantity = quantity - ? WHERE branch_id = ? AND product_id = ? AND quantity >= ?";
            $ust = $conn->prepare($u);
            $ust->bind_param("iiii", $delta, $newBranch, $newProd, $delta);
            $ust->execute();
            if ($ust->affected_rows === 0) {
                throw new Exception('Insufficient stock for requested increase.');
            }
            $ust->close();
        } elseif ($delta < 0) {
            // increase stock by -delta
            $inc = -$delta;
            $u = "UPDATE branchinventory SET quantity = quantity + ? WHERE branch_id = ? AND product_id = ?";
            $ust = $conn->prepare($u);
            $ust->bind_param("iii", $inc, $newBranch, $newProd);
            $ust->execute();
            // if no row existed, try insert
            if ($ust->affected_rows === 0) {
                $ins = $conn->prepare("INSERT INTO branchinventory (branch_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
                $ins->bind_param("iii", $newBranch, $newProd, $inc);
                $ins->execute();
                $ins->close();
            }
            $ust->close();
        }
        // else delta == 0 -> only date change (no inventory)
    } else {
        // different product or branch:
        // 1) return old quantity to old branchinventory (increase)
        $u1 = "UPDATE branchinventory SET quantity = quantity + ? WHERE branch_id = ? AND product_id = ?";
        $st1 = $conn->prepare($u1);
        $st1->bind_param("iii", $oldQty, $oldBranch, $oldProd);
        $st1->execute();
        if ($st1->affected_rows === 0) {
            // row might not exist, insert
            $ins1 = $conn->prepare("INSERT INTO branchinventory (branch_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
            $ins1->bind_param("iii", $oldBranch, $oldProd, $oldQty);
            $ins1->execute();
            $ins1->close();
        }
        $st1->close();

        // 2) attempt to deduct newQty from new branch/product ensuring enough stock
        $u2 = "UPDATE branchinventory SET quantity = quantity - ? WHERE branch_id = ? AND product_id = ? AND quantity >= ?";
        $st2 = $conn->prepare($u2);
        $st2->bind_param("iiii", $newQty, $newBranch, $newProd, $newQty);
        $st2->execute();
        if ($st2->affected_rows === 0) {
            // revert the return to old branchinventory
            // try to subtract what we just added (best effort)
            $revert = $conn->prepare("UPDATE branchinventory SET quantity = quantity - ? WHERE branch_id = ? AND product_id = ?");
            $revert->bind_param("iii", $oldQty, $oldBranch, $oldProd);
            $revert->execute();
            $revert->close();

            throw new Exception('Insufficient stock at destination branch (or product missing).');
        }
        $st2->close();
    }

    // finally update sales row
    $updateSql = "UPDATE sales SET branch_id = ?, product_id = ?, sale_date = ?, quantity = ? WHERE sale_id = ?";
    $ust = $conn->prepare($updateSql);
    $ust->bind_param("iisii", $newBranch, $newProd, $saleDate, $newQty, $saleId);
    if (!$ust->execute()) {
        throw new Exception('Failed to update sale: ' . $ust->error);
    }
    $ust->close();

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
