<?php
require_once __DIR__ . "/../db_connection.php";

$unit   = $_GET['unit'] ?? '';
$sort   = $_GET['sort'] ?? 'ASC';
$search = $_GET['search'] ?? '';

$sql = "
    SELECT 
        p.product_id,
        p.product_name,
        p.unit,
        p.cost_price,
        p.selling_price,
        COALESCE(m.quantity, 0) AS quantity
    FROM Products p
    LEFT JOIN MainInventory m ON p.product_id = m.product_id
    WHERE 1=1
";

$params = [];
$types = "";

/* Filter by unit */
if ($unit !== '') {
    $sql .= " AND p.unit = ? ";
    $types .= "s";
    $params[] = $unit;
}

/* Search product name */
if ($search !== '') {
    $sql .= " AND p.product_name LIKE ? ";
    $types .= "s";
    $params[] = "%$search%";
}

/* Sort by quantity */
$sql .= " ORDER BY quantity $sort, p.product_name ASC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<tr><td colspan='6' style='text-align:center;'>No products found</td></tr>";
    exit;
}

while ($row = $result->fetch_assoc()):
?>
<tr>
    <td><?= htmlspecialchars($row['product_name']) ?></td>
    <td class="muted"><?= htmlspecialchars($row['unit']) ?></td>
    <td class="right">₱<?= number_format($row['cost_price'], 2) ?></td>
    <td class="right">₱<?= number_format($row['selling_price'], 2) ?></td>
    <td class="right"><?= (int)$row['quantity'] ?></td>
    <td>
        <button class="btn btn-primary"
            onclick="openRestockModal(
                <?= $row['product_id'] ?>,
                '<?= htmlspecialchars($row['product_name'], ENT_QUOTES) ?>',
                <?= (int)$row['quantity'] ?>
            )">Restock</button>
    </td>
</tr>
<?php endwhile; ?>
