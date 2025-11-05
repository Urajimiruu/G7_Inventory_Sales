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
    $username     = trim($_POST["username"]);
    $password     = trim($_POST["password"]); // optional
    $role         = $_POST["role"];
    $branch_id    = ($role === "shop") ? $_POST["branch_id"] : null;
    $phone_number = trim($_POST["phone_number"]);

    // Validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    }

    if (!empty($password) && strlen($password) < 5) {
        $errors[] = "Password must be at least 5 characters long.";
    }

   if (empty($phone_number)) {
            $errors[] = "Phone number is required.";
        } elseif (!preg_match('/^\+63\s\d{3}\s\d{3}\s\d{4}$/', $phone_number)) {
            $errors[] = "Invalid phone number format. Use +63 912 345 6789 format with spaces.";
        }



    if (empty($errors)) {
        if (!empty($password)) {
            // Update with new password
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE Users 
                                    SET username=?, password_hash=?, role=?, branch_id=?, phone_number=? 
                                    WHERE user_id=?");
            $stmt->bind_param("sssdsi", $username, $password_hash, $role, $branch_id, $phone_number, $user_id);
        } else {
            // Keep existing password
            $stmt = $conn->prepare("UPDATE Users 
                                    SET username=?, role=?, branch_id=?, phone_number=? 
                                    WHERE user_id=?");
            $stmt->bind_param("ssdsi", $username, $role, $branch_id, $phone_number, $user_id);
        }

        if ($stmt->execute()) {
            $success = "User updated successfully!";
            // Refresh displayed data
            $stmt->close();
            $stmt = $conn->prepare("SELECT * FROM Users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
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

<h2>Edit User</h2>

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

<form method="POST">
    <label>Username:</label>
    <input type="text" name="username" value="<?= htmlspecialchars($user['username']); ?>" required>

    <label>Password:</label>
    <input type="password" name="password" placeholder="Leave blank to keep current password">

    <label>Phone Number:</label>
   <input type="text" name="phone_number" placeholder="+63 912 345 6789" value="<?= htmlspecialchars($user['phone_number']); ?>" required>


    <label>Role:</label>
    <select name="role" id="role" onchange="toggleBranchDropdown()" required>
        <?php foreach ($roles as $r): ?>
            <option value="<?= $r; ?>" <?= ($r == $user['role']) ? "selected" : ""; ?>>
                <?= ucfirst($r); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Branch:</label>
    <select name="branch_id" id="branch_id" <?= ($user['role'] === "admin") ? "disabled" : ""; ?>>
        <option value="">-- Select Branch --</option>
        <?php
        // reset pointer and re-fetch branch list
        $branches->data_seek(0);
        while ($row = $branches->fetch_assoc()): ?>
            <option value="<?= $row['branch_id']; ?>" <?= ($row['branch_id'] == $user['branch_id']) ? "selected" : ""; ?>>
                <?= htmlspecialchars($row['branch_name']); ?>
            </option>
        <?php endwhile; ?>
    </select>

    <button type="submit">Update User</button>
</form>

<script>
function toggleBranchDropdown() {
    const role = document.getElementById("role").value;
    document.getElementById("branch_id").disabled = (role === "admin");
}
</script>

<script>

document.querySelector('input[name="phone_number"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, ''); // remove non-digit
    if (value.startsWith('63')) value = '+' + value;
    if (value.startsWith('+63')) {
        let digits = value.replace('+63', '');
        digits = digits.substring(0, 10); // limit to 10 digits
        let formatted = '+63';
        if (digits.length > 0) formatted += ' ' + digits.substring(0, 3);
        if (digits.length > 3) formatted += ' ' + digits.substring(3, 6);
        if (digits.length > 6) formatted += ' ' + digits.substring(6);
        e.target.value = formatted;
    }
});

</script>

</body>
</html>
