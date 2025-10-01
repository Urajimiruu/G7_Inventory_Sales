<?php
require_once "db_connection.php";

$user_id = intval($_GET['id']);
$errors = [];
$success = "";

// Get current user data
$stmt = $conn->prepare("SELECT * FROM Users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found");
}

// load branches for dropdown
$branches = $conn->query("SELECT branch_id, branch_name FROM Branches");
$roles = ["admin", "shop"];

// Handle update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username  = trim($_POST["username"]);
    $password  = trim($_POST["password"]); // may be empty
    $role      = $_POST["role"];
    $branch_id = ($role === "shop") ? $_POST["branch_id"] : null;

    if (empty($username)) {
        $errors[] = "Username is required.";
    }

    // Password validation if user entered a new password
    if (!empty($password) && strlen($password) < 5) {
        $errors[] = "Password must be at least 5 characters long.";
    }

    if (empty($errors)) {
        if (!empty($password)) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE Users SET username=?, password_hash=?, role=?, branch_id=? WHERE user_id=?");
            $stmt->bind_param("sssii", $username, $password_hash, $role, $branch_id, $user_id);
        } else {
            // Keep existing password hash
            $stmt = $conn->prepare("UPDATE Users SET username=?, role=?, branch_id=? WHERE user_id=?");
            $stmt->bind_param("ssii", $username, $role, $branch_id, $user_id);
        }

        if ($stmt->execute()) {
            $success = "User updated successfully!";
        } else {
            $errors[] = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
</head>
<body>
<h2>Edit User</h2>

<?php if (!empty($errors)): ?>
    <div style="color:red;">
        <?php foreach ($errors as $e) echo "<p>$e</p>"; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div style="color:green;">
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<form method="POST">
    <label>Username:</label><br>
    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required><br><br>

    <label>Password:</label><br>
    <input type="password" name="password" placeholder="Enter new password to change"><br>


    <label>Role:</label><br>
    <select name="role" id="role" onchange="toggleBranchDropdown()" required>
        <?php foreach ($roles as $r): ?>
            <option value="<?php echo $r; ?>" <?php if ($r == $user['role']) echo "selected"; ?>><?php echo ucfirst($r); ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Branch:</label><br>
    <select name="branch_id" id="branch_id" <?php echo ($user['role'] === "admin") ? "disabled" : ""; ?>>
        <option value="">-- Select Branch --</option>
        <?php while ($row = $branches->fetch_assoc()): ?>
            <option value="<?php echo $row['branch_id']; ?>" <?php if ($row['branch_id'] == $user['branch_id']) echo "selected"; ?>>
                <?php echo htmlspecialchars($row['branch_name']); ?>
            </option>
        <?php endwhile; ?>
    </select><br><br>

    <input type="submit" value="Update User">
</form>

<script>
    function toggleBranchDropdown() {
        let role = document.getElementById("role").value;
        let branchDropdown = document.getElementById("branch_id");

        branchDropdown.disabled = (role === "admin");
    }
</script>

</body>
</html>
