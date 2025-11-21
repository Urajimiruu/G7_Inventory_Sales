<?php
function getDashboardData($conn, $role, $branchId)
{
    // ---------- TOTAL PRODUCTS ----------
    $sql = "SELECT COUNT(*) AS total FROM products";
    $totalProducts = $conn->query($sql)->fetch_assoc()['total'];

    // ---------- TOTAL SALES (QUANTITY) ----------
    if ($role === "shop") {
        $sql = "SELECT SUM(quantity) AS total FROM sales WHERE branch_id = $branchId";
    } else {
        $sql = "SELECT SUM(quantity) AS total FROM sales";
    }
    $totalSales = $conn->query($sql)->fetch_assoc()['total'] ?? 0;

    // ---------- TOTAL BRANCHES ----------
    $sql = "SELECT COUNT(*) AS total FROM branches";
    $totalBranches = $conn->query($sql)->fetch_assoc()['total'];

    // ---------- TOTAL USERS ----------
    $sql = "SELECT COUNT(*) AS total FROM users";
    $totalUsers = $conn->query($sql)->fetch_assoc()['total'];

    // ---------- TOP 5 PRODUCTS SOLD ----------
    $topLabels = [];
    $topData   = [];

    if ($role === "shop") {
        $sql = "
            SELECT p.product_name, SUM(s.quantity) AS qty
            FROM sales s
            JOIN products p ON p.product_id = s.product_id
            WHERE s.branch_id = $branchId
            GROUP BY s.product_id
            ORDER BY qty DESC
            LIMIT 5
        ";
    } else {
        $sql = "
            SELECT p.product_name, SUM(s.quantity) AS qty
            FROM sales s
            JOIN products p ON p.product_id = s.product_id
            GROUP BY s.product_id
            ORDER BY qty DESC
            LIMIT 5
        ";
    }

    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $topLabels[] = $row['product_name'];
        $topData[]   = (int)$row['qty'];
    }

    // ---------- MONTHLY SALES (LINE CHART) ----------
    $lineLabels = [];
    $lineData   = [];

    if ($role === "shop") {
        $sql = "
            SELECT DATE_FORMAT(sale_date, '%b') AS month, SUM(quantity) AS total
            FROM sales
            WHERE branch_id = $branchId
            GROUP BY MONTH(sale_date)
            ORDER BY MONTH(sale_date)
        ";
    } else {
        $sql = "
            SELECT DATE_FORMAT(sale_date, '%b') AS month, SUM(quantity) AS total
            FROM sales
            GROUP BY MONTH(sale_date)
            ORDER BY MONTH(sale_date)
        ";
    }

    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $lineLabels[] = $row['month'];
        $lineData[]   = (int)$row['total'];
    }

    // ---------- RETURN EVERYTHING ----------
    return [
        'totalProducts' => $totalProducts,
        'totalSales'    => $totalSales,
        'totalBranches' => $totalBranches,
        'totalUsers'    => $totalUsers,
        'topLabels'     => $topLabels,
        'topData'       => $topData,
        'lineLabels'    => $lineLabels,
        'lineData'      => $lineData
    ];
}

require_once "db_connection.php";

$data = getDashboardData($conn, $role, $branchId);

// Extract values
$totalProducts = $data['totalProducts'];
$totalSales    = $data['totalSales'];
$totalBranches = $data['totalBranches'];
$totalUsers    = $data['totalUsers'];

$topLabels     = $data['topLabels'];
$topData       = $data['topData'];
$lineLabels    = $data['lineLabels'];
$lineData      = $data['lineData'];

?>

<div class="dashboard"> <!-- display sales data dito -->

<div class="grid grid--4-cols">
          <div class="feature">
            <ion-icon class="feature-icon" name="copy-outline"></ion-icon>
            <p class="feature-title">Products Available</p>
            <p class="feature-text">
              9583
            </p>
          </div>
          <div class="feature">
            <ion-icon class="feature-icon" name="pricetag-outline"></ion-icon>
            <p class="feature-title">Sales Made</p>
            <p class="feature-text">
              3534
            </p>
          </div>
          <div class="feature">
            <ion-icon class="feature-icon" name="storefront-outline"></ion-icon>
            <p class="feature-title">Branches</p>
            <p class="feature-text">
              4
            </p>
          </div>
          <div class="feature">
            <ion-icon class="feature-icon" name="person-outline"></ion-icon>
            <p class="feature-title">Users</p>
            <p class="feature-text">
              7
            </p>
          </div>
        </div>


<div class="grid grid--2-cols">

<div class="graph">
          <canvas id="salesChart"></canvas>
  <script>
    const ctx = document.getElementById('salesChart');

    new Chart(ctx, {
      type: 'bar', // other options: 'line', 'pie', 'doughnut', etc.
      data: {
        labels: ['January', 'February', 'March', 'April', 'May'],
        datasets: [{
          label: 'Sales (₱)',
          data: [1200, 1500, 1100, 1800, 1600],
          backgroundColor: '#4e79a7',
          borderRadius: 5
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: true },
          title: {
            display: true,
            text: 'Monthly Sales Data'
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { stepSize: 500 }
          }
        }
      }
    });
  </script>

</div>
</script>

</div>

</div>
