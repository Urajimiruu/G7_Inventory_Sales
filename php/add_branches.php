<?php

require_once "db_connection.php";


$branchName = $location = "";
$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $branchName = trim($_POST["branch_name"]);
    $location   = trim($_POST["location"]);

   
    if (empty($branchName)) {
        $errors[] = "Branch name is required.";
    }
    if (empty($location)) {
        $errors[] = "Location is required.";
    }

   
    if (empty($errors)) {
        $check = $conn->prepare("SELECT branch_id FROM Branches WHERE branch_name = ?");
        $check->bind_param("s", $branchName);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors[] = "Branch name already exists. Please choose another.";
        }
        $check->close();
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO Branches (branch_name, location) VALUES (?, ?)");
        $stmt->bind_param("ss", $branchName, $location);

        if ($stmt->execute()) {
            $success = "Branch <strong>$branchName</strong> at <strong>$location</strong> added successfully!";
            $branchName = $location = "";
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Branch</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; }
        .error { color: red; }
        .success { color: green; }
        form { max-width: 400px; padding: 15px; border: 1px solid #ccc; border-radius: 8px; }
        label { display: block; margin-top: 10px; }
        input[type=text] { width: 100%; padding: 8px; margin-top: 5px; }
        input[type=submit] { margin-top: 15px; padding: 10px 15px; background: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer; }
        input[type=submit]:hover { background: #0056b3; }
    </style>
</head>
<body>

    <h2>Add New Branch</h2>


    <?php if (!empty($errors)): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?php echo $e; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <p class="success"><?php echo $success; ?></p>
    <?php endif; ?>

 
    <form method="POST" action="">
        <label>Branch Name:</label>
        <input type="text" name="branch_name" value="<?php echo htmlspecialchars($branchName); ?>" required>

        <label>Location:</label>
        <input type="text" name="location" value="<?php echo htmlspecialchars($location); ?>" required>

        <input type="submit" value="Add Branch">
    </form>

</body>
</html>
