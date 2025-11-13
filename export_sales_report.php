<?php
session_start();
require_once "db_connection.php";

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// GET params
$view    = $_GET['view'] ?? 'detailed';
$branch  = $_GET['branch'] ?? '';
$product = $_GET['product'] ?? '';
$from    = $_GET['from'] ?? '';
$to      = $_GET['to'] ?? '';
$sort    = $_GET['sort'] ?? 'date_desc';
$group   = $_GET['group'] ?? 'none';
$search  = trim($_GET['search'] ?? '');

$params = [];
$types  = "";
$whereClauses = " WHERE 1=1 ";

// role-based restriction
if (strtolower($role) === 'shop') {
    $whereClauses .= " AND s.branch_id = ? ";
    $types .= "i";
    $params[] = $branchId;
}

// filters
if (!empty($branch)) {
    $whereClauses .= " AND s.branch_id = ? ";
    $types .= "i";
    $params[] = (int)$branch;
}
if (!empty($product)) {
    $whereClauses .= " AND s.product_id = ? ";
    $types .= "i";
    $params[] = (int)$product;
}
if (!empty($from)) {
    $whereClauses .= " AND s.sale_date >= ? ";
    $types .= "s";
    $params[] = $from;
}
if (!empty($to)) {
    $whereClauses .= " AND s.sale_date <= ? ";
    $types .= "s";
    $params[] = $to;
}
if (!empty($search)) {
    $whereClauses .= " AND (p.product_name LIKE ? OR b.branch_name LIKE ?) ";
    $types .= "ss";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Build SQL
if ($view === 'detailed') {
    $sql = "
    SELECT 
      s.sale_date,
      b.branch_name,
      p.product_name,
      s.quantity,
      p.selling_price,
      (s.quantity * p.selling_price) AS total_sales,
      (s.quantity * p.cost_price) AS total_cost,
      ((p.selling_price - p.cost_price) * s.quantity) AS profit
    FROM Sales s
    JOIN Products p ON s.product_id = p.product_id
    JOIN Branches b ON s.branch_id = b.branch_id
    " . $whereClauses;
    
    switch ($sort) {
        case 'date_asc': $sql .= " ORDER BY s.sale_date ASC, p.product_name ASC"; break;
        case 'total_sales_desc': $sql .= " ORDER BY total_sales DESC"; break;
        case 'profit_desc': $sql .= " ORDER BY profit DESC"; break;
        case 'date_desc':
        default: $sql .= " ORDER BY s.sale_date DESC, p.product_name ASC";
    }

} else {
    // summary view
    if ($group === 'daily') {
        $sql = "
        SELECT
          s.sale_date,
          b.branch_name,
          p.product_name,
          SUM(s.quantity) AS total_qty,
          SUM(s.quantity * p.selling_price) AS total_sales,
          SUM(s.quantity * p.cost_price) AS total_cost,
          SUM((p.selling_price - p.cost_price) * s.quantity) AS profit
        FROM Sales s
        JOIN Products p ON s.product_id = p.product_id
        JOIN Branches b ON s.branch_id = b.branch_id
        " . $whereClauses . "
        GROUP BY s.sale_date, b.branch_name, p.product_name";
        
        switch ($sort) {
            case 'date_asc': $sql .= " ORDER BY s.sale_date ASC, b.branch_name, p.product_name"; break;
            case 'total_sales_desc': $sql .= " ORDER BY total_sales DESC"; break;
            case 'profit_desc': $sql .= " ORDER BY profit DESC"; break;
            default: $sql .= " ORDER BY s.sale_date DESC, b.branch_name, p.product_name";
        }

    } else {
        $sql = "
        SELECT
          b.branch_name,
          p.product_name,
          SUM(s.quantity) AS total_qty,
          SUM(s.quantity * p.selling_price) AS total_sales,
          SUM(s.quantity * p.cost_price) AS total_cost,
          SUM((p.selling_price - p.cost_price) * s.quantity) AS profit
        FROM Sales s
        JOIN Products p ON s.product_id = p.product_id
        JOIN Branches b ON s.branch_id = b.branch_id
        " . $whereClauses . "
        GROUP BY b.branch_name, p.product_name";

        switch ($sort) {
            case 'total_sales_desc': $sql .= " ORDER BY total_sales DESC"; break;
            case 'profit_desc': $sql .= " ORDER BY profit DESC"; break;
            default: $sql .= " ORDER BY b.branch_name, p.product_name";
        }
    }
}

// prepare and execute
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// --- dynamic filename using actual names and date range ---
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

// readable sort name
$sortMap = [
    'date_desc'       => 'Newest',
    'date_asc'        => 'Oldest',
    'total_sales_desc'=> 'HighestSales',
    'profit_desc'     => 'HighestProfit'
];
$sortName = $sortMap[$sort] ?? 'Sorted';

$filename = "sales_report";
if ($branchNameForFile)  $filename .= "_branch{$branchNameForFile}";
if ($productNameForFile) $filename .= "_product{$productNameForFile}";
if (!empty($from))        $filename .= "_from" . str_replace('-', '', $from);
if (!empty($to))          $filename .= "_to" . str_replace('-', '', $to);
if ($sortName)            $filename .= "_sort{$sortName}";
$filename .= "_" . date('Ymd_His') . ".xls";

// headers
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename={$filename}");
header("Pragma: no-cache");
header("Expires: 0");

// table output
echo "<table border='1'>";
echo "<tr>";
if ($view === 'detailed') {
    echo "<th>Branch</th><th>Product</th><th>Date</th><th>Qty</th><th>Selling Price</th><th>Total Sales</th><th>Total Cost</th><th>Profit</th>";
} else {
    echo "<th>Branch</th><th>Product</th><th>Total Qty</th><th>Total Sales</th><th>Total Cost</th><th>Profit</th>";
    if ($group === 'daily') echo "<th>Date</th>";
}
echo "</tr>";

if ($result && $result->num_rows > 0) {
    while ($r = $result->fetch_assoc()) {
        echo "<tr>";
        if ($view === 'detailed') {
            echo "<td>{$r['branch_name']}</td>";
            echo "<td>{$r['product_name']}</td>";
            echo "<td>{$r['sale_date']}</td>";
            echo "<td>{$r['quantity']}</td>";
            echo "<td>{$r['selling_price']}</td>";
            echo "<td>{$r['total_sales']}</td>";
            echo "<td>{$r['total_cost']}</td>";
            echo "<td>{$r['profit']}</td>";
        } else {
            echo "<td>{$r['branch_name']}</td>";
            echo "<td>{$r['product_name']}</td>";
            echo "<td>{$r['total_qty']}</td>";
            echo "<td>{$r['total_sales']}</td>";
            echo "<td>{$r['total_cost']}</td>";
            echo "<td>{$r['profit']}</td>";
            if ($group === 'daily') echo "<td>{$r['sale_date']}</td>";
        }
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='8' style='text-align:center;'>No sales found</td></tr>";
}

$stmt->close();
$conn->close();
?>
