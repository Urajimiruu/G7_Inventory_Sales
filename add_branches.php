<?php
require_once "db_connection.php";

$branch_name = trim($_POST['branch_name'] ?? '');
$location = trim($_POST['location'] ?? '');
$errors = [];

if ($branch_name === '') $errors[] = "Branch name is required.";
if ($location === '') $errors[] = "Location is required.";

if (empty($errors)) {
    $check = $conn->prepare("SELECT branch_id FROM Branches WHERE branch_name = ?");
    $check->bind_param("s", $branch_name);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) $errors[] = "Branch name already exists.";
    $check->close();
}

if (empty($errors)) {
    $stmt = $conn->prepare("INSERT INTO Branches (branch_name, location) VALUES (?, ?)");
    $stmt->bind_param("ss", $branch_name, $location);
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        $errors[] = "Database error: " . $stmt->error;
        echo json_encode(["success" => false, "errors" => $errors]);
    }
    $stmt->close();
} else {
    echo json_encode(["success" => false, "errors" => $errors]);
}
