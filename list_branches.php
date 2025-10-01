<?php
require_once "db_connection.php";

// Handle delete request
if (isset($_GET['delete'])) {
    $branch_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM Branches WHERE branch_id = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $stmt->close();
    header("Location: list_branches.php");
    exit;
}

// Fetch all branches
$sql = "SELECT * FROM Branches ORDER BY branch_id ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Branch List</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:hover { background-color: #f9f9f9; }
        a { text-decoration: none; padding: 5px; border-radius: 3px; }
        .edit { background: #ffc107; color: white; }
        .delete { background: #dc3545; color: white; }
    </style>
</head>
<body>

<h2>Branch List</h2>
<a href="add_branches.php" style="background:green;color:white;padding:5px;text-decoration:none;">Add Branch</a><br><br>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Branch Name</th>
            <th>Location</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php $counter = 1; ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['location']); ?></td>
                    <td>
                        <a class="edit" href="edit_branches.php?id=<?php echo $row['branch_id']; ?>">Edit</a>
                        <a class="delete" href="list_branches.php?delete=<?php echo $row['branch_id']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="4">No branches found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>
