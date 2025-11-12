<?php
require_once "db_connection.php";

$branch_id = intval($_POST['branch_id'] ?? 0);

if ($branch_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid branch ID"]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM Branches WHERE branch_id = ?");
$stmt->bind_param("i", $branch_id);
if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    // Handle foreign key constraint
    if ($conn->errno == 1451) {
        echo json_encode(["success" => false, "message" => "Cannot delete branch. It is Referenced in other modules."]);
    } else {
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }
}
$stmt->close();
