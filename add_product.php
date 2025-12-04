<?php
require_once "db_connection.php";

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


if (empty($errors)) {
    $checkStmt = $conn->prepare("SELECT product_id FROM Products WHERE product_name = ?");
    $checkStmt->bind_param("s", $name);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows > 0) {
        $errors[] = "Product name already exists. Please choose a different name.";
    }
    
    $checkStmt->close();
}


if (!empty($errors)) {
    echo json_encode(["success" => false, "errors" => $errors]);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO Products (product_name, description, unit, cost_price, selling_price)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("sssdd", $name, $desc, $unit, $cost, $sell);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "errors" => ["Database error: " . $stmt->error]]);
}
