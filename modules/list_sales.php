<?php
session_start();
require_once "../db_connection.php";

header("Content-Type: text/html");

// Protect access
if (!isset($_SESSION['user_id'])) {
    echo "<tr><td colspan='8' style='text-align:center;'>Unauthorized</td></tr>";
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');
$branchId = (int)($_SESSION['branch_id'] ?? 0);

$productId = $_GET['product_id'] ?? '';
$filterBranch = $_GET['branch_id'] ?? '';
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50; // items per page
$offset = ($page - 1) * $limit;

// Base SQL
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

// ---------------- COUNT TOTAL ----------------
$countSql = "SELECT COUNT(*) FROM ($sql) AS temp";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalItems = $countStmt->get_result()->fetch_row()[0];
$totalPages = ceil($totalItems / $limit);
$countStmt->close();

// ---------------- ADD ORDER AND LIMIT ----------------
$sql .= " ORDER BY s.sale_date DESC, s.sale_id DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
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
    
    if ($hasDiscount) {
        echo "<span style='text-decoration: line-through; color: #999; font-size: 0.9em;'>₱" . number_format($originalUnitPrice, 2) . "</span><br>
              <span style='color: #e74c3c; font-weight: bold;'>₱" . number_format($r['unit_price'], 2) . "</span>";
    } else {
        echo "₱" . number_format($r['unit_price'], 2);
    }
    
    echo "</td>
        <td class='right'>₱" . number_format($r['line_total'], 2) . "</td>
        <td>";
    
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

// ---------------- PAGINATION ----------------
echo "<tr><td colspan='8' style='text-align:center;'>";

if ($totalPages > 1) {
    // Prev button
    if ($page > 1) {
        echo "<button class='btn btn-primary' onclick='loadSales(".($page-1).")'>Prev</button> ";
    }

    // Numbered buttons (windowed)
    $windowSize = 2; // show 2 pages before and after current
    $start = max(1, $page - $windowSize);
    $end   = min($totalPages, $page + $windowSize);

    if ($start > 1) {
        echo "<button class='btn btn-primary' onclick='loadSales(1)'>1</button> ";
        if ($start > 2) echo "<span>...</span> ";
    }

    for ($i = $start; $i <= $end; $i++) {
        $btnClass = ($i === $page) ? "btn btn-warning" : "btn btn-primary";
        echo "<button class='$btnClass' onclick='loadSales($i)'>$i</button> ";
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) echo "<span>...</span> ";
        echo "<button class='btn btn-primary' onclick='loadSales($totalPages)'>$totalPages</button> ";
    }

    // Next button
    if ($page < $totalPages) {
        echo "<button class='btn btn-primary' onclick='loadSales(".($page+1).")'>Next</button>";
    }
}

echo "</td></tr>";

?>
