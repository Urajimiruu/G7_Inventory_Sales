<?php
require_once "db_connection.php";

$branch_id = intval($_GET['id']);
$errors = [];
$success = "";

// Get branch info
$stmt = $conn->prepare("SELECT * FROM Branches WHERE branch_id = ?");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$result = $stmt->get_result();
$branch = $result->fetch_assoc();
$stmt->close();

if (!$branch) {
    die("Branch not found.");
}

// Handle update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $branch_name = trim($_POST["branch_name"]);
    $location    = trim($_POST["location"]);

    if (empty($branch_name)) {
        $errors[] = "Branch name is required.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE Branches SET branch_name=?, location=? WHERE branch_id=?");
        $stmt->bind_param("ssi", $branch_name, $location, $branch_id);

        if ($stmt->execute()) {
            $success = "Branch updated successfully!";
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
    <title>Edit Branch</title>
</head>
<body>

<h2>Edit Branch</h2>

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
    <label>Branch Name:</label><br>
    <input type="text" name="branch_name" value="<?php echo htmlspecialchars($branch['branch_name']); ?>" required><br><br>

    <label>Location:</label><br>
    <input type="text" name="location" value="<?php echo htmlspecialchars($branch['location']); ?>"><br><br>

    <input type="submit" value="Update Branch">
</form>

</body>
</html>
