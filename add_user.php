<?php
require_once "db_connection.php";

$errors = [];
$response = ["success" => false];

// POST only
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $role = strtolower(trim($_POST["role"]));
    $phone = trim($_POST["phone"]);
    $branch_id = isset($_POST["branch"]) && $role === "shop" ? intval($_POST["branch"]) : null;

    // --- VALIDATION ---
    if ($username === "") $errors[] = "Username is required.";
    if ($password === "") $errors[] = "Password is required.";
    if ($role === "") $errors[] = "Role is required.";

    // Phone number validation and formatting
    if ($phone === "") {
        $errors[] = "Phone number is required.";
    } else {
        // Remove any spaces, dashes, or other characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Check length and format
        $phoneLength = strlen($phone);
        
        if ($phoneLength === 10) {
            // 10-digit number (e.g., 9123456789)
            if (substr($phone, 0, 1) === '9') {
                // Format as +639123456789
                $formattedPhone = '+63' . $phone;
            } else {
                $errors[] = "Invalid phone number format. Example: 9123456789";
            }
        } elseif ($phoneLength === 11) {
            // 11-digit number (e.g., 09123456789)
            if (substr($phone, 0, 2) === '09') {
                // Replace 09 with +63
                $formattedPhone = '+63' . substr($phone, 1);
            } else {
                $errors[] = "Invalid phone number format. Example: 09123456789";
            }
        } elseif ($phoneLength === 13 && substr($phone, 0, 3) === '+63') {
            // Already in +63 format (e.g., +639123456789)
            $formattedPhone = $phone;
            // Remove the +63 to check if the remaining starts with 9
            $remaining = substr($formattedPhone, 3);
            if (substr($remaining, 0, 1) !== '9' || strlen($remaining) !== 10) {
                $errors[] = "Invalid phone number format. Example: +639123456789";
            }
        } else {
            $errors[] = "Invalid phone number format. Examples: 9123456789 or 09123456789";
        }
        
        // If no errors, set the formatted phone number
        if (!isset($errors[array_key_last($errors)]) || strpos($errors[array_key_last($errors)], "Invalid phone number") === false) {
            $phone = $formattedPhone;
        }
    }

    if ($role === "shop" && !$branch_id) {
        $errors[] = "Branch is required for shop role.";
    }

    // Duplicate username only (phone number can be duplicate)
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM Users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Username already exists.";
        }
        $stmt->close();
    }

    // --- INSERT USER ---
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        if ($branch_id !== null) {
            $stmt = $conn->prepare(
                "INSERT INTO Users (username, password_hash, role, branch_id, phone_number)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("sssis", $username, $password_hash, $role, $branch_id, $phone);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO Users (username, password_hash, role, branch_id, phone_number)
                 VALUES (?, ?, ?, NULL, ?)"
            );
            $stmt->bind_param("ssss", $username, $password_hash, $role, $phone);
        }

        if ($stmt->execute()) {
            $response["success"] = true;
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }

        $stmt->close();
    }

    if (!empty($errors)) {
        $response["errors"] = $errors;
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>