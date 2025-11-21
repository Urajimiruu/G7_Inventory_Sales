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
$sql .= " ORDER BY bi.quantity $sort, p.product_name ASC ";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<tr><td colspan='7' style='text-align:center;'>No data found</td></tr>";
    exit;
}

while ($row = $result->fetch_assoc()) {

    $qty = (int)$row['quantity'];

    // Status
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
}
