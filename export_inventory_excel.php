<?php
require_once "db_connection.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$search   = trim($_GET['search'] ?? '');
$filterBranch  = trim($_GET['branch'] ?? '');
$filterProduct = trim($_GET['product'] ?? '');
$sortStock     = trim($_GET['sort'] ?? '');

// Base query
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

$params = [];
$types  = "";

if (strtolower($role) === 'shop' && $branchId > 0) {
    $sql .= " AND bi.branch_id = ? ";
    $types .= "i";
    $params[] = $branchId;
}

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

// Build a dynamic filename
$filename = "inventory_report";

// Add branch to filename if filtered
if ($filterBranch !== '') {
    // Get branch name for clarity
    $bstmt = $conn->prepare("SELECT branch_name FROM branches WHERE branch_id = ?");
    $bstmt->bind_param("i", $filterBranch);
    $bstmt->execute();
    $bres = $bstmt->get_result();
    if ($brow = $bres->fetch_assoc()) {
        $branchNameSafe = preg_replace('/[^a-zA-Z0-9_-]/', '', $brow['branch_name']);
        $filename .= "_branch_{$branchNameSafe}";
    }
    $bstmt->close();
}

// Add product to filename if filtered
if ($filterProduct !== '') {
    // Get product name
    $pstmt = $conn->prepare("SELECT product_name FROM products WHERE product_id = ?");
    $pstmt->bind_param("i", $filterProduct);
    $pstmt->execute();
    $pres = $pstmt->get_result();
    if ($prow = $pres->fetch_assoc()) {
        $productNameSafe = preg_replace('/[^a-zA-Z0-9_-]/', '', $prow['product_name']);
        $filename .= "_product_{$productNameSafe}";
    }
    $pstmt->close();
}

// Add sort info to filename if set
if ($sortStock === "asc") {
    $filename .= "_sorted_asc";
} elseif ($sortStock === "desc") {
    $filename .= "_sorted_desc";
}

// Add timestamp
$filename .= "_" . date('Ymd_His') . ".xls";

// Headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename={$filename}");
header("Pragma: no-cache");
header("Expires: 0");

// Column headers
echo "Branch\tProduct\tUnit\tCost Price\tSelling Price\tQuantity\tTotal Cost\tPotential Revenue\tStatus\n";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $branchName   = $row['branch_name'];
        $productName  = $row['product_name'];
        $unit         = $row['unit'];
        $costPrice    = number_format((float)$row['cost_price'], 2);
        $sellingPrice = number_format((float)$row['selling_price'], 2);
        $qty          = (int)$row['quantity'];
        $totalCost    = number_format((float)$row['total_cost'], 2);
        $revenue      = number_format((float)$row['potential_revenue'], 2);

        // Stock status
        if ($qty === 0) {
            $statusText = 'No Stock';
        } elseif ($qty < 20) {
            $statusText = 'Low Stock';
        } else {
            $statusText = 'On Stock';
        }

        echo "{$branchName}\t{$productName}\t{$unit}\t{$costPrice}\t{$sellingPrice}\t{$qty}\t{$totalCost}\t{$revenue}\t{$statusText}\n";
    }
} else {
    echo "No data found\n";
}

$conn->close();
exit;
?>
