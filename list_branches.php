<?php
require_once "db_connection.php";

$search = $_GET['search'] ?? '';
$search = $conn->real_escape_string($search);

$query = "SELECT * FROM Branches WHERE 1";
if ($search !== '') {
    $query .= " AND branch_name LIKE '%$search%' OR location LIKE '%$search%'";
}
$query .= " ORDER BY branch_id ASC";

$result = $conn->query($query);

while ($row = $result->fetch_assoc()):
?>
<tr>
    <td class="right"><?= $row['branch_id'] ?></td>
    <td><?= htmlspecialchars($row['branch_name']) ?></td>
    <td><?= htmlspecialchars($row['location']) ?></td>
    <td>
        <button class="btn btn-warning btn-sm" onclick='openEditBranchModal(<?= json_encode($row) ?>)'>Edit</button>
        <button class="btn btn-danger btn-sm" onclick="deleteBranch(<?= $row['branch_id'] ?>)">Delete</button>
    </td>
</tr>
<?php endwhile; ?>
