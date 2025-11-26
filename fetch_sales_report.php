<?php
// fetch_sales_report.php
session_start();
require_once "db_connection.php";

if (!isset($_SESSION['user_id'])) {
    echo "<tr><td colspan='10' style='text-align:center;'>Unauthorized</td></tr>";
    exit;
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// GET parameters
$view    = $_GET['view'] ?? 'detailed';
$branch  = $_GET['branch'] ?? '';
$product = $_GET['product'] ?? '';
$from    = $_GET['from'] ?? '';
$to      = $_GET['to'] ?? '';
$sort    = $_GET['sort'] ?? 'date_desc';
$group   = $_GET['group'] ?? 'none';
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = (int)($_GET['limit'] ?? 50);
$offset  = ($page - 1) * $limit;

$params = [];
$types  = "";

$whereClauses = " WHERE 1=1 ";

// role restriction
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

///////////////////////////////////////////////////////////////////
// DETAILED VIEW (8 columns)
///////////////////////////////////////////////////////////////////
if ($view === 'detailed') {

    $sql = "
    SELECT 
      s.sale_date,
      s.quantity,
      b.branch_name,
      p.product_name,
      p.selling_price,
      p.cost_price,
      (s.quantity * p.selling_price) AS total_sales,
      (s.quantity * p.cost_price) AS total_cost,
      ((p.selling_price - p.cost_price) * s.quantity) AS profit
    FROM Sales s
    JOIN Products p ON s.product_id = p.product_id
    JOIN Branches b ON s.branch_id = b.branch_id
    $whereClauses
    ";

    switch ($sort) {
        case 'date_asc':         $sql .= " ORDER BY s.sale_date ASC, p.product_name ASC"; break;
        case 'total_sales_desc': $sql .= " ORDER BY total_sales DESC"; break;
        case 'profit_desc':      $sql .= " ORDER BY profit DESC"; break;
        case 'date_desc':
        default:
            $sql .= " ORDER BY s.sale_date DESC, p.product_name ASC";
    }

    $sql .= " LIMIT ?, ?";

    $types2  = $types . "ii";
    $params2 = [...$params, $offset, $limit];

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types2, ...$params2);
    $stmt->execute();
    $result = $stmt->get_result();

    // count
    $countSql = "
        SELECT COUNT(*) 
        FROM Sales s
        JOIN Products p ON s.product_id = p.product_id
        JOIN Branches b ON s.branch_id = b.branch_id
        $whereClauses
    ";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $totalRows = $countStmt->get_result()->fetch_row()[0];
    $totalPages = ceil($totalRows / $limit);
    $countStmt->close();

    // output rows
    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {
            echo "<tr>
                <td>" . htmlspecialchars($r['branch_name']) . "</td>
                <td>" . htmlspecialchars($r['product_name']) . "</td>
                <td>" . htmlspecialchars($r['sale_date']) . "</td>
                <td class='right'>" . (int)$r['quantity'] . "</td>
                <td class='right'>₱" . number_format((float)$r['selling_price'], 2) . "</td>
                <td class='right'>₱" . number_format((float)$r['total_sales'], 2) . "</td>
                <td class='right'>₱" . number_format((float)$r['total_cost'], 2) . "</td>
                <td class='right'>₱" . number_format((float)$r['profit'], 2) . "</td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='10' style='text-align:center;'>No sales found</td></tr>";
    }

    $stmt->close();
}

///////////////////////////////////////////////////////////////////
// SUMMARY VIEW (overall totals or daily grouped)
///////////////////////////////////////////////////////////////////
else {

    if ($group === 'daily') {
        $baseSql = "
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
        $whereClauses
        GROUP BY s.sale_date, b.branch_name, p.product_name
        ";
    } else {
        $baseSql = "
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
        $whereClauses
        GROUP BY b.branch_name, p.product_name
        ";
    }

    switch ($sort) {
        case 'total_sales_desc': $order = " ORDER BY total_sales DESC"; break;
        case 'profit_desc':      $order = " ORDER BY profit DESC"; break;
        case 'date_asc':         $order = " ORDER BY total_sales ASC"; break;
        case 'date_desc':
        default:
            $order = " ORDER BY total_sales DESC";
    }

    $sql = $baseSql . $order . " LIMIT ?, ?";

    $types2  = $types . "ii";
    $params2 = [...$params, $offset, $limit];

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types2, ...$params2);
    $stmt->execute();
    $result = $stmt->get_result();

    // correct count for grouped summary
    $countSql = "SELECT COUNT(*) AS total FROM ($baseSql) AS grouped_results";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $totalRows = $countStmt->get_result()->fetch_row()[0];
    $totalPages = ceil($totalRows / $limit);
    $countStmt->close();

    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {

            $branchName  = htmlspecialchars($r['branch_name']);
            $productName = htmlspecialchars($r['product_name']);
            $totalQty    = (int)$r['total_qty'];
            $totalSales  = number_format((float)$r['total_sales'], 2);
            $totalCost   = number_format((float)$r['total_cost'], 2);
            $profit      = number_format((float)$r['profit'], 2);

            if ($group === 'daily') {
                echo "<tr>
                    <td>{$branchName}</td>
                    <td>{$productName}</td>
                    <td>" . htmlspecialchars($r['sale_date']) . "</td>
                    <td class='right'>{$totalQty}</td>
                    <td class='right'>₱{$totalSales}</td>
                    <td class='right'>₱{$totalCost}</td>
                    <td class='right'>₱{$profit}</td>
                </tr>";
            } else {
                echo "<tr>
                    <td>{$branchName}</td>
                    <td>{$productName}</td>
                    <td class='right'>{$totalQty}</td>
                    <td class='right'>₱{$totalSales}</td>
                    <td class='right'>₱{$totalCost}</td>
                    <td class='right'>₱{$profit}</td>
                </tr>";
            }
        }
    } else {
        echo "<tr><td colspan='10' style='text-align:center;'>No summary results</td></tr>";
    }

    $stmt->close();
}

///////////////////////////////////////////////////////////////////
// PAGINATION FOOTER
///////////////////////////////////////////////////////////////////

echo "<tr><td colspan='10' style='text-align:center;'>";

if ($totalPages > 1) {
    echo "<div class='pagination'>";

    if ($page > 1) {
        echo "<button class='btn btn-primary' onclick='loadSales(" . ($page - 1) . ")'>Prev</button>";
    }

    $window = 2;
    $start = max(1, $page - $window);
    $end   = min($totalPages, $page + $window);

    if ($start > 1) {
        echo "<button class='btn btn-secondary' onclick='loadSales(1)'>1</button>";
        if ($start > 2) echo "<span>...</span>";
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $page) ? "btn-warning" : "btn-primary";
        echo "<button class='btn $active' onclick='loadSales($i)'>$i</button>";
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) echo "<span>...</span>";
        echo "<button class='btn btn-secondary' onclick='loadSales($totalPages)'>$totalPages</button>";
    }

    if ($page < $totalPages) {
        echo "<button class='btn btn-primary' onclick='loadSales(" . ($page + 1) . ")'>Next</button>";
    }

    echo "</div>";
}

echo "</td></tr>";

$conn->close();
?>
