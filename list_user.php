<?php
require_once "db_connection.php";

// Handle delete request
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM Users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: list_user.php");
    exit;
}

// fetch all users with branch names
$sql = "SELECT u.user_id, u.username, u.password_hash, u.role, b.branch_name
        FROM Users u
        LEFT JOIN Branches b ON u.branch_id = b.branch_id
        ORDER BY u.user_id ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>User List</title>
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

<h2>User List</h2>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Branch</th>
            <th>Actions</th>
        </tr>
    </thead>
   <tbody>
    <?php if ($result->num_rows > 0): ?>
        <?php $counter = 1; ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $counter++; ?></td>
                <td><?php echo htmlspecialchars($row['username']); ?></td>
                <td><?php echo htmlspecialchars($row['role']); ?></td>
                <td><?php echo htmlspecialchars($row['branch_name'] ?? 'N/A'); ?></td>
                <td>
                    <a class="edit" href="edit_user.php?id=<?php echo $row['user_id']; ?>">Edit</a>
                    <a class="delete" href="list_users.php?delete=<?php echo $row['user_id']; ?>" onclick="return confirm('Are you sure?');">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="5">No users found.</td>
        </tr>
    <?php endif; ?>
</tbody>

</table>

</body>
</html>
