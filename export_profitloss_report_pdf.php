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

// Build same WHERE as fetch to ensure consistency
$where = " WHERE 1=1 ";
$params = [];
$types  = "";

// role restriction
if (strtolower($role) === 'shop' && $branchId > 0) {
    $where .= " AND s.branch_id = ? ";
    $types .= "i";
    $params[] = $branchId;
}

// search
if ($search !== '') {
    $where .= " AND (p.product_name LIKE ? OR b.branch_name LIKE ?) ";
    $types .= "ss";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// branch filter
if ($branch !== '') {
    $where .= " AND s.branch_id = ? ";
    $types .= "i";
    $params[] = (int)$branch;
}

// product filter
if ($product !== '') {
    $where .= " AND s.product_id = ? ";
    $types .= "i";
    $params[] = (int)$product;
}

$sql = "
SELECT
    b.branch_name,
    p.product_name,
    SUM(CASE WHEN s.status = 'active' THEN s.quantity ELSE 0 END) AS total_qty_sold,
    SUM(CASE WHEN s.status = 'active' THEN (s.quantity * s.unit_price) ELSE 0 END) AS total_sales_active,
    SUM(CASE WHEN s.status = 'returned' THEN (s.quantity * p.selling_price) ELSE 0 END) AS total_returned_sales,
    (SUM(CASE WHEN s.status = 'active' THEN (s.quantity * s.unit_price) ELSE 0 END)
     - SUM(CASE WHEN s.status = 'returned' THEN (s.quantity * p.selling_price) ELSE 0 END)
    ) AS net_sales,
    SUM(CASE WHEN s.status = 'active' THEN (s.quantity * p.cost_price) ELSE 0 END) AS total_cost_active,
    (
      (SUM(CASE WHEN s.status = 'active' THEN (s.quantity * s.unit_price) ELSE 0 END)
       - SUM(CASE WHEN s.status = 'returned' THEN (s.quantity * p.selling_price) ELSE 0 END)
      )
      - SUM(CASE WHEN s.status = 'active' THEN (s.quantity * p.cost_price) ELSE 0 END)
    ) AS profit
FROM Sales s
JOIN Products p ON s.product_id = p.product_id
JOIN Branches b ON s.branch_id = b.branch_id
$where
GROUP BY b.branch_name, p.product_name
";

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

// label helpers (same as before)
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

// totals for PDF
$totalQty       = 0;
$totalSalesAct  = 0;
$totalReturned  = 0;
$totalNetSales  = 0;
$totalCostAct   = 0;
$totalProfit    = 0;

foreach ($rows as $r) {
    $totalQty      += (int)$r['total_qty_sold'];
    $totalSalesAct += (float)$r['total_sales_active'];
    $totalReturned += (float)$r['total_returned_sales'];
    $totalNetSales += (float)$r['net_sales'];
    $totalCostAct  += (float)$r['total_cost_active'];
    $totalProfit   += (float)$r['profit'];
}

function peso($n) {
    return "₱" . number_format((float)$n, 2);
}

// Build PDF HTML
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
    <th class='right'>Returned Sales</th>
    <th class='right'>Net Sales</th>
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
            <td class='right'>" . (int)$r['total_qty_sold'] . "</td>
            <td class='right'>" . peso($r['total_sales_active']) . "</td>
            <td class='right'>" . peso($r['total_returned_sales']) . "</td>
            <td class='right'>" . peso($r['net_sales']) . "</td>
            <td class='right'>" . peso($r['total_cost_active']) . "</td>
            <td class='right {$profitClass}'>" . peso($profit) . "</td>
            <td>{$statusText}</td>
        </tr>";
    }
} else {
    $html .= "<tr><td colspan='9' style='text-align:center;'>No records found</td></tr>";
}

$html .= "
<tr class='total-row'>
    <td colspan='2'>TOTALS</td>
    <td class='right'>{$totalQty}</td>
    <td class='right'>" . peso($totalSalesAct) . "</td>
    <td class='right'>" . peso($totalReturned) . "</td>
    <td class='right'>" . peso($totalNetSales) . "</td>
    <td class='right'>" . peso($totalCostAct) . "</td>
    <td class='right'>" . peso($totalProfit) . "</td>
    <td></td>
</tr>
</table>
";

// Render PDF
$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("profitloss_report_" . date('Ymd_His') . ".pdf", ["Attachment" => true]);
exit;
