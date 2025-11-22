<?php
session_start();
require_once "../db_connection.php";

header("Content-Type: text/html");

// Protect access
if (!isset($_SESSION['user_id'])) {
    echo "<tr><td colspan='7' style='text-align:center;'>Unauthorized</td></tr>";
    exit;
}

$role = strtolower($_SESSION['role']);
$branchId = (int)($_SESSION['branch_id'] ?? 0);

$productId = $_GET['product_id'] ?? '';
$filterBranch = $_GET['branch_id'] ?? '';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

$sql = "
    SELECT 
        s.sale_id,
        s.sale_date,
        s.quantity,
        p.product_name,
        p.selling_price,
        (s.quantity * p.selling_price) AS total,
        b.branch_name
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN branches b ON s.branch_id = b.branch_id
    WHERE s.status = 'active'
";

$params = [];
$types = "";

// SHOP USERS → force their own branch
if ($role === 'shop' && $branchId > 0) {
    $sql .= " AND s.branch_id = ? ";
    $params[] = $branchId;
    $types   .= "i";
}

// Admin branch filter
if ($role === 'admin' && $filterBranch !== "") {
    $sql .= " AND s.branch_id = ? ";
    $params[] = $filterBranch;
    $types   .= "i";
}

// Product filter
if ($productId !== "") {
    $sql .= " AND s.product_id = ? ";
    $params[] = $productId;
    $types   .= "i";
}

// Date range filter
if ($fromDate !== "") {
    $sql .= " AND s.sale_date >= ? ";
    $params[] = $fromDate;
    $types   .= "s";
}

if ($toDate !== "") {
    $sql .= " AND s.sale_date <= ? ";
    $params[] = $toDate;
    $types   .= "s";
}

$sql .= " ORDER BY s.sale_date DESC, s.sale_id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "<tr><td colspan='7' style='text-align:center;'>No sales found.</td></tr>";
    exit;
}

while ($r = $res->fetch_assoc()) {
    echo "
    <tr>
        <td>{$r['sale_date']}</td>
        <td class='muted'>{$r['product_name']}</td>
        <td>{$r['branch_name']}</td>
        <td class='right'>{$r['quantity']}</td>
        <td class='right'>₱".number_format($r['selling_price'],2)."</td>
        <td class='right'>₱".number_format($r['total'],2)."</td>
        <td>
            <button class='btn btn-warning btn-sm' onclick='openEditSaleModal({$r['sale_id']})'>Edit</button>
            <button class='btn btn-danger btn-sm' onclick='deleteSale({$r['sale_id']})'>Delete</button>
            <button class='btn btn-primary btn-sm' onclick='returnSale({$r['sale_id']})'>Return</button>
        </td>
    </tr>";
}

