<?php
session_start();
require_once "db_connection.php";
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

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

// ---- SQL base ----
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
WHERE s.status != 'returned'
";

$params = [];
$types  = "";

// role restriction
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

// branch filter
if ($branch !== '') {
    $sql .= " AND s.branch_id = ? ";
    $types .= "i";
    $params[] = (int)$branch;
}

// product filter
if ($product !== '') {
    $sql .= " AND s.product_id = ? ";
    $types .= "i";
    $params[] = (int)$product;
}

$sql .= " GROUP BY b.branch_name, p.product_name ";

// sorting
if ($sort === 'asc') {
    $sql .= " ORDER BY profit ASC";
} elseif ($sort === 'desc') {
    $sql .= " ORDER BY profit DESC";
} else {
    $sql .= " ORDER BY b.branch_name, p.product_name";
}

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

// ---- Label helpers ----
function getBranchLabel($conn, $id) {
    if (!$id) return "All Branches";
    $name = null;
    $stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($name);
    $stmt->fetch();
    $stmt->close();
    return $name ?: "All Branches";
}

function getProductLabel($conn, $id) {
    if (!$id) return "All Products";
    $name = null;
    $stmt = $conn->prepare("SELECT product_name FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($name);
    $stmt->fetch();
    $stmt->close();
    return $name ?: "All Products";
}

$branchLabel  = getBranchLabel($conn, (int)$branch);
$productLabel = getProductLabel($conn, (int)$product);

// ---- Totals ----
$totalQty    = 0;
$totalSales  = 0;
$totalCost   = 0;
$totalProfit = 0;

foreach ($rows as $r) {
    $totalQty    += (int)$r['total_sold'];
    $totalSales  += (float)$r['total_sales'];
    $totalCost   += (float)$r['total_cost'];
    $totalProfit += (float)$r['profit'];
}

// peso formatting
function peso($n) {
    return "₱" . number_format((float)$n, 2);
}

// ---- Build PDF HTML ----
$html = "
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
h2 { text-align: center; margin-bottom: 4px; }
.report-meta { text-align: center; margin-bottom: 12px; font-size: 10px; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #555; padding: 5px 4px; }
th { background: #f2f2f2; font-weight: bold; }
.right { text-align: right; }
.total-row { background: #e8e8e8; font-weight: bold; }
.profit { color: green; font-weight:bold; }
.loss { color: red; font-weight:bold; }
.break { color: #555; font-weight:bold; }
</style>

<h2>Profit & Loss Report</h2>
<div class='report-meta'>
    Branch: <strong>{$branchLabel}</strong><br>
    Product: <strong>{$productLabel}</strong><br>
    Generated: <strong>" . date('Y-m-d H:i:s') . "</strong>
</div>

<table>
<tr>
    <th>Branch</th>
    <th>Product</th>
    <th class='right'>Total Quantity Sold</th>
    <th class='right'>Total Sales</th>
    <th class='right'>Total Cost</th>
    <th class='right'>Profit / Loss</th>
    <th>Status</th>
</tr>
";

if (count($rows) > 0) {
    foreach ($rows as $r) {
        $profit = (float)$r['profit'];

        if ($profit > 0) {
            $profitClass = "profit";
            $statusText = "Profit";
        } elseif ($profit < 0) {
            $profitClass = "loss";
            $statusText = "Loss";
        } else {
            $profitClass = "break";
            $statusText = "Break-even";
        }

        $html .= "
        <tr>
            <td>" . htmlspecialchars($r['branch_name']) . "</td>
            <td>" . htmlspecialchars($r['product_name']) . "</td>
            <td class='right'>" . (int)$r['total_sold'] . "</td>
            <td class='right'>" . peso($r['total_sales']) . "</td>
            <td class='right'>" . peso($r['total_cost']) . "</td>
            <td class='right {$profitClass}'>" . peso($profit) . "</td>
            <td>{$statusText}</td>
        </tr>";
    }
} else {
    $html .= "<tr><td colspan='7' style='text-align:center;'>No records found</td></tr>";
}

$html .= "
<tr class='total-row'>
    <td colspan='2'>TOTALS</td>
    <td class='right'>{$totalQty}</td>
    <td class='right'>" . peso($totalSales) . "</td>
    <td class='right'>" . peso($totalCost) . "</td>
    <td class='right'>" . peso($totalProfit) . "</td>
    <td></td>
</tr>
</table>
";

// ---- Render PDF ----
$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("profitloss_report_" . date('Ymd_His') . ".pdf", ["Attachment" => true]);
exit;
