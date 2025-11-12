<?php
require_once "db_connection.php";

$id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM Products WHERE product_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(["success" => false, "message" => "Product not found"]);
    exit;
}

echo json_encode(["success" => true, "product" => $result->fetch_assoc()]);
