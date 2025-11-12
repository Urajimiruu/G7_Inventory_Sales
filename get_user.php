<?php
require_once "db_connection.php";

$user_id = intval($_GET['id']);
$response = ['success' => false, 'user' => null, 'message' => ''];

$stmt = $conn->prepare("SELECT u.user_id, u.username, u.role, u.phone_number, b.branch_name 
                        FROM Users u 
                        LEFT JOIN Branches b ON u.branch_id = b.branch_id
                        WHERE u.user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $response['message'] = "User not found.";
} else {
    // Fetch all branches
    $branches = [];
    $res = $conn->query("SELECT branch_name FROM Branches ORDER BY branch_name ASC");
    while ($row = $res->fetch_assoc()) {
        $branches[] = $row;
    }

    $user['branches'] = $branches;
    $response['success'] = true;
    $response['user'] = $user;
}

$conn->close();
echo json_encode($response);
