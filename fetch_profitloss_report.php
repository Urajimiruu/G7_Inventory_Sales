<?php
require_once "db_connection.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<tr><td colspan='9' style='text-align:center;'>Unauthorized</td></tr><!--PAGINATION-->";
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

$where = " WHERE 1=1 ";
$params = [];
$types  = "";

// shop restriction (if shop-role, restrict)
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
   Count number of grouped rows (branch+product) for pagination
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
   Build aggregates per branch+product using the rules:
   - Active totals use s.unit_price and s.quantity
   - Returned totals use p.selling_price and s.quantity (no discounts)
   - Net sales = active_sales - returned_sales
   - Total cost uses active quantity * p.cost_price
   - Profit = net_sales - total_cost
------------------------------------------ */

$sql = "
SELECT
    b.branch_name,
    p.product_name,

    -- active qty only
    SUM(CASE WHEN s.status = 'active' THEN s.quantity ELSE 0 END) AS total_qty_sold,

    -- total sales from active (unit_price recorded in sale, includes discount if any)
    SUM(CASE WHEN s.status = 'active' THEN (s.quantity * s.unit_price) ELSE 0 END) AS total_sales_active,

    -- returned sales use product selling_price (ignore discounts)
    SUM(CASE WHEN s.status = 'returned' THEN (s.quantity * p.selling_price) ELSE 0 END) AS total_returned_sales,

    -- net sales = active - returned
    (SUM(CASE WHEN s.status = 'active' THEN (s.quantity * s.unit_price) ELSE 0 END)
     - SUM(CASE WHEN s.status = 'returned' THEN (s.quantity * p.selling_price) ELSE 0 END)
    ) AS net_sales,

    -- cost only for active qty
    SUM(CASE WHEN s.status = 'active' THEN (s.quantity * p.cost_price) ELSE 0 END) AS total_cost_active,

    -- profit = net_sales - total_cost_active (defined explicitly for ORDER BY)
    (
      (SUM(CASE WHEN s.status = 'active' THEN (s.quantity * s.unit_price) ELSE 0 END)
       - SUM(CASE WHEN s.status = 'returned' THEN (s.quantity * p.selling_price) ELSE 0 END)
      )
      - SUM(CASE WHEN s.status = 'active' THEN (s.quantity * p.cost_price) ELSE 0 END)
    ) AS profit

FROM Sales s
JOIN Products p ON s.product_id = p.product_id
JOIN Branches b ON s.branch_id = b.branch_id
$where
GROUP BY b.branch_name, p.product_name
";

if ($sortProfit === 'asc') {
    $sql .= " ORDER BY profit ASC ";
} elseif ($sortProfit === 'desc') {
    $sql .= " ORDER BY profit DESC ";
} else {
    $sql .= " ORDER BY b.branch_name, p.product_name ";
}

$sql .= " LIMIT ?, ? ";

// bind limit params
$types2 = $types . "ii";
$params2 = array_merge($params, [$offset, $limit]);

$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();

/* ------------------------------------------
   4. Output table rows (first part)
------------------------------------------ */

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        $branch = htmlspecialchars($row['branch_name']);
        $product = htmlspecialchars($row['product_name']);
        $qty = (int)$row['total_qty_sold'];
        $salesActive = number_format((float)$row['total_sales_active'], 2);
        $returnedSales = number_format((float)$row['total_returned_sales'], 2);
        $netSales = number_format((float)$row['net_sales'], 2);
        $cost = number_format((float)$row['total_cost_active'], 2);
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
            <td class='right'>₱{$salesActive}</td>
            <td class='right'>₱{$returnedSales}</td>
            <td class='right'>₱{$netSales}</td>
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
    echo "<tr><td colspan='9' style='text-align:center;'>No profit/loss records found</td></tr>";
}

$stmt->close();

/* ------------------------------------------
   5. Pagination (second part)
------------------------------------------ */
echo "<tr><td colspan='9' style='text-align:center;'>";

if ($totalPages > 1) {
    echo '<div class="pagination">';

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

    if ($page < $totalPages) {
        echo "<button class='btn btn-primary' onclick='loadProfitLoss(" . ($page + 1) . ")'>Next</button>";
    }

    echo "</div>";
}

echo "</td></tr>";

/* ------------------------------------------
   6. GRAND TOTALS (based only on filters, NOT pagination)
   We compute:
     - total_qty_sold (active only)
     - total_sales_active (active unit_price)
     - total_returned_sales (returned * p.selling_price)
     - net_sales = active - returned
     - total_cost_active
     - total_profit
------------------------------------------ */

$totalsSql = "
    SELECT
      SUM(CASE WHEN s.status = 'active' THEN s.quantity ELSE 0 END) AS total_qty_sold,
      SUM(CASE WHEN s.status = 'active' THEN (s.quantity * s.unit_price) ELSE 0 END) AS total_sales_active,
      SUM(CASE WHEN s.status = 'returned' THEN (s.quantity * p.selling_price) ELSE 0 END) AS total_returned_sales,
      (SUM(CASE WHEN s.status = 'active' THEN (s.quantity * s.unit_price) ELSE 0 END)
       - SUM(CASE WHEN s.status = 'returned' THEN (s.quantity * p.selling_price) ELSE 0 END)
      ) AS net_sales,
      SUM(CASE WHEN s.status = 'active' THEN (s.quantity * p.cost_price) ELSE 0 END) AS total_cost_active,
      (
        (SUM(CASE WHEN s.status = 'active' THEN (s.quantity * s.unit_price) ELSE 0 END)
         - SUM(CASE WHEN s.status = 'returned' THEN (s.quantity * p.selling_price) ELSE 0 END)
        )
        - SUM(CASE WHEN s.status = 'active' THEN (s.quantity * p.cost_price) ELSE 0 END)
      ) AS total_profit
    FROM Sales s
    JOIN Products p ON s.product_id = p.product_id
    JOIN Branches b ON s.branch_id = b.branch_id
    $where
";

$stmtTotals = $conn->prepare($totalsSql);
if (!empty($params)) $stmtTotals->bind_param($types, ...$params);
$stmtTotals->execute();
$gt = $stmtTotals->get_result()->fetch_assoc();
$stmtTotals->close();

$grandQtySold      = (int)($gt['total_qty_sold'] ?? 0);
$grandSalesActive  = (float)($gt['total_sales_active'] ?? 0);
$grandReturned     = (float)($gt['total_returned_sales'] ?? 0);
$grandNetSales     = (float)($gt['net_sales'] ?? 0);
$grandCostActive   = (float)($gt['total_cost_active'] ?? 0);
$grandProfit       = (float)($gt['total_profit'] ?? 0);

/* ------------------------------------------
   OUTPUT HIDDEN GRAND TOTALS BLOCK
   (Front-end reads #profitLossTotalsData inside returned HTML)
------------------------------------------ */
echo "
<tr>
    <td colspan='9' style='padding:0; border:none;'>
        <div id='profitLossTotalsData'
             data-total-qty-sold='{$grandQtySold}'
             data-total-sales-active='{$grandSalesActive}'
             data-total-returned='{$grandReturned}'
             data-net-sales='{$grandNetSales}'
             data-total-cost='{$grandCostActive}'
             data-total-profit='{$grandProfit}'>
        </div>
    </td>
</tr>
";

echo "<!--PAGINATION-->"; // marker for JS to split

$conn->close();
