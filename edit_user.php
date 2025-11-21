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
if (empty($phone_number)) {
    $response['errors'][] = "Phone number is required.";
} else {
    // Phone number validation and formatting
    // Remove any spaces, dashes, or other characters
    $phone = preg_replace('/[^0-9+]/', '', $phone_number);
    
    // Check length and format
    $phoneLength = strlen($phone);
    
    if ($phoneLength === 10) {
        // 10-digit number (e.g., 9123456789)
        if (substr($phone, 0, 1) === '9') {
            // Format as +639123456789
            $formattedPhone = '+63' . $phone;
        } else {
            $response['errors'][] = "Invalid phone number format. Example: 9123456789";
        }
    } elseif ($phoneLength === 11) {
        // 11-digit number (e.g., 09123456789)
        if (substr($phone, 0, 2) === '09') {
            // Replace 09 with +63
            $formattedPhone = '+63' . substr($phone, 1);
        } else {
            $response['errors'][] = "Invalid phone number format. Example: 09123456789";
        }
    } elseif ($phoneLength === 13 && substr($phone, 0, 3) === '+63') {
        // Already in +63 format (e.g., +639123456789)
        $formattedPhone = $phone;
        // Remove the +63 to check if the remaining starts with 9
        $remaining = substr($formattedPhone, 3);
        if (substr($remaining, 0, 1) !== '9' || strlen($remaining) !== 10) {
            $response['errors'][] = "Invalid phone number format. Example: +639123456789";
        }
    } else {
        $response['errors'][] = "Invalid phone number format. Examples: 9123456789 or 09123456789";
    }
    
    // If no errors, set the formatted phone number
    if (empty($response['errors'])) {
        $phone_number = $formattedPhone;
    }
}

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
    $stmt->bind_param("sssssi", $username, $password_hash, $role, $branch_id, $phone_number, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE Users 
        SET username=?, role=?, branch_id=?, phone_number=? 
        WHERE user_id=?");
    $stmt->bind_param("ssssi", $username, $role, $branch_id, $phone_number, $user_id);
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