<?php
require_once "db_connection.php";

$unit   = $_GET['unit'] ?? '';
$search = $_GET['search'] ?? '';
$sort   = $_GET['sort'] ?? 'ASC';

$unit   = $conn->real_escape_string($unit);
$search = $conn->real_escape_string($search);
$sort   = ($sort === "DESC") ? "DESC" : "ASC";

$query = "SELECT * FROM Products WHERE 1";

// Filter by unit
if (!empty($unit)) {
    $query .= " AND unit = '$unit'";
}

// Search
if (!empty($search)) {
    $query .= " AND (product_name LIKE '%$search%' OR description LIKE '%$search%' OR selling_price LIKE '%$search%' OR cost_price LIKE '%$search%')";
}

// Sorting by selling price
$query .= " ORDER BY selling_price $sort";

$result = $conn->query($query);

while ($row = $result->fetch_assoc()):
?>
<tr>
    <td class="right"><?= $row['product_id'] ?></td>
    <td><?= htmlspecialchars($row['product_name']) ?></td>
    <td><?= htmlspecialchars($row['description']) ?></td>
    <td><?= htmlspecialchars($row['unit']) ?></td>
    <td class="right"><?= number_format($row['cost_price'], 2) ?></td>
    <td class="right"><?= number_format($row['selling_price'], 2) ?></td>
    <td>
        <button class="btn btn-warning btn-sm"
            onclick='openEditProductModal(<?= json_encode($row) ?>)'>
            Edit
        </button>
        <button class="btn btn-danger btn-sm"
            onclick="deleteProduct(<?= $row['product_id'] ?>)">
            Delete
        </button>
    </td>
</tr>
<?php endwhile; ?>
