<?php
// modules/check_stock.php
header('Content-Type: application/json');
require_once __DIR__ . "/../db_connection.php";

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(['success'=>false,'message'=>'Invalid payload']);
    exit;
}

$branchId = (int)($data['branch_id'] ?? 0);
$products = $data['products'] ?? [];

if ($branchId <= 0 || !is_array($products) || empty($products)) {
    echo json_encode(['success'=>false,'message'=>'Missing data']);
    exit;
}

// build placeholders
$ids = array_map(function($p){ return (int)$p['product_id']; }, $products);
$ids = array_values(array_unique($ids));

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT product_id, quantity FROM branchinventory WHERE branch_id = ? AND product_id IN ($placeholders)";
$stmt = $conn->prepare($sql);

$types = str_repeat('i', count($ids) + 1);
$params = array_merge([$branchId], $ids);

// bind dynamically
$refArr = [];
foreach ($params as $k => $v) $refArr[$k] = &$params[$k];
call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $refArr));

$stmt->execute();
$res = $stmt->get_result();

$stocks = [];
while ($row = $res->fetch_assoc()) {
    $stocks[(int)$row['product_id']] = (int)$row['quantity'];
}

$stmt->close();

echo json_encode(['success'=>true,'stocks'=>$stocks]);
