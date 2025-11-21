<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once "../db_connection.php";

if (!isset($_POST['product_id'], $_POST['branch_id'], $_POST['quantity'], $_POST['date'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
    exit;
}

$productId = (int) $_POST['product_id'];
$branchId  = (int) $_POST['branch_id'];
$qty       = (int) $_POST['quantity'];
$date = $_POST['date']; // user selects only the date

// add current server time
$currentTime = date("H:i:s");
$date = $date . " " . $currentTime;


if ($productId <= 0 || $branchId <= 0 || $qty <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data values.']);
    exit;
}

$conn->begin_transaction();

try {
    // 1) deduct from maininventory if enough stock
    $sqlMain = "UPDATE maininventory 
                SET quantity = quantity - ? 
                WHERE product_id = ? AND quantity >= ?";
    $stmtMain = $conn->prepare($sqlMain);
    $stmtMain->bind_param("iii", $qty, $productId, $qty);
    $stmtMain->execute();

    if ($stmtMain->affected_rows === 0) {
        throw new Exception('Insufficient stock in main inventory or invalid product ID.');
    }

    // 2) add to branchinventory
    $sqlBranch = "
        UPDATE branchinventory
        SET quantity = quantity + ?
        WHERE branch_id = ? AND product_id = ?
    ";
    $stmtBranch = $conn->prepare($sqlBranch);
    $stmtBranch->bind_param("iii", $qty, $branchId, $productId);
    $stmtBranch->execute();

    if ($stmtBranch->affected_rows === 0) {
        throw new Exception('Branch inventory row not found for this branch/product.');
    }

    // 3) log transfer
    $sqlTransfer = "
        INSERT INTO stocktransfers (product_id, branch_id, quantity, transfer_date)
        VALUES (?, ?, ?, ?)
    ";
    $stmtTransfer = $conn->prepare($sqlTransfer);
    $stmtTransfer->bind_param("iiis", $productId, $branchId, $qty, $date);
    $stmtTransfer->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
