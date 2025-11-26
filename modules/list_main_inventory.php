<?php
require_once __DIR__ . "/../db_connection.php";

$unit   = $_GET['unit'] ?? '';
$sort   = $_GET['sort'] ?? 'ASC';
$search = $_GET['search'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50; // items per page
$offset = ($page - 1) * $limit;

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

/* Count total for pagination */
$countSql = "SELECT COUNT(*) FROM ($sql) AS temp";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalItems = $countStmt->get_result()->fetch_row()[0];
$totalPages = ceil($totalItems / $limit);

/* Add ordering and limit */
$sql .= " ORDER BY quantity $sort, p.product_name ASC LIMIT ?, ?";
$types .= "ii";
$params[] = $offset;
$params[] = $limit;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
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

<tr>
    <td colspan="6" style="text-align:center;">
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
    <!-- Prev button -->
    <?php if ($page > 1): ?>
        <button class="btn btn-primary" onclick="loadMainInventory(<?= $page - 1 ?>)">Prev</button>
    <?php endif; ?>

    <?php
    $windowSize = 2; // show current ±2 pages
    $start = max(1, $page - $windowSize);
    $end   = min($totalPages, $page + $windowSize);

    if ($start > 1) {
        echo '<button class="btn btn-secondary" onclick="loadMainInventory(1)">1</button>';
        if ($start > 2) echo '<span>...</span>';
    }

    for ($i = $start; $i <= $end; $i++):
    ?>
        <button 
            class="btn <?= ($i === $page) ? 'btn-warning' : 'btn-primary' ?>" 
            onclick="loadMainInventory(<?= $i ?>)">
            <?= $i ?>
        </button>
    <?php endfor; ?>

    <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages - 1) echo '<span>...</span>'; ?>
        <button class="btn btn-secondary" onclick="loadMainInventory(<?= $totalPages ?>)"><?= $totalPages ?></button>
    <?php endif; ?>

    <!-- Next button -->
    <?php if ($page < $totalPages): ?>
        <button class="btn btn-primary" onclick="loadMainInventory(<?= $page + 1 ?>)">Next</button>
    <?php endif; ?>
</div>
        <?php endif; ?>
    </td>
</tr>
