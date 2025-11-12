<?php
require_once "db_connection.php";

$id = $_POST['product_id'];

$stmt = $conn->prepare("DELETE FROM Products WHERE product_id = ?");
$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
    if (str_contains($stmt->error, "foreign key")) {
        echo json_encode([
            "success" => false,
            "message" => "This product cannot be deleted because it is used in inventory."
        ]);
    } else {
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }
    exit;
}
