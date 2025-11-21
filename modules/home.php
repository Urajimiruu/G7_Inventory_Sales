<?php
require_once "db_connection.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'admin';
$branchId = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : 0;

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

    // ---------- MONTHLY SALES (LINE CHART, 12-month normalized) ----------
    $lineLabels = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    $lineData   = array_fill(0, 12, 0); // default 12 months with zeros

    if ($role === "shop") {
        $sql = "
            SELECT MONTH(sale_date) AS m, SUM(quantity) AS total
            FROM sales
            WHERE branch_id = $branchId
            GROUP BY m
            ORDER BY m
        ";
    } else {
        $sql = "
            SELECT MONTH(sale_date) AS m, SUM(quantity) AS total
            FROM sales
            GROUP BY m
            ORDER BY m
        ";
    }

    $res = $conn->query($sql);

    // Safety: ensure query executed
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $monthIndex = ((int)$row['m']) - 1; // 1→Jan, so use index 0
            if ($monthIndex >= 0 && $monthIndex < 12) {
                $lineData[$monthIndex] = (int)$row['total'];
            }
        }
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
        
          const salesCtx = document.getElementById('salesChart').getContext('2d');

          const salesLabels = <?= json_encode($lineLabels) ?>;
          const salesData   = <?= json_encode($lineData) ?>;

          new Chart(salesCtx, {
              type: 'bar',
              data: {
                  labels: salesLabels,
                  datasets: [{
                      label: 'Monthly Sales (Qty Sold)',
                      data: salesData,
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
                          text: 'Monthly Sales (12-Month Summary)'
                      }
                  },
                  scales: {
                      y: {
                          beginAtZero: true,
                          ticks: { precision: 0 }
                      }
                  }
              }
          });

      </script>



    </div>

  </div>

</div>
