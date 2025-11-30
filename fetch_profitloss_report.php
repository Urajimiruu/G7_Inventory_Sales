<?php
require_once "db_connection.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<tr><td colspan='7' style='text-align:center;'>Unauthorized</td></tr><!--PAGINATION-->";
    exit;
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);

$search        = trim($_GET['search'] ?? '');
$filterBranch  = trim($_GET['branch'] ?? '');
$filterProduct = trim($_GET['product'] ?? '');
$sortProfit    = trim($_GET['sort'] ?? '');

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = (int)($_GET['limit'] ?? 20);
$offset = ($page - 1) * $limit;

/* ------------------------------------------
   1. BUILD FILTER CONDITIONS FOR BOTH QUERIES
------------------------------------------ */

$where = " WHERE s.status = 'active' ";
$params = [];
$types  = "";

// shop restriction
if (strtolower($role) === 'shop' && $branchId > 0) {
    $where .= " AND s.branch_id = ? ";
    $types .= "i";
    $params[] = $branchId;
}

// search
if ($search !== '') {
    $where .= " AND (p.product_name LIKE ? OR b.branch_name LIKE ?) ";
    $types .= "ss";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// filter by branch
if ($filterBranch !== '') {
    $where .= " AND s.branch_id = ? ";
    $types .= "i";
    $params[] = (int)$filterBranch;
}

// filter by product
if ($filterProduct !== '') {
    $where .= " AND s.product_id = ? ";
    $types .= "i";
    $params[] = (int)$filterProduct;
}


/* ------------------------------------------
   2. COUNT QUERY 
------------------------------------------ */

$countSql = "
    SELECT COUNT(*) AS total
    FROM (
        SELECT 1
        FROM Sales s
        JOIN Products p ON s.product_id = p.product_id
        JOIN Branches b ON s.branch_id = b.branch_id
        $where
        GROUP BY b.branch_name, p.product_name
    ) AS t
";

$stmtCount = $conn->prepare($countSql);
if (!empty($params)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$totalRows = $stmtCount->get_result()->fetch_assoc()['total'] ?? 0;
$stmtCount->close();

$totalPages = max(1, ceil($totalRows / $limit));


/* ------------------------------------------
   3. MAIN QUERY WITH LIMIT
------------------------------------------ */

$sql = "
SELECT 
    b.branch_name,
    p.product_name,

    SUM(s.quantity) AS total_sold,

    -- USE UNIT PRICE FROM SALES
    SUM(s.quantity * s.unit_price) AS total_sales,

    -- USE COST PRICE FROM PRODUCTS
    SUM(s.quantity * p.cost_price) AS total_cost,

    -- REAL PROFIT OR LOSS
    (SUM(s.quantity * s.unit_price) - SUM(s.quantity * p.cost_price)) AS profit

FROM Sales s
JOIN Products p ON s.product_id = p.product_id
JOIN Branches b ON s.branch_id = b.branch_id
$where
GROUP BY b.branch_name, p.product_name
";

// sorting
if ($sortProfit === 'asc') {
    $sql .= " ORDER BY profit ASC ";
} elseif ($sortProfit === 'desc') {
    $sql .= " ORDER BY profit DESC ";
} else {
    $sql .= " ORDER BY b.branch_name, p.product_name ";
}

$sql .= " LIMIT ?, ? ";

// add limit parameters
$types2 = $types . "ii";
$params2 = array_merge($params, [$offset, $limit]);

$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();


/* ------------------------------------------
   4. OUTPUT TABLE ROWS
------------------------------------------ */

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        $branch = htmlspecialchars($row['branch_name']);
        $product = htmlspecialchars($row['product_name']);
        $qty = (int)$row['total_sold'];
        $sales = number_format($row['total_sales'], 2);
        $cost = number_format($row['total_cost'], 2);
        $profit = (float)$row['profit'];
        $profitFmt = number_format($profit, 2);

        if ($profit > 0) {
            $statusText = "Profit";
            $statusClass = "pl-profit";
        } elseif ($profit < 0) {
            $statusText = "Loss";
            $statusClass = "pl-loss";
        } else {
            $statusText = "Break-even";
            $statusClass = "pl-neutral";
        }

        echo "
        <tr>
            <td>{$branch}</td>
            <td>{$product}</td>
            <td class='right'>{$qty}</td>
            <td class='right'>₱{$sales}</td>
            <td class='right'>₱{$cost}</td>
            <td class='right'>₱{$profitFmt}</td>
            <td>
                <div class='pl-status'>
                    <span class='dot {$statusClass}'></span>
                    {$statusText}
                </div>
            </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='7' style='text-align:center;'>No profit/loss records found</td></tr>";
}

$stmt->close();


/* ------------------------------------------
   5. OUTPUT PAGINATION
------------------------------------------ */
echo "<tr><td colspan='8' style='text-align:center;'>";

if ($totalPages > 1) {
    echo '<div class="pagination">';

    // Prev
    if ($page > 1) {
        echo "<button class='btn btn-primary' onclick='loadProfitLoss(" . ($page - 1) . ")'>Prev</button>";
    }

    $window = 2;
    $start = max(1, $page - $window);
    $end   = min($totalPages, $page + $window);

    if ($start > 1) {
        echo "<button class='btn btn-secondary' onclick='loadProfitLoss(1)'>1</button>";
        if ($start > 2) echo "<span>...</span>";
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $page) ? "btn-warning" : "btn-primary";
        echo "<button class='btn $active' onclick='loadProfitLoss($i)'>$i</button>";
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) echo "<span>...</span>";
        echo "<button class='btn btn-secondary' onclick='loadProfitLoss($totalPages)'>$totalPages</button>";
    }

    // Next
    if ($page < $totalPages) {
        echo "<button class='btn btn-primary' onclick='loadProfitLoss(" . ($page + 1) . ")'>Next</button>";
    }

    echo "</div>";
}

echo "</td></tr>";

/* ------------------------------------------
   GRAND TOTALS (based only on filters, NOT pagination)
------------------------------------------ */

$totalsSql = "
    SELECT
        SUM(s.quantity) AS total_qty,
        SUM(s.quantity * s.unit_price) AS total_sales,
        SUM(s.quantity * p.cost_price) AS total_cost,
        SUM((s.unit_price - p.cost_price) * s.quantity) AS total_profit
    FROM Sales s
    JOIN Products p ON s.product_id = p.product_id
    JOIN Branches b ON s.branch_id = b.branch_id
    $where
      AND s.status = 'active'
";

$stmtTotals = $conn->prepare($totalsSql);
if (!empty($params)) $stmtTotals->bind_param($types, ...$params);
$stmtTotals->execute();
$gt = $stmtTotals->get_result()->fetch_assoc();
$stmtTotals->close();

$grandQty    = (int)($gt['total_qty'] ?? 0);
$grandSales  = (float)($gt['total_sales'] ?? 0);
$grandCost   = (float)($gt['total_cost'] ?? 0);
$grandProfit = (float)($gt['total_profit'] ?? 0);

/* ------------------------------------------
   OUTPUT HIDDEN GRAND TOTALS BLOCK
------------------------------------------ */
echo "
<tr>
    <td colspan='8' style='padding:0; border:none;'>
        <div id='profitLossTotalsData'
             data-total-qty='{$grandQty}'
             data-total-sales='{$grandSales}'
             data-total-cost='{$grandCost}'
             data-total-profit='{$grandProfit}'>
        </div>
    </td>
</tr>
";

echo "<!--PAGINATION-->"; // marker for JS to split

$conn->close();