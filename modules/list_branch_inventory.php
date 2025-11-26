<?php

// ensure session is available
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../db_connection.php";

// if not logged in, return an "Unauthorized" row (so the table remains intact)
if (!isset($_SESSION['user_id'])) {
    echo "<tr><td colspan='7' style='text-align:center;'>Unauthorized</td></tr>";
    exit;
}


$role      = $_SESSION['role'] ?? '';
$userBranch = (int)($_SESSION['branch_id'] ?? 0);

$productId = $_GET['product_id'] ?? '';
$unit      = $_GET['unit'] ?? '';
$branchId  = $_GET['branch_id'] ?? '';
$sort      = $_GET['sort'] ?? 'ASC';
$search    = $_GET['search'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50; // items per page
$offset = ($page - 1) * $limit;

$sql = "
SELECT 
    bi.branch_id,
    bi.product_id,
    bi.quantity,
    p.product_name,
    p.unit,
    p.cost_price,
    p.selling_price,
    b.branch_name
FROM BranchInventory bi
JOIN Products p ON bi.product_id = p.product_id
JOIN Branches b ON bi.branch_id = b.branch_id
WHERE 1=1
";

$params = [];
$types = "";

/* IF SHOP — restrict to their branch only */
if ($role === 'shop') {
    $sql .= " AND bi.branch_id = ? ";
    $types .= "i";
    $params[] = $userBranch;
}

/* Branch filter (admin only) */
if ($role === 'admin' && $branchId !== '') {
    $sql .= " AND bi.branch_id = ? ";
    $types .= "i";
    $params[] = $branchId;
}

/* Product filter */
if ($productId !== '') {
    $sql .= " AND bi.product_id = ? ";
    $types .= "i";
    $params[] = $productId;
}

/* Unit filter */
if ($unit !== '') {
    $sql .= " AND p.unit = ? ";
    $types .= "s";
    $params[] = $unit;
}

/* Search */
if ($search !== '') {
    $sql .= " AND p.product_name LIKE ? ";
    $types .= "s";
    $params[] = "%$search%";
}

/* Sorting */

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
    echo "<tr><td colspan='7' style='text-align:center;'>No data found</td></tr>";
    exit;
}

while ($row = $result->fetch_assoc()) {

    $qty = (int)$row['quantity'];

    if ($qty === 0) {
        $statusText = "No Stock";
        $statusClass = "no-stock";
    } elseif ($qty < 20) {
        $statusText = "Low Stock";
        $statusClass = "low-stock";
    } else {
        $statusText = "On Stock";
        $statusClass = "on-stock";
    }

    echo "
    <tr>
        <td>" . htmlspecialchars($row['branch_name']) . "</td>
        <td>" . htmlspecialchars($row['product_name']) . "</td>
        <td class='muted'>" . htmlspecialchars($row['unit']) . "</td>
        <td class='right'>₱" . number_format($row['cost_price'], 2) . "</td>
        <td class='right'>₱" . number_format($row['selling_price'], 2) . "</td>
        <td class='right'>$qty</td>
        <td><span class='status $statusClass'><span class='dot'></span>$statusText</span></td>
    </tr>
    ";
}  // END WHILE
?>

<tr>
    <td colspan="6" style="text-align:center;">
        <?php if ($totalPages > 1): ?>
            <div class="pagination">

                <!-- Prev -->
                <?php if ($page > 1): ?>
                    <button class="btn btn-primary" onclick="loadBranchInventory(<?= $page - 1 ?>)">Prev</button>
                <?php endif; ?>

                <?php
                $windowSize = 2;
                $start = max(1, $page - $windowSize);
                $end   = min($totalPages, $page + $windowSize);

                if ($start > 1) {
                    echo '<button class="btn btn-secondary" onclick="loadBranchInventory(1)">1</button>';
                    if ($start > 2) echo '<span>...</span>';
                }

                for ($i = $start; $i <= $end; $i++):
                ?>
                    <button 
                        class="btn <?= ($i === $page) ? 'btn-warning' : 'btn-primary' ?>" 
                        onclick="loadBranchInventory(<?= $i ?>)">
                        <?= $i ?>
                    </button>
                <?php endfor; ?>

                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1) echo '<span>...</span>'; ?>
                    <button class="btn btn-secondary" onclick="loadBranchInventory(<?= $totalPages ?>)"><?= $totalPages ?></button>
                <?php endif; ?>

                <!-- Next -->
                <?php if ($page < $totalPages): ?>
                    <button class="btn btn-primary" onclick="loadBranchInventory(<?= $page + 1 ?>)">Next</button>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    </td>
</tr>
