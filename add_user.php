<?php
// include database connection
require_once "db_connection.php";

// initialize variables
$username = $password = $role = $phone_number = "";
$branch_id = null;
$errors = [];
$success = "";

// handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username      = trim($_POST["username"]);
    $password      = trim($_POST["password"]);
    $role          = $_POST["role"];
    $phone_number  = trim($_POST["phone_number"]);

    // if role is shop, take branch_id, else set null
    if ($role === "shop") {
        $branch_id = $_POST["branch_id"];
    } else {
        $branch_id = null;
    }

    // Validations
    if (empty($username)) $errors[] = "Username is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if (empty($role)) $errors[] = "Role is required.";
    if (empty($phone_number)) {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match('/^\+63\d{10}$/', $phone_number)) {
        $errors[] = "Invalid phone number format. Use +63 followed by 10 digits (e.g. +639123456789).";
    }
    if ($role === "shop" && empty($branch_id)) $errors[] = "Branch is required for shop users.";

    // Check duplicate username
    if (empty($errors)) {
        $check = $conn->prepare("SELECT user_id FROM Users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) $errors[] = "Username already exists.";
        $check->close();
    }

    // Insert if no errors
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        if ($branch_id !== null) {
            $stmt = $conn->prepare("INSERT INTO Users (username, password_hash, role, branch_id, phone_number)
                                    VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssds", $username, $password_hash, $role, $branch_id, $phone_number);
        } else {
            $stmt = $conn->prepare("INSERT INTO Users (username, password_hash, role, branch_id, phone_number)
                                    VALUES (?, ?, ?, NULL, ?)");
            $stmt->bind_param("ssss", $username, $password_hash, $role, $phone_number);
        }

        if ($stmt->execute()) {
            $success = "User <strong>$username</strong> created successfully!";
            $username = $password = $role = $phone_number = "";
            $branch_id = null;
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// load branches for dropdown
$branches = $conn->query("SELECT branch_id, branch_name FROM Branches");

// roles dropdown
$roles = ["admin", "shop"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; padding: 20px; }
        form { background: white; padding: 20px; border-radius: 10px; max-width: 400px; margin: auto; }
        input, select { width: 100%; padding: 10px; margin: 8px 0; }
        button { padding: 10px; background: #007BFF; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h2>Add New User</h2>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <form method="post">
        <label>Username</label>
        <input type="text" name="username" value="<?= htmlspecialchars($username) ?>">

        <label>Password</label>
        <input type="password" name="password" value="">

        <label>Phone Number</label>
        <input type="text" name="phone_number" placeholder="+639123456789" value="<?= htmlspecialchars($phone_number) ?>">

        <label>Role</label>
        <select name="role" id="role" onchange="toggleBranch()">
            <option value="">-- Select Role --</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
        </select>

        <div id="branchDiv" style="display: none;">
            <label>Branch</label>
            <select name="branch_id">
                <option value="">-- Select Branch --</option>
                <?php while ($b = $branches->fetch_assoc()): ?>
                    <option value="<?= $b['branch_id'] ?>" <?= $branch_id == $b['branch_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['branch_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <button type="submit">Add User</button>
    </form>

    <script>
        function toggleBranch() {
            const role = document.getElementById("role").value;
            document.getElementById("branchDiv").style.display = role === "shop" ? "block" : "none";
        }
        toggleBranch();
    </script>
</body>
</html>
