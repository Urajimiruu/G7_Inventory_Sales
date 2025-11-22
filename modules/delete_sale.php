<?php
// modules/delete_sale.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once __DIR__ . "/../db_connection.php";

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$saleId = (int)($_POST['sale_id'] ?? 0);
if ($saleId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid sale_id']);
    exit;
}

$conn->begin_transaction();
try {
    // lock sale
    $q = "SELECT product_id, branch_id, quantity, status FROM sales WHERE sale_id = ? FOR UPDATE";
    $st = $conn->prepare($q);
    $st->bind_param("i", $saleId);
    $st->execute();
    $res = $st->get_result();
    if (!$res || $res->num_rows === 0) throw new Exception('Sale not found');
    $sale = $res->fetch_assoc();
    $st->close();

    if ($sale['status'] !== 'active') throw new Exception('Only active sales can be deleted');

    $prod = (int)$sale['product_id'];
    $branch = (int)$sale['branch_id'];
    $qty = (int)$sale['quantity'];

    // return to inventory
    $u = "UPDATE branchinventory SET quantity = quantity + ? WHERE branch_id = ? AND product_id = ?";
    $st2 = $conn->prepare($u);
    $st2->bind_param("iii", $qty, $branch, $prod);
    $st2->execute();
    if ($st2->affected_rows === 0) {
        // try insert
        $ins = $conn->prepare("INSERT INTO branchinventory (branch_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
        $ins->bind_param("iii", $branch, $prod, $qty);
        $ins->execute();
        $ins->close();
    }
    $st2->close();

    // delete sale
    $del = $conn->prepare("DELETE FROM sales WHERE sale_id = ?");
    $del->bind_param("i", $saleId);
    $del->execute();
    $del->close();

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
