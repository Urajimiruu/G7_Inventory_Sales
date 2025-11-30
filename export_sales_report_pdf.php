<?php
session_start();
require_once "db_connection.php";

// ===== Dompdf setup =====
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

// ===== ALWAYS exclude returned sales =====
$whereClauses = " WHERE s.status = 'active' ";

// ===== Role-based branch restriction (shop role) =====
if (strtolower($role) === 'shop') {
    $whereClauses .= " AND s.branch_id = ? ";
    $types  .= "i";
    $params[] = $branchId;
}

// ===== Filters =====
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

// =====================================================================================
// MAIN SQL (UPDATED TO USE s.unit_price)
// =====================================================================================
if ($view === 'detailed') {

    $sql = "
        SELECT 
            s.sale_date,
            b.branch_name,
            p.product_name,
            s.quantity AS qty,
            s.unit_price,                 -- actual sale price (possibly discounted)
            p.cost_price,
            (s.quantity * s.unit_price) AS total_sales,
            (s.quantity * p.cost_price) AS total_cost,
            ((s.unit_price - p.cost_price) * s.quantity) AS profit
        FROM Sales s
        JOIN Products p ON s.product_id = p.product_id
        JOIN Branches b ON s.branch_id = b.branch_id
        $whereClauses
    ";

    switch ($sort) {
        case 'date_asc':  $sql .= " ORDER BY s.sale_date ASC, p.product_name ASC"; break;
        case 'total_sales_desc': $sql .= " ORDER BY total_sales DESC"; break;
        case 'profit_desc':      $sql .= " ORDER BY profit DESC"; break;
        default: $sql .= " ORDER BY s.sale_date DESC, p.product_name ASC";
    }

} else {
    // ===== SUMMARY VIEW =====

    if ($group === 'daily') {
        $sql = "
            SELECT
                s.sale_date,
                b.branch_name,
                p.product_name,
                SUM(s.quantity) AS qty,
                SUM(s.quantity * s.unit_price) AS total_sales,
                SUM(s.quantity * p.cost_price) AS total_cost,
                SUM((s.unit_price - p.cost_price) * s.quantity) AS profit
            FROM Sales s
            JOIN Products p ON s.product_id = p.product_id
            JOIN Branches b ON s.branch_id = b.branch_id
            $whereClauses
            GROUP BY s.sale_date, b.branch_name, p.product_name
        ";

        switch ($sort) {
            case 'date_asc':  $sql .= " ORDER BY s.sale_date ASC"; break;
            case 'total_sales_desc': $sql .= " ORDER BY total_sales DESC"; break;
            case 'profit_desc':      $sql .= " ORDER BY profit DESC"; break;
            default: $sql .= " ORDER BY s.sale_date DESC";
        }

    } else {
        $sql = "
            SELECT
                b.branch_name,
                p.product_name,
                SUM(s.quantity) AS qty,
                SUM(s.quantity * s.unit_price) AS total_sales,
                SUM(s.quantity * p.cost_price) AS total_cost,
                SUM((s.unit_price - p.cost_price) * s.quantity) AS profit
            FROM Sales s
            JOIN Products p ON s.product_id = p.product_id
            JOIN Branches b ON s.branch_id = b.branch_id
            $whereClauses
            GROUP BY b.branch_name, p.product_name
        ";

        switch ($sort) {
            case 'total_sales_desc': $sql .= " ORDER BY total_sales DESC"; break;
            case 'profit_desc':      $sql .= " ORDER BY profit DESC"; break;
            default: $sql .= " ORDER BY b.branch_name, p.product_name";
        }
    }
}

// ===== EXECUTE =====
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $row['qty']         = (int)$row['qty'];
    $row['total_sales'] = (float)$row['total_sales'];
    $row['total_cost']  = (float)$row['total_cost'];
    $row['profit']      = (float)$row['profit'];
    $rows[] = $row;
}
$stmt->close();

// =====================================================================================
// LABELS
// =====================================================================================
function getBranchLabel($conn, $id) {
    if (!$id) return "All Branches";
    $name = null;
    $s = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ?");
    $s->bind_param("i", $id);
    $s->execute();
    $s->bind_result($name);
    $s->fetch();
    $s->close();
    return $name ?: "All Branches";
}

function getProductLabel($conn, $id) {
    if (!$id) return "All Products";
    $name = null;
    $s = $conn->prepare("SELECT product_name FROM products WHERE product_id = ?");
    $s->bind_param("i", $id);
    $s->execute();
    $s->bind_result($name);
    $s->fetch();
    $s->close();
    return $name ?: "All Products";
}

$branchLabel  = getBranchLabel($conn, (int)$branch);
$productLabel = getProductLabel($conn, (int)$product);

$dateLabel = ($from || $to)
    ? ($from ?: "Start") . " to " . ($to ?: "Present")
    : "All Dates";

// =====================================================================================
// TOTALS
// =====================================================================================
$totalQty = $totalSales = $totalCost = $totalProfit = 0;

foreach ($rows as $r) {
    $totalQty    += $r['qty'];
    $totalSales  += $r['total_sales'];
    $totalCost   += $r['total_cost'];
    $totalProfit += $r['profit'];
}

function peso($n) { return "₱" . number_format($n, 2); }

// =====================================================================================
// PDF HTML
// =====================================================================================
$hasDateColumn =
    ($view === 'detailed') ||
    ($view === 'summary' && $group === 'daily');

$headerCols = "
    <th>Branch</th>
    <th>Product</th>
";
if ($hasDateColumn) $headerCols .= "<th>Date</th>";
$headerCols .= "
    <th class='right'>Qty</th>
    <th class='right'>Total Sales</th>
    <th class='right'>Total Cost</th>
    <th class='right'>Profit</th>
";

$colspanForTotal = $hasDateColumn ? 3 : 2;

$html = "
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
h2 { text-align:center; margin-bottom:6px;}
.report-meta { text-align:center; margin-bottom:12px; font-size:10px; }
table { width:100%; border-collapse: collapse; }
th, td { border:1px solid #555; padding:4px; }
th { background:#f2f2f2; }
.right { text-align:right; }
.total-row { background:#e8e8e8; font-weight:bold; }
</style>

<h2>Sales Report</h2>
<div class='report-meta'>
    Branch: <strong>{$branchLabel}</strong><br>
    Product: <strong>{$productLabel}</strong><br>
    Date Range: <strong>{$dateLabel}</strong><br>
    Generated: <strong>" . date('Y-m-d H:i:s') . "</strong>
</div>

<table>
    <tr>{$headerCols}</tr>
";

if (count($rows)) {
    foreach ($rows as $r) {
        $html .= "<tr>";
        $html .= "<td>" . htmlspecialchars($r['branch_name']) . "</td>";
        $html .= "<td>" . htmlspecialchars($r['product_name']) . "</td>";
        if ($hasDateColumn) $html .= "<td>" . htmlspecialchars($r['sale_date']) . "</td>";
        $html .= "<td class='right'>{$r['qty']}</td>";
        $html .= "<td class='right'>" . peso($r['total_sales']) . "</td>";
        $html .= "<td class='right'>" . peso($r['total_cost']) . "</td>";
        $html .= "<td class='right'>" . peso($r['profit']) . "</td>";
        $html .= "</tr>";
    }
} else {
    $html .= "<tr><td colspan='" . ($colspanForTotal + 4) . "' style='text-align:center;'>No results</td></tr>";
}

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

// ==== Render PDF ====
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("sales_report_" . date('Ymd_His') . ".pdf", ["Attachment" => true]);
exit;
