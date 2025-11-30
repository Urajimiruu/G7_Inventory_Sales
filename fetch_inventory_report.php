<?php
require_once "db_connection.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<tr><td colspan='10' style='text-align:center;'>Unauthorized</td></tr><!--PAGINATION-->";
    exit;
}

$role          = $_SESSION['role'] ?? '';
$branchId      = (int)($_SESSION['branch_id'] ?? 0);
$search        = trim($_GET['search'] ?? '');
$filterBranch  = trim($_GET['branch'] ?? '');
$filterProduct = trim($_GET['product'] ?? '');
$sortStock     = trim($_GET['sort'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$limit         = (int)($_GET['limit'] ?? 20);
$offset        = ($page - 1) * $limit;

/* ------------------------------------------
   1. BUILD FILTER CONDITIONS
------------------------------------------ */
$where = " WHERE 1 ";
$params = [];
$types = "";

// shop restriction
if (strtolower($role) === 'shop' && $branchId > 0) {
    $where .= " AND bi.branch_id = ? ";
    $types .= "i";
    $params[] = $branchId;
}

// search filter
if ($search !== '') {
    $where .= " AND (p.product_name LIKE ? OR b.branch_name LIKE ?) ";
    $types .= "ss";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// explicit branch/product filters
if ($filterBranch !== '') {
    $where .= " AND bi.branch_id = ? ";
    $types .= "i";
    $params[] = (int)$filterBranch;
}

if ($filterProduct !== '') {
    $where .= " AND bi.product_id = ? ";
    $types .= "i";
    $params[] = (int)$filterProduct;
}

/* ------------------------------------------
   2. TOTAL ROW COUNT
------------------------------------------ */
$countSql = "
    SELECT COUNT(*) AS total
    FROM branchinventory bi
    JOIN products p ON bi.product_id = p.product_id
    JOIN branches b ON bi.branch_id = b.branch_id
    $where
";

$stmtCount = $conn->prepare($countSql);
if (!empty($params)) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRows = $stmtCount->get_result()->fetch_assoc()['total'] ?? 0;
$stmtCount->close();

$totalPages = max(1, ceil($totalRows / $limit));

/* ------------------------------------------
   3. GRAND TOTALS QUERY (NO LIMIT!)
------------------------------------------ */
$totalSql = "
    SELECT 
        SUM(bi.quantity) AS totalQty,
        SUM(bi.quantity * p.cost_price) AS totalCost,
        SUM(bi.quantity * p.selling_price) AS totalRevenue
    FROM branchinventory bi
    JOIN products p ON bi.product_id = p.product_id
    JOIN branches b ON bi.branch_id = b.branch_id
    $where
";

$stmtTotal = $conn->prepare($totalSql);
if (!empty($params)) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totals = $stmtTotal->get_result()->fetch_assoc();
$stmtTotal->close();

$grandQty     = (int)($totals['totalQty'] ?? 0);
$grandCost    = (float)($totals['totalCost'] ?? 0);
$grandRevenue = (float)($totals['totalRevenue'] ?? 0);

/* ------------------------------------------
   4. MAIN PAGINATED QUERY
------------------------------------------ */
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
$where
";

if ($sortStock === "asc") {
    $sql .= " ORDER BY bi.quantity ASC ";
} elseif ($sortStock === "desc") {
    $sql .= " ORDER BY bi.quantity DESC ";
} else {
    $sql .= " ORDER BY b.branch_name, p.product_name ";
}

$sql .= " LIMIT ?, ? ";

$types2 = $types . "ii";
$params2 = array_merge($params, [$offset, $limit]);

$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();

/* ------------------------------------------
   5. OUTPUT TABLE ROWS
------------------------------------------ */

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        $branchName   = htmlspecialchars($row['branch_name'], ENT_QUOTES);
        $productName  = htmlspecialchars($row['product_name'], ENT_QUOTES);
        $unit         = htmlspecialchars($row['unit'], ENT_QUOTES);

        // raw values
        $qty          = (int)$row['quantity'];
        $costRaw      = (float)$row['cost_price'];
        $sellRaw      = (float)$row['selling_price'];
        $totalCostRaw = (float)$row['total_cost'];
        $revenueRaw   = (float)$row['potential_revenue'];

        // formatted
        $costFmt      = number_format($costRaw, 2);
        $sellFmt      = number_format($sellRaw, 2);
        $totCostFmt   = number_format($totalCostRaw, 2);
        $revFmt       = number_format($revenueRaw, 2);

        // status
        if ($qty === 0) {
            $statusText  = 'No Stock';
            $statusClass = 'inv-no-stock';
        } elseif ($qty < 20) {
            $statusText  = 'Low Stock';
            $statusClass = 'inv-low-stock';
        } else {
            $statusText  = 'On Stock';
            $statusClass = 'inv-on-stock';
        }

        echo "
        <tr>
            <td>{$branchName}</td>
            <td>{$productName}</td>
            <td>{$unit}</td>
            <td class='right'>₱{$costFmt}</td>
            <td class='right'>₱{$sellFmt}</td>
            <td class='right'>{$qty}</td>
            <td class='right'>₱{$totCostFmt}</td>
            <td class='right'>₱{$revFmt}</td>
            <td>
                <div class='inv-status'>
                    <span class='dot {$statusClass}'></span>
                    {$statusText}
                </div>
            </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='10' style='text-align:center;'>No branch inventory found</td></tr>";
}

$stmt->close();

/* ------------------------------------------
   6. PAGINATION OUTPUT
------------------------------------------ */
echo "<tr><td colspan='8' style='text-align:center;'>";

if ($totalPages > 1) {
    echo '<div class="pagination">';

    if ($page > 1) {
        echo "<button class='btn btn-primary' onclick='loadInventory(" . ($page - 1) . ")'>Prev</button>";
    }

    $window = 2;
    $start = max(1, $page - $window);
    $end   = min($totalPages, $page + $window);

    if ($start > 1) {
        echo "<button class='btn btn-secondary' onclick='loadInventory(1)'>1</button>";
        if ($start > 2) echo "<span>...</span>";
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $page) ? "btn-warning" : "btn-primary";
        echo "<button class='btn $active' onclick='loadInventory($i)'>$i</button>";
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) echo "<span>...</span>";
        echo "<button class='btn btn-secondary' onclick='loadInventory($totalPages)'>$totalPages</button>";
    }

    if ($page < $totalPages) {
        echo "<button class='btn btn-primary' onclick='loadInventory(" . ($page + 1) . ")'>Next</button>";
    }

    echo "</div>";
}

echo "</td></tr>";

/* ------------------------------------------
   7. GRAND TOTAL PREVIEW (BOTTOM)
------------------------------------------ */
echo "
<tr><td colspan='9' style='padding:0; border:none;'>
    <div id='invTotalsData'
         data-total-qty='{$grandQty}'
         data-total-cost='{$grandCost}'
         data-total-rev='{$grandRevenue}'>
    </div>
</td></tr>
";

echo "<!--PAGINATION-->";

$conn->close();
?>
