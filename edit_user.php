<?php
require_once "db_connection.php";

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Get POST data
$user_id      = intval($_POST['user_id']);
$username     = trim($_POST['username']);
$password     = trim($_POST['password']);
$role         = $_POST['role'];
$branch_name  = $_POST['branch'] ?? null;
$phone_number = trim($_POST['phone'] ?? '');

$response = ['success' => false, 'errors' => []];

// Validation
if (empty($username)) $response['errors'][] = "Username is required.";
if (empty($phone_number)) $response['errors'][] = "Phone number is required.";
elseif (!preg_match('/^\+63\s\d{3}\s\d{3}\s\d{4}$/', $phone_number)) //dko sure kung alin ang working sa otp
    $response['errors'][] = "Invalid phone number format. Use +63 912 345 6789.";

// Determine branch_id if role is shop
$branch_id = null;
if ($role === 'shop' && !empty($branch_name)) {
    $stmt = $conn->prepare("SELECT branch_id FROM Branches WHERE branch_name=?");
    $stmt->bind_param("s", $branch_name);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $branch_id = $res['branch_id'] ?? null;
    $stmt->close();
}

// Stop if validation fails
if (!empty($response['errors'])) {
    echo json_encode($response);
    exit;
}

// Update user
if (!empty($password)) {
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE Users 
        SET username=?, password_hash=?, role=?, branch_id=?, phone_number=? 
        WHERE user_id=?");
    $stmt->bind_param("sssdsi", $username, $password_hash, $role, $branch_id, $phone_number, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE Users 
        SET username=?, role=?, branch_id=?, phone_number=? 
        WHERE user_id=?");
    $stmt->bind_param("ssdsi", $username, $role, $branch_id, $phone_number, $user_id);
}

if ($stmt->execute()) {
    $response['success'] = true;
} else {
    $response['errors'][] = "Database error: " . $stmt->error;
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
