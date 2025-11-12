<?php
require_once "db_connection.php";

session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<tr><td colspan='10' style='text-align:center;'>Unauthorized</td></tr>";
    exit;
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$search   = trim($_GET['search'] ?? '');
$filterBranch  = trim($_GET['branch'] ?? '');
$filterProduct = trim($_GET['product'] ?? '');
$sortStock     = trim($_GET['sort'] ?? '');

// Base query with enhancements: total cost, potential revenue
$sql = "
SELECT 
    bi.branch_id,
    bi.product_id,
    bi.quantity,
    p.product_name,
    p.unit,
    p.cost_price,
    p.selling_price,
    (bi.quantity * p.cost_price) AS total_cost,
    (bi.quantity * p.selling_price) AS potential_revenue,
    b.branch_name
FROM branchinventory bi
JOIN products p ON bi.product_id = p.product_id
JOIN branches b ON bi.branch_id = b.branch_id
WHERE 1
";

// Restrict to shop branch if role is "shop"
$params = [];
$types  = "";

if (strtolower($role) === 'shop' && $branchId > 0) {
    $sql .= " AND bi.branch_id = ? ";
    $types .= "i";
    $params[] = $branchId;
}

// Search filter
if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ? OR b.branch_name LIKE ?)";
    $types .= "ss";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterBranch !== '') {
    $sql .= " AND bi.branch_id = ? ";
    $types .= "i";
    $params[] = (int)$filterBranch;
}

if ($filterProduct !== '') {
    $sql .= " AND bi.product_id = ? ";
    $types .= "i";
    $params[] = (int)$filterProduct;
}


if ($sortStock === "asc") {
    $sql .= " ORDER BY bi.quantity ASC";
} elseif ($sortStock === "desc") {
    $sql .= " ORDER BY bi.quantity DESC";
} else {
    $sql .= " ORDER BY b.branch_name, p.product_name";
}


$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $branchName   = htmlspecialchars($row['branch_name'], ENT_QUOTES);
        $productName  = htmlspecialchars($row['product_name'], ENT_QUOTES);
        $unit         = htmlspecialchars($row['unit'], ENT_QUOTES);
        $costPrice    = number_format((float)$row['cost_price'], 2);
        $sellingPrice = number_format((float)$row['selling_price'], 2);
        $qty          = (int)$row['quantity'];
        $totalCost    = number_format((float)$row['total_cost'], 2);
        $revenue      = number_format((float)$row['potential_revenue'], 2);

        // Stock status
        if ($qty === 0) {
            $statusText  = 'No Stock';
            $statusClass = 'inv-no-stock';
        } elseif ($qty < 20) {
            $statusText  = 'Low Stock';
            $statusClass = 'inv-low-stock';
        } else {
            $statusText  = 'On Stock';
            $statusClass = 'inv-on-stock';
        }

        echo "
        <tr>
            <td>{$branchName}</td>
            <td>{$productName}</td>
            <td>{$unit}</td>
            <td class='right'>₱{$costPrice}</td>
            <td class='right'>₱{$sellingPrice}</td>
            <td class='right'>{$qty}</td>
            <td class='right'>₱{$totalCost}</td>
            <td class='right'>₱{$revenue}</td>
            <td>
                <div class='inv-status'>
                    <span class='dot {$statusClass}'></span>
                    {$statusText}
                </div>
            </td>
        </tr>
        ";
    }
} else {
    echo "<tr><td colspan='9' style='text-align:center;'>No branch inventory found</td></tr>";
}

$conn->close();
