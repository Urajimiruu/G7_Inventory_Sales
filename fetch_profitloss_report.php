<?php
// fetch_profitloss_report.php
require_once "db_connection.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<tr><td colspan='7' style='text-align:center;'>Unauthorized</td></tr>";
    exit;
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$search   = trim($_GET['search'] ?? '');
$filterBranch  = trim($_GET['branch'] ?? '');
$filterProduct = trim($_GET['product'] ?? '');
$sortProfit    = trim($_GET['sort'] ?? '');

// Build base SQL (aggregate from Sales)
$sql = "
SELECT 
    b.branch_name,
    p.product_name,
    SUM(s.quantity) AS total_sold,
    SUM(s.quantity * p.selling_price) AS total_sales,
    SUM(s.quantity * p.cost_price) AS total_cost,
    (SUM(s.quantity * p.selling_price) - SUM(s.quantity * p.cost_price)) AS profit
FROM Sales s
JOIN Products p ON s.product_id = p.product_id
JOIN Branches b ON s.branch_id = b.branch_id
WHERE 1
";

$params = [];
$types  = "";

// shop users restricted to their branch
if (strtolower($role) === 'shop' && $branchId > 0) {
    $sql .= " AND s.branch_id = ? ";
    $types .= "i";
    $params[] = $branchId;
}

// search filter
if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ? OR b.branch_name LIKE ?) ";
    $types .= "ss";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// explicit branch/product filters
if ($filterBranch !== '') {
    $sql .= " AND s.branch_id = ? ";
    $types .= "i";
    $params[] = (int)$filterBranch;
}
if ($filterProduct !== '') {
    $sql .= " AND s.product_id = ? ";
    $types .= "i";
    $params[] = (int)$filterProduct;
}

// group and order
$sql .= " GROUP BY b.branch_name, p.product_name ";

if ($sortProfit === 'asc') {
    $sql .= " ORDER BY profit ASC";
} elseif ($sortProfit === 'desc') {
    $sql .= " ORDER BY profit DESC";
} else {
    $sql .= " ORDER BY b.branch_name, p.product_name";
}

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo "<tr><td colspan='7' style='text-align:center;'>SQL prepare error</td></tr>";
    exit;
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $branch = htmlspecialchars($row['branch_name'], ENT_QUOTES);
        $product = htmlspecialchars($row['product_name'], ENT_QUOTES);
        $qty = (int)$row['total_sold'];
        $sales = number_format((float)$row['total_sales'], 2);
        $cost = number_format((float)$row['total_cost'], 2);
        $profit = (float)$row['profit'];
        $profitFmt = number_format($profit, 2);

        // Status indicator
        if ($profit > 0) {
            $statusText = "Profit";
            $statusClass = "pl-profit";
        } elseif ($profit < 0) {
            $statusText = "Loss";
            $statusClass = "pl-loss";
        } else {
            $statusText = "Break-even";
            $statusClass = "pl-neutral";
        }

        echo "
        <tr>
            <td>{$branch}</td>
            <td>{$product}</td>
            <td class='right'>{$qty}</td>
            <td class='right'>₱{$sales}</td>
            <td class='right'>₱{$cost}</td>
            <td class='right'>₱{$profitFmt}</td>
            <td>
                <div class='pl-status'>
                    <span class='dot {$statusClass}'></span>
                    {$statusText}
                </div>
            </td>
        </tr>
        ";
    }
} else {
    echo "<tr><td colspan='7' style='text-align:center;'>No profit/loss records found</td></tr>";
}

$stmt->close();
$conn->close();
