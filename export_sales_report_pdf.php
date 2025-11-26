<?php
session_start();
require_once "db_connection.php";

// ===== Dompdf setup (requires Composer: composer require dompdf/dompdf) =====
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// GET params (same as report_sales.php)
$view    = $_GET['view']   ?? 'detailed';
$branch  = $_GET['branch'] ?? '';
$product = $_GET['product']?? '';
$from    = $_GET['from']   ?? '';
$to      = $_GET['to']     ?? '';
$sort    = $_GET['sort']   ?? 'date_desc';
$group   = $_GET['group']  ?? 'none';
$search  = trim($_GET['search'] ?? '');

$params = [];
$types  = "";
$whereClauses = " WHERE 1=1 ";

// --- role-based restriction (shop only sees its branch) ---
if (strtolower($role) === 'shop') {
    $whereClauses .= " AND s.branch_id = ? ";
    $types  .= "i";
    $params[] = $branchId;
}

// --- filters ---
if ($branch !== '') {
    $whereClauses .= " AND s.branch_id = ? ";
    $types  .= "i";
    $params[] = (int)$branch;
}

if ($product !== '') {
    $whereClauses .= " AND s.product_id = ? ";
    $types  .= "i";
    $params[] = (int)$product;
}

if ($from !== '') {
    $whereClauses .= " AND s.sale_date >= ? ";
    $types  .= "s";
    $params[] = $from;
}

if ($to !== '') {
    $whereClauses .= " AND s.sale_date <= ? ";
    $types  .= "s";
    $params[] = $to;
}

if ($search !== '') {
    $whereClauses .= " AND (p.product_name LIKE ? OR b.branch_name LIKE ?) ";
    $types  .= "ss";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ===== Build SQL (we normalize quantity alias to `qty` everywhere) =====
if ($view === 'detailed') {
    // one row per sale transaction
    $sql = "
        SELECT 
            s.sale_date,
            b.branch_name,
            p.product_name,
            s.quantity AS qty,
            p.selling_price,
            (s.quantity * p.selling_price) AS total_sales,
            (s.quantity * p.cost_price)    AS total_cost,
            ((p.selling_price - p.cost_price) * s.quantity) AS profit
        FROM Sales s
        JOIN Products p ON s.product_id = p.product_id
        JOIN Branches b ON s.branch_id = b.branch_id
        $whereClauses
    ";

    switch ($sort) {
        case 'date_asc':
            $sql .= " ORDER BY s.sale_date ASC, p.product_name ASC";
            break;
        case 'total_sales_desc':
            $sql .= " ORDER BY total_sales DESC";
            break;
        case 'profit_desc':
            $sql .= " ORDER BY profit DESC";
            break;
        case 'date_desc':
        default:
            $sql .= " ORDER BY s.sale_date DESC, p.product_name ASC";
            break;
    }

} else {
    // SUMMARY VIEW
    if ($group === 'daily') {
        // grouped per DATE + branch + product
        $sql = "
            SELECT
                s.sale_date,
                b.branch_name,
                p.product_name,
                SUM(s.quantity) AS qty,
                SUM(s.quantity * p.selling_price) AS total_sales,
                SUM(s.quantity * p.cost_price)    AS total_cost,
                SUM((p.selling_price - p.cost_price) * s.quantity) AS profit
            FROM Sales s
            JOIN Products p ON s.product_id = p.product_id
            JOIN Branches b ON s.branch_id = b.branch_id
            $whereClauses
            GROUP BY s.sale_date, b.branch_name, p.product_name
        ";

        switch ($sort) {
            case 'date_asc':
                $sql .= " ORDER BY s.sale_date ASC, b.branch_name, p.product_name";
                break;
            case 'total_sales_desc':
                $sql .= " ORDER BY total_sales DESC";
                break;
            case 'profit_desc':
                $sql .= " ORDER BY profit DESC";
                break;
            default:
                $sql .= " ORDER BY s.sale_date DESC, b.branch_name, p.product_name";
                break;
        }

    } else {
        // grouped per branch + product (no date column)
        $sql = "
            SELECT
                b.branch_name,
                p.product_name,
                SUM(s.quantity) AS qty,
                SUM(s.quantity * p.selling_price) AS total_sales,
                SUM(s.quantity * p.cost_price)    AS total_cost,
                SUM((p.selling_price - p.cost_price) * s.quantity) AS profit
            FROM Sales s
            JOIN Products p ON s.product_id = p.product_id
            JOIN Branches b ON s.branch_id = b.branch_id
            $whereClauses
            GROUP BY b.branch_name, p.product_name
        ";

        switch ($sort) {
            case 'total_sales_desc':
                $sql .= " ORDER BY total_sales DESC";
                break;
            case 'profit_desc':
                $sql .= " ORDER BY profit DESC";
                break;
            default:
                $sql .= " ORDER BY b.branch_name, p.product_name";
                break;
        }
    }
}

// ===== Execute query =====
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    // normalize numerics
    $row['qty']         = isset($row['qty']) ? (int)$row['qty'] : 0;
    $row['total_sales'] = isset($row['total_sales']) ? (float)$row['total_sales'] : 0;
    $row['total_cost']  = isset($row['total_cost']) ? (float)$row['total_cost'] : 0;
    $row['profit']      = isset($row['profit']) ? (float)$row['profit'] : 0;
    $rows[] = $row;
}
$stmt->close();

// ===== Helper: labels for header =====
function getBranchLabel($conn, $id) {
    if (!$id) return "All Branches";

    $name = null; // ✅ prevent undefined variable warning
    $stmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($name);
    $stmt->fetch();
    $stmt->close();

    return !empty($name) ? $name : "All Branches";
}

function getProductLabel($conn, $id) {
    if (!$id) return "All Products";

    $name = null; // ✅ prevent undefined variable warning
    $stmt = $conn->prepare("SELECT product_name FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($name);
    $stmt->fetch();
    $stmt->close();

    return !empty($name) ? $name : "All Products";
}


$branchLabel  = getBranchLabel($conn, (int)$branch);
$productLabel = getProductLabel($conn, (int)$product);

if ($from !== '' || $to !== '') {
    $dateLabel = ($from ?: "Start") . " to " . ($to ?: "Present");
} else {
    $dateLabel = "All Dates";
}

// ===== Totals =====
$totalQty     = 0;
$totalSales   = 0;
$totalCost    = 0;
$totalProfit  = 0;

foreach ($rows as $r) {
    $totalQty    += $r['qty'];
    $totalSales  += $r['total_sales'];
    $totalCost   += $r['total_cost'];
    $totalProfit += $r['profit'];
}

// ===== Peso formatter =====
function peso($n) {
    return "₱" . number_format((float)$n, 2);
}

// ===== Build HTML for PDF =====
$hasDateColumn =
    ($view === 'detailed') ||
    ($view === 'summary' && $group === 'daily');

$headerCols = "";
$headerCols .= "<th>Branch</th>";
$headerCols .= "<th>Product</th>";
if ($hasDateColumn) {
    $headerCols .= "<th>Date</th>";
}
$headerCols .= "<th class='right'>Qty</th>";
$headerCols .= "<th class='right'>Total Sales</th>";
$headerCols .= "<th class='right'>Total Cost</th>";
$headerCols .= "<th class='right'>Profit</th>";

$colspanForTotal = $hasDateColumn ? 3 : 2;

$html = "
<style>
body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
}
h2 {
    text-align: center;
    margin-bottom: 4px;
}
.report-meta {
    text-align: center;
    margin-bottom: 12px;
    font-size: 10px;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    border: 1px solid #555;
    padding: 5px 4px;
}
th {
    background: #f2f2f2;
    font-weight: bold;
}
.right {
    text-align: right;
}
.total-row {
    background: #e8e8e8;
    font-weight: bold;
}
</style>

<h2>Sales Report</h2>
<div class='report-meta'>
    Branch: <strong>{$branchLabel}</strong><br>
    Product: <strong>{$productLabel}</strong><br>
    Date Range: <strong>{$dateLabel}</strong><br>
    Generated: <strong>" . date('Y-m-d H:i:s') . "</strong>
</div>

<table>
    <tr>
        {$headerCols}
    </tr>
";

if (count($rows) > 0) {
    foreach ($rows as $r) {
        $html .= "<tr>";
        $html .= "<td>" . htmlspecialchars($r['branch_name']) . "</td>";
        $html .= "<td>" . htmlspecialchars($r['product_name']) . "</td>";
        if ($hasDateColumn) {
            $saleDate = $r['sale_date'] ?? '';
            $html .= "<td>" . htmlspecialchars($saleDate) . "</td>";
        }
        $html .= "<td class='right'>" . $r['qty'] . "</td>";
        $html .= "<td class='right'>" . peso($r['total_sales']) . "</td>";
        $html .= "<td class='right'>" . peso($r['total_cost']) . "</td>";
        $html .= "<td class='right'>" . peso($r['profit']) . "</td>";
        $html .= "</tr>";
    }
} else {
    $html .= "<tr><td colspan='" . ($colspanForTotal + 4) . "' style='text-align:center;'>No records found</td></tr>";
}

// totals row
$html .= "
<tr class='total-row'>
    <td colspan='{$colspanForTotal}'>TOTALS</td>
    <td class='right'>{$totalQty}</td>
    <td class='right'>" . peso($totalSales) . "</td>
    <td class='right'>" . peso($totalCost) . "</td>
    <td class='right'>" . peso($totalProfit) . "</td>
</tr>
</table>
";

// ===== Render PDF =====
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("sales_report_" . date('Ymd_His') . ".pdf", ["Attachment" => true]);
exit;
