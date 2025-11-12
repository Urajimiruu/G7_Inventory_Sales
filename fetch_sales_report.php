<?php
// fetch_sales_report.php
session_start();
require_once "db_connection.php";

if (!isset($_SESSION['user_id'])) {
    echo "<tr><td colspan='8' style='text-align:center;'>Unauthorized</td></tr>";
    exit;
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// GET params
$view    = $_GET['view'] ?? 'detailed'; // 'detailed' or 'summary'
$branch  = $_GET['branch'] ?? '';
$product = $_GET['product'] ?? '';
$from    = $_GET['from'] ?? '';
$to      = $_GET['to'] ?? '';
$sort    = $_GET['sort'] ?? 'date_desc';
$group   = $_GET['group'] ?? 'none'; // 'none' or 'daily'
$search  = trim($_GET['search'] ?? '');

// helper arrays for bind params
$params = [];
$types  = "";

$whereClauses = " WHERE 1=1 ";

if (strtolower($role) === 'shop') {
    // Shop users can only view their assigned branch
    $whereClauses .= " AND s.branch_id = ? ";
    $types .= "i";
    $params[] = $branchId;
} elseif (strtolower($role) === 'admin') {
    // Admin users — no restriction unless they choose a specific branch
    // so we do nothing here
}


// apply filters
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

// Build detailed or summary query
if ($view === 'detailed') {
    $sql = "
    SELECT 
      s.sale_id,
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
    " . $whereClauses . "
    ";
    // sort mapping
    switch ($sort) {
        case 'date_asc': $sql .= " ORDER BY s.sale_date ASC, p.product_name ASC"; break;
        case 'total_sales_desc': $sql .= " ORDER BY total_sales DESC"; break;
        case 'profit_desc': $sql .= " ORDER BY profit DESC"; break;
        case 'date_desc':
        default:
            $sql .= " ORDER BY s.sale_date DESC, p.product_name ASC";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo "<tr><td colspan='8' style='text-align:center;'>SQL prepare error</td></tr>";
        exit;
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {
            $branchName   = htmlspecialchars($r['branch_name'], ENT_QUOTES);
            $productName  = htmlspecialchars($r['product_name'], ENT_QUOTES);
            $saleDate     = htmlspecialchars($r['sale_date']);
            $qty          = (int)$r['quantity'];
            $sellingPrice = number_format((float)$r['selling_price'], 2);
            $totalSales   = number_format((float)$r['total_sales'], 2);
            $totalCost    = number_format((float)$r['total_cost'], 2);
            $profit       = number_format((float)$r['profit'], 2);

            echo "<tr>
                    <td>{$branchName}</td>
                    <td>{$productName}</td>
                    <td>{$saleDate}</td>
                    <td class='right'>{$qty}</td>
                    <td class='right'>₱{$sellingPrice}</td>
                    <td class='right'>₱{$totalSales}</td>
                    <td class='right'>₱{$totalCost}</td>
                    <td class='right'>₱{$profit}</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='8' style='text-align:center;'>No sales found</td></tr>";
    }

    $stmt->close();
} else {
    // summary view (grouped) — optional daily grouping
    if ($group === 'daily') {
        // group by date + branch + product
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
        GROUP BY s.sale_date, b.branch_name, p.product_name
        ";
        // sort
        switch ($sort) {
            case 'date_asc': $sql .= " ORDER BY s.sale_date ASC, b.branch_name, p.product_name"; break;
            case 'total_sales_desc': $sql .= " ORDER BY total_sales DESC"; break;
            case 'profit_desc': $sql .= " ORDER BY profit DESC"; break;
            case 'date_desc':
            default:
                $sql .= " ORDER BY s.sale_date DESC, b.branch_name, p.product_name";
        }
    } else {
        // overall totals (group by branch + product)
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
        GROUP BY b.branch_name, p.product_name
        ";
        // sort
        switch ($sort) {
            case 'date_asc':
            case 'date_desc':
                $sql .= " ORDER BY b.branch_name, p.product_name";
                break;
            case 'total_sales_desc': $sql .= " ORDER BY total_sales DESC"; break;
            case 'profit_desc': $sql .= " ORDER BY profit DESC"; break;
            default: $sql .= " ORDER BY total_sales DESC";
        }
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo "<tr><td colspan='8' style='text-align:center;'>SQL prepare error</td></tr>";
        exit;
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {
            if ($group === 'daily') {
                $date = htmlspecialchars($r['sale_date']);
                $branchName  = htmlspecialchars($r['branch_name'], ENT_QUOTES);
                $productName = htmlspecialchars($r['product_name'], ENT_QUOTES);
                $totalQty    = (int)$r['total_qty'];
                $totalSales  = number_format((float)$r['total_sales'], 2);
                $totalCost   = number_format((float)$r['total_cost'], 2);
                $profit      = number_format((float)$r['profit'], 2);

                echo "<tr>
                        <td>{$branchName}</td>
                        <td>{$productName}</td>
                        <td class='right'>{$totalQty}</td>
                        <td class='right'>₱{$totalSales}</td>
                        <td class='right'>₱{$totalCost}</td>
                        <td class='right'>₱{$profit}</td>
                        <td class='right' style='display:none'>{$date}</td>
                      </tr>";
            } else {
                $branchName  = htmlspecialchars($r['branch_name'], ENT_QUOTES);
                $productName = htmlspecialchars($r['product_name'], ENT_QUOTES);
                $totalQty    = (int)$r['total_qty'];
                $totalSales  = number_format((float)$r['total_sales'], 2);
                $totalCost   = number_format((float)$r['total_cost'], 2);
                $profit      = number_format((float)$r['profit'], 2);

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
        echo "<tr><td colspan='8' style='text-align:center;'>No summary results</td></tr>";
    }

    $stmt->close();
}

$conn->close();
?>
