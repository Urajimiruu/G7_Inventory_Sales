<?php
require_once "db_connection.php";

// Get user ID from POST
$userId = $_POST['user_id'] ?? '';

if (!empty($userId)) {
    $stmt = $conn->prepare("DELETE FROM Users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
}

$conn->close();
?>
