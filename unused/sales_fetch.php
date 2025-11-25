<?php
session_start();
require_once "../db_connection.php";

$role = strtolower($_SESSION['role'] ?? '');
$branchId = (int)($_SESSION['branch_id'] ?? 0);

$branchFilter = $_GET['branch_id'] ?? '';
$productFilter = $_GET['product_id'] ?? '';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$params = [];
$types = "";
$where = ["s.status != 'returned'"];

if ($role === 'shop' && $branchId > 0) {
    $where[] = "s.branch_id = ?";
    $params[] = $branchId;
    $types .= "i";
} elseif ($branchFilter !== '') {
    $where[] = "s.branch_id = ?";
    $params[] = $branchFilter;
    $types .= "i";
}

if ($productFilter !== '') {
    $where[] = "s.product_id = ?";
    $params[] = $productFilter;
    $types .= "i";
}

if ($fromDate !== '') {
    $where[] = "s.sale_date >= ?";
    $params[] = $fromDate;
    $types .= "s";
}

if ($toDate !== '') {
    $where[] = "s.sale_date <= ?";
    $params[] = $toDate;
    $types .= "s";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$countSql = "SELECT COUNT(*) as total FROM sales s $whereSql";
if ($params) {
    $stmt = $conn->prepare($countSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalRes = $stmt->get_result()->fetch_assoc();
} else {
    $totalRes = $conn->query($countSql)->fetch_assoc();
}
$totalRows = $totalRes['total'];
$totalPages = ceil($totalRows / $limit);

// Fetch paginated sales
$salesSql = "
    SELECT s.sale_id, s.sale_date, s.quantity, p.product_name, s.status,
           b.branch_name, s.unit_price, s.customer_type, (s.quantity * s.unit_price) AS line_total
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN branches b ON s.branch_id = b.branch_id
    $whereSql
    ORDER BY s.sale_date DESC, s.sale_id DESC
    LIMIT $limit OFFSET $offset
";

if ($params) {
    $stmt = $conn->prepare($salesSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $salesRes = $stmt->get_result();
} else {
    $salesRes = $conn->query($salesSql);
}

