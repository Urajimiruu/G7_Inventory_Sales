<?php
// modules/import_sales.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . "/../db_connection.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

if (!isset($_POST['sale_date'], $_POST['branch_id'], $_POST['product_id'])) {
    echo json_encode(['success'=>false,'message'=>'Missing parameters.']);
    exit;
}

$saleDate = $_POST['sale_date'];
$branchId = (int)$_POST['branch_id'];
$productIds = $_POST['product_id'];
$quantities = $_POST['quantity'];
$unitPrices = $_POST['unit_price'];
$customerTypes = $_POST['customer_type'];

if ($branchId <= 0) {
    echo json_encode(['success'=>false,'message'=>'Invalid branch.']);
    exit;
}

$conn->begin_transaction();
try {
    $insertSql = "INSERT INTO sales (branch_id, product_id, sale_date, quantity, unit_price, customer_type) VALUES (?, ?, ?, ?, ?, ?)";
    $insertStmt = $conn->prepare($insertSql);

    $invSql = "UPDATE branchinventory SET quantity = quantity - ? WHERE branch_id = ? AND product_id = ? AND quantity >= ?";
    $invStmt = $conn->prepare($invSql);

    for ($i = 0; $i < count($productIds); $i++) {
        $pid = (int)$productIds[$i];
        $qty = (int)$quantities[$i];
        $unit = (float)$unitPrices[$i];
        $ctype = $customerTypes[$i] ?? 'Regular';

        if ($pid <= 0 || $qty <= 0) continue;

        // lock row and ensure enough stock
        $qtySql = "SELECT quantity FROM branchinventory WHERE branch_id = ? AND product_id = ? FOR UPDATE";
        $qtyStmt = $conn->prepare($qtySql);
        $qtyStmt->bind_param("ii", $branchId, $pid);
        $qtyStmt->execute();
        $qtyStmt->bind_result($oldQty);
        $qtyStmt->fetch();
        $qtyStmt->close();

        $invStmt->bind_param("iiii", $qty, $branchId, $pid, $qty);
        $invStmt->execute();
        if ($invStmt->affected_rows === 0) {
            throw new Exception("Not enough stock for product ID $pid.");
        }

        $insertStmt->bind_param("iisids", $branchId, $pid, $saleDate, $qty, $unit, $ctype);
        $insertStmt->execute();
    }

    $conn->commit();
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
