<?php
require_once "db_connection.php";

$branch_id = intval($_POST['branch_id'] ?? 0);
$branch_name = trim($_POST['branch_name'] ?? '');
$location = trim($_POST['location'] ?? '');
$errors = [];

if ($branch_id <= 0) $errors[] = "Invalid branch ID.";
if ($branch_name === '') $errors[] = "Branch name is required.";
if ($location === '') $errors[] = "Location is required.";

if (empty($errors)) {
    $stmt = $conn->prepare("UPDATE Branches SET branch_name=?, location=? WHERE branch_id=?");
    $stmt->bind_param("ssi", $branch_name, $location, $branch_id);
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
