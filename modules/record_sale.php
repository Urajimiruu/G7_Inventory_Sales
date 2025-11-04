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

if (!isset($_POST['sale_date'], $_POST['branch_id'], $_POST['product_id'], $_POST['quantity'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
    exit;
}

$saleDate   = $_POST['sale_date'];
$branchId   = (int)$_POST['branch_id'];
$productIds = $_POST['product_id'];
$quantities = $_POST['quantity'];

if ($branchId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid branch.']);
    exit;
}

$conn->begin_transaction();

try {
    $insertSql = "
        INSERT INTO sales (branch_id, product_id, sale_date, quantity)
        VALUES (?, ?, ?, ?)
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

        if ($pid <= 0 || $qty <= 0) continue;

        // 1️⃣ Get old quantity (lock row)
        $qtySql = "SELECT quantity FROM branchinventory WHERE branch_id = ? AND product_id = ? FOR UPDATE";
        $qtyStmt = $conn->prepare($qtySql);
        $qtyStmt->bind_param("ii", $branchId, $pid);
        $qtyStmt->execute();
        $qtyStmt->bind_result($oldQty);
        $qtyStmt->fetch();
        $qtyStmt->close();

        $oldQty = (int)$oldQty;

        // 2️⃣ Update inventory
        $invStmt->bind_param("iiii", $qty, $branchId, $pid, $qty);
        $invStmt->execute();
        if ($invStmt->affected_rows === 0) {
            throw new Exception("Not enough stock for product ID $pid.");
        }

        // 3️⃣ Insert sale
        $insertStmt->bind_param("iisi", $branchId, $pid, $saleDate, $qty);
        $insertStmt->execute();

        // 4️⃣ Record for notification (don’t send email yet)
        $notifications[] = [
            'branchId' => $branchId,
            'productId' => $pid,
            'oldQty' => $oldQty
        ];
    }

    $conn->commit();

    // 📨 Now safely send notifications after successful commit
    foreach ($notifications as $n) {
        try {
            checkLowStockAndNotify($conn, $n['branchId'], $n['productId'], $n['oldQty'], 20);
        } catch (Exception $mailEx) {
            error_log("Email send failed for product {$n['productId']}: " . $mailEx->getMessage());
            // Don't break flow
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
