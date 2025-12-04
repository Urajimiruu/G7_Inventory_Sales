<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');

require_once "../db_connection.php";
require_once "../includes/email_config.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

// Check required parameters
if (!isset($_POST['sale_date'], $_POST['branch_id'], $_POST['customer_type'], $_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
    exit;
}

$saleDate     = $_POST['sale_date'];
$branchId     = (int)$_POST['branch_id'];
$customerType = $_POST['customer_type'];
$productIds   = $_POST['product_id'];
$quantities   = $_POST['quantity'];
$unitPrices   = $_POST['unit_price']; // Original unit prices from products

if ($branchId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid branch.']);
    exit;
}

$conn->begin_transaction();

try {
    $insertSql = "
        INSERT INTO sales (branch_id, product_id, sale_date, quantity, unit_price, customer_type)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $insertStmt = $conn->prepare($insertSql);

    $invSql = "
        UPDATE branchinventory
        SET quantity = quantity - ?
        WHERE branch_id = ? AND product_id = ? AND quantity >= ?
    ";
    $invStmt = $conn->prepare($invSql);

    // Keep track of what needs notifications
    $notifications = [];

    for ($i = 0; $i < count($productIds); $i++) {
        $pid = (int)$productIds[$i];
        $qty = (int)$quantities[$i];
        // Fetch product name (no logic change)
        $pname = "";
        $pnStmt = $conn->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $pnStmt->bind_param("i", $pid);
        $pnStmt->execute();
        $pnStmt->bind_result($pname);
        $pnStmt->fetch();
        $pnStmt->close();

        if (!$pname) $pname = "Product #$pid";

        $originalUnitPrice = (float)$unitPrices[$i];

        if ($pid <= 0 || $qty <= 0) continue;

        // Apply discount based on customer type
        $finalUnitPrice = $originalUnitPrice;
        if ($customerType === 'Senior' || $customerType === 'PWD') {
            $finalUnitPrice = $originalUnitPrice * 0.8; // 20% discount
        }

        // Get old quantity (lock row)
        $qtySql = "SELECT quantity FROM branchinventory WHERE branch_id = ? AND product_id = ? FOR UPDATE";
        $qtyStmt = $conn->prepare($qtySql);
        $qtyStmt->bind_param("ii", $branchId, $pid);
        $qtyStmt->execute();
        $qtyStmt->bind_result($oldQty);
        $qtyStmt->fetch();
        $qtyStmt->close();

        $oldQty = (int)$oldQty;

        // Update inventory
        $invStmt->bind_param("iiii", $qty, $branchId, $pid, $qty);
        $invStmt->execute();
        if ($invStmt->affected_rows === 0) {
            throw new Exception("Not enough stock for product: $pname, Available stocks = $oldQty.");
        }

        // Insert sale with final unit price (discounted if applicable)
        $insertStmt->bind_param("iisids", $branchId, $pid, $saleDate, $qty, $finalUnitPrice, $customerType);
        $insertStmt->execute();

        // Record for notification (don't send email yet)
        $notifications[] = [
            'branchId' => $branchId,
            'productId' => $pid,
            'oldQty' => $oldQty
        ];
    }

    $conn->commit();

    // safely send notifications after successful commit
    foreach ($notifications as $n) {
        try {
            checkLowStockAndNotify($conn, $n['branchId'], $n['productId'], $n['oldQty'], 20);
        } catch (Exception $mailEx) {
            error_log("Email send failed for product {$n['productId']}: " . $mailEx->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => 'Sale recorded successfully!']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Close statements if they exist
if (isset($insertStmt)) $insertStmt->close();
if (isset($invStmt)) $invStmt->close();
$conn->close();
?>