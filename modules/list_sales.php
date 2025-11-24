<?php
session_start();
require_once "../db_connection.php";

header("Content-Type: text/html");

// Protect access
if (!isset($_SESSION['user_id'])) {
    echo "<tr><td colspan='8' style='text-align:center;'>Unauthorized</td></tr>";
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
        s.unit_price,
        s.customer_type,
        (s.quantity * s.unit_price) AS line_total,
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
    echo "<tr><td colspan='8' style='text-align:center;'>No sales found.</td></tr>";
    exit;
}

while ($r = $res->fetch_assoc()) {
    $hasDiscount = $r['customer_type'] !== 'Regular';
    $originalUnitPrice = $hasDiscount ? $r['unit_price'] / 0.8 : $r['unit_price'];
    
    echo "
    <tr>
        <td>{$r['sale_date']}</td>
        <td class='muted'>{$r['product_name']}</td>
        <td>{$r['branch_name']}</td>
        <td class='right'>{$r['quantity']}</td>
        <td class='right'>";
    
    // Show unit price with discount indication
    if ($hasDiscount) {
        echo "<span style='text-decoration: line-through; color: #999; font-size: 0.9em;'>₱" . number_format($originalUnitPrice, 2) . "</span><br>
              <span style='color: #e74c3c; font-weight: bold;'>₱" . number_format($r['unit_price'], 2) . "</span>";
    } else {
        echo "₱" . number_format($r['unit_price'], 2);
    }
    
    echo "</td>
        <td class='right'>₱" . number_format($r['line_total'], 2) . "</td>
        <td>";
    
    // Display customer type with color coding
    switch ($r['customer_type']) {
        case 'Senior':
            echo "<span style='color: #e67e22; font-weight: bold;'>Senior</span>";
            break;
        case 'PWD':
            echo "<span style='color: #9b59b6; font-weight: bold;'>PWD</span>";
            break;
        default:
            echo "Regular";
    }
    
    echo "</td>
        <td>
            <button class='btn btn-warning btn-sm' onclick='openEditSaleModal({$r['sale_id']})'>Edit</button>
            <button class='btn btn-danger btn-sm' onclick='deleteSale({$r['sale_id']})'>Delete</button>
            <button class='btn btn-primary btn-sm' onclick='returnSale({$r['sale_id']})'>Return</button>
        </td>
    </tr>";
}

$stmt->close();
?>