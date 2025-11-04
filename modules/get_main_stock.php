<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once "../db_connection.php";

if (!isset($_GET['product_id'])) {
    echo json_encode(['quantity' => 0]);
    exit;
}

$productId = (int) $_GET['product_id'];

$sql = "SELECT COALESCE(quantity, 0) AS qty
        FROM maininventory
        WHERE product_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $productId);
$stmt->execute();
$stmt->bind_result($qty);
$stmt->fetch();

echo json_encode(['quantity' => (int)$qty]);

$stmt->close();
$conn->close();
