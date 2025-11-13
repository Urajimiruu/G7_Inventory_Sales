<?php
require_once "db_connection.php";

$id   = $_POST['product_id'];
$name = trim($_POST['product_name']);
$desc = trim($_POST['description']);
$unit = trim($_POST['unit']);
$cost = $_POST['cost_price'];
$sell = $_POST['selling_price'];

$errors = [];

if ($name == "") $errors[] = "Product name required.";
if ($desc == "") $errors[] = "Description required.";
if ($unit == "") $errors[] = "Unit required.";
if (!is_numeric($cost)) $errors[] = "Invalid cost price.";
if (!is_numeric($sell)) $errors[] = "Invalid selling price.";

if (!empty($errors)) {
    echo json_encode(["success" => false, "errors" => $errors]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE Products
    SET product_name=?, description=?, unit=?, cost_price=?, selling_price=?
    WHERE product_id=?
");
$stmt->bind_param("sssddi", $name, $desc, $unit, $cost, $sell, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "errors" => ["Database error: " . $stmt->error]]);
}
