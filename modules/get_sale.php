<?php
// modules/get_sale.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once __DIR__ . "/../db_connection.php";

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$saleId = (int)($_GET['sale_id'] ?? 0);
if ($saleId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid sale id']);
    exit;
}

$sql = "SELECT sale_id, sale_date, product_id, branch_id, quantity, customer_type, status FROM sales WHERE sale_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $saleId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Sale not found']);
    exit;
}
$sale = $res->fetch_assoc();
$stmt->close();
echo json_encode(['success' => true, 'sale' => $sale]);
$conn->close();
