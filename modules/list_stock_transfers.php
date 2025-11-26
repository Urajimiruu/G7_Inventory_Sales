<?php
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . "/../db_connection.php";

$productId = $_GET['product_id'] ?? '';
$fromDate  = $_GET['from_date'] ?? '';
$toDate    = $_GET['to_date'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50; // items per page
$offset = ($page - 1) * $limit;

$sql = "
    SELECT 
        st.transfer_id,
        st.quantity,
        st.transfer_date,
        p.product_name,
        b.branch_name
    FROM stocktransfers st
    JOIN products p ON st.product_id = p.product_id
    JOIN branches b ON st.branch_id = b.branch_id
    WHERE 1=1
";

$params = [];
$types  = "";

/* Filter product */
if ($productId !== "") {
    $sql .= " AND st.product_id = ? ";
    $types .= "i";
    $params[] = (int)$productId;
}

/* Date range */
if ($fromDate !== "") {
    $sql .= " AND st.transfer_date >= ? ";
    $types .= "s";
    $params[] = $fromDate;
}

if ($toDate !== "") {
    $sql .= " AND st.transfer_date <= ? ";
    $types .= "s";
    $params[] = $toDate;
}

$countSql = "SELECT COUNT(*) FROM ($sql) AS temp";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalItems = $countStmt->get_result()->fetch_row()[0];
$totalPages = ceil($totalItems / $limit);

/* Add ordering and limit */
$sql .= " ORDER BY st.transfer_date DESC, st.transfer_id DESC LIMIT ?, ?";
$types .= "ii";
$params[] = $offset;
$params[] = $limit;


$stmt = $conn->prepare($sql);


$stmt->bind_param($types, ...$params);


$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<tr><td colspan='4' style='text-align:center;'>No transfer records found</td></tr>";
    exit;
}

while ($row = $result->fetch_assoc()):
?>
<tr>
    <td><?= htmlspecialchars($row['product_name']) ?></td>
    <td class="right"><?= (int)$row['quantity'] ?></td>
    <td><?= htmlspecialchars($row['branch_name']) ?></td>
    <td class="right"><?= htmlspecialchars($row['transfer_date']) ?></td>
</tr>
<?php endwhile; ?>

<tr>
    <td colspan="6" style="text-align:center;">
        <?php if ($totalPages > 1): ?>
            <div class="pagination">

                <!-- Prev -->
                <?php if ($page > 1): ?>
                    <button class="btn btn-primary" onclick="loadTransfers(<?= $page - 1 ?>)">Prev</button>
                <?php endif; ?>

                <?php
                $windowSize = 2;
                $start = max(1, $page - $windowSize);
                $end   = min($totalPages, $page + $windowSize);

                if ($start > 1) {
                    echo '<button class="btn btn-secondary" onclick="loadTransfers(1)">1</button>';
                    if ($start > 2) echo '<span>...</span>';
                }

                for ($i = $start; $i <= $end; $i++):
                ?>
                    <button 
                        class="btn <?= ($i === $page) ? 'btn-warning' : 'btn-primary' ?>" 
                        onclick="loadTransfers(<?= $i ?>)">
                        <?= $i ?>
                    </button>
                <?php endfor; ?>

                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1) echo '<span>...</span>'; ?>
                    <button class="btn btn-secondary" onclick="loadTransfers(<?= $totalPages ?>)"><?= $totalPages ?></button>
                <?php endif; ?>

                <!-- Next -->
                <?php if ($page < $totalPages): ?>
                    <button class="btn btn-primary" onclick="loadTransfers(<?= $page + 1 ?>)">Next</button>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    </td>
</tr>
