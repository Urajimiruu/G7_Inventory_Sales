<?php
// include database connection
require_once "db_connection.php";

// initialize variables
$username = $password = $role = "";
$branch_id = null;
$errors = [];
$success = "";

// handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username  = trim($_POST["username"]);
    $password  = trim($_POST["password"]);
    $role      = $_POST["role"];

    // if role is shop, take branch_id, else set null
    if ($role === "shop") {
        $branch_id = $_POST["branch_id"];
    } else {
        $branch_id = null;
    }

    // ✅ Validations
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    if (empty($role)) {
        $errors[] = "Role is required.";
    }
    if ($role === "shop" && empty($branch_id)) {
        $errors[] = "Branch is required for shop users.";
    }

    // ✅ Check duplicate username
    if (empty($errors)) {
        $check = $conn->prepare("SELECT user_id FROM Users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors[] = "Username already exists.";
        }
        $check->close();
    }

    // ✅ Insert if no errors
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        if ($branch_id !== null) {
            $stmt = $conn->prepare("INSERT INTO Users (username, password_hash, role, branch_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $username, $password_hash, $role, $branch_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO Users (username, password_hash, role, branch_id) VALUES (?, ?, ?, NULL)");
            $stmt->bind_param("sss", $username, $password_hash, $role);
        }

        if ($stmt->execute()) {
            $success = "✅ User <strong>$username</strong> created successfully!";
            $username = $password = $role = "";
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
<html>
<head>
    <title>Add User</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; }
        .error { color: red; }
        .success { color: green; }
        form { max-width: 400px; padding: 15px; border: 1px solid #ccc; border-radius: 8px; }
        label { display: block; margin-top: 10px; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; }
        input[type=submit] { margin-top: 15px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; }
        input[type=submit]:hover { background: #218838; }
    </style>
    <script>
        function toggleBranchDropdown() {
            let role = document.getElementById("role").value;
            let branchDropdown = document.getElementById("branch_id");

            if (role === "admin") {
                branchDropdown.disabled = true;
                branchDropdown.value = ""; // clear selection
            } else {
                branchDropdown.disabled = false;
            }
        }
    </script>
</head>
<body>

    <h2>Create New User</h2>

    <!-- Show errors -->
    <?php if (!empty($errors)): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?php echo $e; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Show success -->
    <?php if (!empty($success)): ?>
        <p class="success"><?php echo $success; ?></p>
    <?php endif; ?>

    <!-- User Form -->
    <form method="POST" action="">
        <label>Username:</label>
        <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required>

        <label>Password:</label>
        <input type="password" name="password" required>

        <label>Role:</label>
        <select name="role" id="role" onchange="toggleBranchDropdown()" required>
            <option value="">-- Select Role --</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?php echo $r; ?>" <?php if ($r == $role) echo "selected"; ?>><?php echo ucfirst($r); ?></option>
            <?php endforeach; ?>
        </select>

        <label>Branch:</label>
        <select name="branch_id" id="branch_id" <?php echo ($role === "admin") ? "disabled" : ""; ?>>
            <option value="">-- Select Branch --</option>
            <?php while ($row = $branches->fetch_assoc()): ?>
                <option value="<?php echo $row['branch_id']; ?>" <?php if ($row['branch_id'] == $branch_id) echo "selected"; ?>>
                    <?php echo htmlspecialchars($row['branch_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <input type="submit" value="Create User">
    </form>

</body>
</html>
