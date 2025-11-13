<?php
// export_profitloss_report.php
session_start();
require_once "db_connection.php";

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// GET params
$branch  = $_GET['branch'] ?? '';
$product = $_GET['product'] ?? '';
$search  = trim($_GET['search'] ?? '');
$sort    = $_GET['sort'] ?? '';

// Base SQL
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
if ($branch !== '') {
    $sql .= " AND s.branch_id = ? ";
    $types .= "i";
    $params[] = (int)$branch;
}
if ($product !== '') {
    $sql .= " AND s.product_id = ? ";
    $types .= "i";
    $params[] = (int)$product;
}

// group and order
$sql .= " GROUP BY b.branch_name, p.product_name ";

if ($sort === 'asc') {
    $sql .= " ORDER BY profit ASC";
} elseif ($sort === 'desc') {
    $sql .= " ORDER BY profit DESC";
} else {
    $sql .= " ORDER BY b.branch_name, p.product_name";
}

// prepare and execute
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("SQL prepare error");
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// dynamic filename
$branchNameForFile  = '';
$productNameForFile = '';

if ($branch) {
    $bStmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ?");
    $bStmt->bind_param("i", $branch);
    $bStmt->execute();
    $bStmt->bind_result($branchNameForFile);
    $bStmt->fetch();
    $bStmt->close();
    $branchNameForFile = preg_replace('/[^A-Za-z0-9_\-]/', '_', $branchNameForFile);
}

if ($product) {
    $pStmt = $conn->prepare("SELECT product_name FROM products WHERE product_id = ?");
    $pStmt->bind_param("i", $product);
    $pStmt->execute();
    $pStmt->bind_result($productNameForFile);
    $pStmt->fetch();
    $pStmt->close();
    $productNameForFile = preg_replace('/[^A-Za-z0-9_\-]/', '_', $productNameForFile);
}

// filename
$sortMap = ['asc' => 'LowestProfit', 'desc' => 'HighestProfit'];
$sortName = $sortMap[$sort] ?? 'Sorted';

$filename = "profitloss_report";
if ($branchNameForFile)  $filename .= "_branch{$branchNameForFile}";
if ($productNameForFile) $filename .= "_product{$productNameForFile}";
if ($sortName)           $filename .= "_sort{$sortName}";
$filename .= "_" . date('Ymd_His') . ".xls";

// headers
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename={$filename}");
header("Pragma: no-cache");
header("Expires: 0");

// table output
echo "<table border='1'>";
echo "<tr>
        <th>Branch</th>
        <th>Product</th>
        <th>Total Quantity Sold</th>
        <th>Total Sales</th>
        <th>Total Cost</th>
        <th>Profit / Loss</th>
        <th>Status</th>
      </tr>";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $branch  = htmlspecialchars($row['branch_name'], ENT_QUOTES);
        $product = htmlspecialchars($row['product_name'], ENT_QUOTES);
        $qty     = (int)$row['total_sold'];
        $sales   = number_format((float)$row['total_sales'], 2);
        $cost    = number_format((float)$row['total_cost'], 2);
        $profit  = (float)$row['profit'];
        $profitFmt = number_format($profit, 2);

        // Status
        if ($profit > 0) {
            $statusText = "Profit";
        } elseif ($profit < 0) {
            $statusText = "Loss";
        } else {
            $statusText = "Break-even";
        }

        echo "<tr>
                <td>{$branch}</td>
                <td>{$product}</td>
                <td>{$qty}</td>
                <td>₱{$sales}</td>
                <td>₱{$cost}</td>
                <td>₱{$profitFmt}</td>
                <td>{$statusText}</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='7' style='text-align:center;'>No profit/loss records found</td></tr>";
}

$stmt->close();
$conn->close();
?>
