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

    if ($phone === "") {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match('/^\+63\d{10}$/', $phone)) {
        $errors[] = "Invalid phone number format. Must be +63 followed by 10 digits.";
    }

    if ($role === "shop" && !$branch_id) {
        $errors[] = "Branch is required for shop role.";
    }

    // Duplicate username
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
