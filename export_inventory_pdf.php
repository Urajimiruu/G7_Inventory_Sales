<?php
session_start();
require_once "db_connection.php";

// Dompdf
require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// GET params
$search  = trim($_GET['search'] ?? '');
$branch  = trim($_GET['branch'] ?? '');
$product = trim($_GET['product'] ?? '');
$sort    = trim($_GET['sort'] ?? '');

// BASE QUERY
$sql = "
SELECT 
    bi.branch_id,
    bi.product_id,
    bi.quantity AS qty,
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

$params = [];
$types  = "";

// role restriction
if (strtolower($role) === 'shop' && $branchId > 0) {
    $sql .= " AND bi.branch_id = ? ";
    $types .= "i";
    $params[] = $branchId;
}

// filters
if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ? OR b.branch_name LIKE ?)";
    $types .= "ss";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($branch !== '') {
    $sql .= " AND bi.branch_id = ? ";
    $types .= "i";
    $params[] = (int)$branch;
}

if ($product !== '') {
    $sql .= " AND bi.product_id = ? ";
    $types .= "i";
    $params[] = (int)$product;
}

// sorting
if ($sort === "asc") {
    $sql .= " ORDER BY bi.quantity ASC";
} elseif ($sort === "desc") {
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

// ===== Totals =====
$totalQty   = 0;
$totalCost  = 0;
$totalRev   = 0;

$rows = [];
while ($row = $result->fetch_assoc()) {
    $row['qty'] = (int)$row['qty'];
    $row['total_cost'] = (float)$row['total_cost'];
    $row['potential_revenue'] = (float)$row['potential_revenue'];

    $totalQty += $row['qty'];
    $totalCost += $row['total_cost'];
    $totalRev  += $row['potential_revenue'];

    $rows[] = $row;
}

$stmt->close();

// ===== helper functions =====
function peso($n) { return "₱" . number_format((float)$n, 2); }

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

// Sort text label
$sortLabel = "Default";
if ($sort === "asc") $sortLabel = "Lowest → Highest";
if ($sort === "desc") $sortLabel = "Highest → Lowest";

// ===== PDF TABLE BUILD =====
$html = "
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
h2 { text-align:center; margin-bottom:6px; }
.report-meta { text-align:center; margin-bottom:12px; font-size:10px; }
table { width:100%; border-collapse:collapse; }
th, td { border:1px solid #555; padding:5px 4px; }
th { background:#f2f2f2; font-weight:bold; }
.right { text-align:right; }
.total-row { background:#e8e8e8; font-weight:bold; }
</style>

<h2>Inventory Report</h2>
<div class='report-meta'>
    Branch: <strong>{$branchLabel}</strong><br>
    Product: <strong>{$productLabel}</strong><br>
    Sort: <strong>{$sortLabel}</strong><br>
    Generated: <strong>" . date('Y-m-d H:i:s') . "</strong>
</div>

<table>
<tr>
    <th>Branch</th>
    <th>Product</th>
    <th>Unit</th>
    <th class='right'>Cost Price</th>
    <th class='right'>Selling Price</th>
    <th class='right'>Quantity</th>
    <th class='right'>Total Cost</th>
    <th class='right'>Potential Revenue</th>
    <th>Status</th>
</tr>
";

if (count($rows) > 0) {
    foreach ($rows as $r) {
        $status = $r['qty'] === 0 ? "No Stock" : ($r['qty'] < 20 ? "Low Stock" : "On Stock");

        $html .= "<tr>
            <td>{$r['branch_name']}</td>
            <td>{$r['product_name']}</td>
            <td>{$r['unit']}</td>
            <td class='right'>" . peso($r['cost_price']) . "</td>
            <td class='right'>" . peso($r['selling_price']) . "</td>
            <td class='right'>{$r['qty']}</td>
            <td class='right'>" . peso($r['total_cost']) . "</td>
            <td class='right'>" . peso($r['potential_revenue']) . "</td>
            <td>{$status}</td>
        </tr>";
    }
} else {
    $html .= "<tr><td colspan='9' style='text-align:center;'>No inventory records found</td></tr>";
}

$html .= "
<tr class='total-row'>
    <td colspan='5'>TOTALS</td>
    <td class='right'>{$totalQty}</td>
    <td class='right'>" . peso($totalCost) . "</td>
    <td class='right'>" . peso($totalRev) . "</td>
    <td></td>
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
$dompdf->stream("inventory_report_" . date('Ymd_His') . ".pdf", ["Attachment" => true]);
exit;
