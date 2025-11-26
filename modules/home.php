<?php
require_once "db_connection.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'admin';
$branchId = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : 0;

// Get filter values from request
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$selectedBranch = isset($_GET['branch']) ? (int)$_GET['branch'] : ($role === 'shop' ? $branchId : 0);

// Validate month
if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = date('n');
}

// Get branches for filter dropdown
$branches = [];
if ($role === 'admin') {
    $sql = "SELECT branch_id, branch_name FROM branches ORDER BY branch_name";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $branches[$row['branch_id']] = $row['branch_name'];
    }
}

function getDashboardData($conn, $role, $branchId, $selectedMonth, $selectedBranch)
{
    $monthFilter = $selectedMonth;
    $branchFilter = $selectedBranch;
    
    // If shop user, always use their branch
    if ($role === "shop") {
        $branchFilter = $branchId;
    }

    // ---------- TOTAL PRODUCTS ----------
    $sql = "SELECT COUNT(*) AS total FROM products";
    $totalProducts = $conn->query($sql)->fetch_assoc()['total'];

    // ---------- TOTAL SALES TRANSACTIONS WITH FILTERS ----------
    $whereClause = "status != 'returned'";
    if ($branchFilter > 0) {
        $whereClause .= " AND branch_id = $branchFilter";
    }
    if ($monthFilter > 0) {
        $whereClause .= " AND MONTH(sale_date) = $monthFilter AND YEAR(sale_date) = YEAR(CURDATE())";
    }
    
    $sql = "SELECT count(*) AS total FROM sales WHERE $whereClause";
    $totalSales = $conn->query($sql)->fetch_assoc()['total'] ?? 0;

    // ---------- TOTAL BRANCHES ----------
    $sql = "SELECT COUNT(*) AS total FROM branches";
    $totalBranches = $conn->query($sql)->fetch_assoc()['total'];

    // ---------- TOTAL USERS ----------
    $sql = "SELECT COUNT(*) AS total FROM users";
    $totalUsers = $conn->query($sql)->fetch_assoc()['total'];

    
    // ---------- MONTHLY SALES REVENUE (12-month normalized) WITH BRANCH FILTER ----------
    $lineLabels = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    $lineData   = array_fill(0, 12, 0);

    $whereClause = "status != 'returned' AND YEAR(sale_date) = YEAR(CURDATE())";
    if ($branchFilter > 0) {
        $whereClause .= " AND branch_id = $branchFilter";
    }

    $sql = "
        SELECT MONTH(sale_date) AS m, SUM(quantity * unit_price) AS total_revenue
        FROM sales
        WHERE $whereClause
        GROUP BY m
        ORDER BY m
    ";

    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $monthIndex = ((int)$row['m']) - 1;
            if ($monthIndex >= 0 && $monthIndex < 12) {
                $lineData[$monthIndex] = (float)$row['total_revenue'];
            }
        }
    }

    // ---------- CURRENT MONTH ITEMS SALES WITH FILTERS ----------
    $currentMonthName = date('F', mktime(0, 0, 0, $monthFilter, 1));
    
    $whereClause = "s.status != 'returned' AND MONTH(s.sale_date) = $monthFilter AND YEAR(s.sale_date) = YEAR(CURDATE())";
    if ($branchFilter > 0) {
        $whereClause .= " AND s.branch_id = $branchFilter";
    }

    $sql = "
        SELECT 
            p.product_id,
            p.product_name,
            COALESCE(SUM(s.quantity), 0) AS total_quantity,
            COALESCE(SUM(s.quantity * s.unit_price), 0) AS total_revenue
        FROM products p
        LEFT JOIN sales s ON p.product_id = s.product_id 
            AND $whereClause
        GROUP BY p.product_id, p.product_name
        ORDER BY total_quantity DESC
    ";

    $res = $conn->query($sql);
    $currentMonthLabels = [];
    $currentMonthQuantities = [];
    $currentMonthRevenues = [];

    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $currentMonthLabels[] = $row['product_name'];
            $currentMonthQuantities[] = (int)$row['total_quantity'];
            $currentMonthRevenues[] = (float)$row['total_revenue'];
        }
    }

    // ---------- RETURN EVERYTHING ----------
    return [
        'totalProducts' => $totalProducts,
        'totalSales'    => $totalSales,
        'totalBranches' => $totalBranches,
        'totalUsers'    => $totalUsers,
        'lineLabels'    => $lineLabels,
        'lineData'      => $lineData,
        'currentMonthLabels' => $currentMonthLabels,
        'currentMonthQuantities' => $currentMonthQuantities,
        'currentMonthRevenues' => $currentMonthRevenues,
        'currentMonthName' => $currentMonthName,
        'selectedMonth' => $monthFilter,
        'selectedBranch' => $branchFilter
    ];
}

$data = getDashboardData($conn, $role, $branchId, $selectedMonth, $selectedBranch);

// Extract values
$totalProducts = $data['totalProducts'];
$totalSales    = $data['totalSales'];
$totalBranches = $data['totalBranches'];
$totalUsers    = $data['totalUsers'];

$lineLabels    = $data['lineLabels'];
$lineData      = $data['lineData'];
$currentMonthLabels = $data['currentMonthLabels'];
$currentMonthQuantities = $data['currentMonthQuantities'];
$currentMonthRevenues = $data['currentMonthRevenues'];
$currentMonthName = $data['currentMonthName'];
$selectedMonth = $data['selectedMonth'];
$selectedBranch = $data['selectedBranch'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --card-bg: #ffffff;
            --border: #e9ecef;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
            min-height: 100vh;
            /* padding: 20px; */
        }

        .dashboard {
            max-width: 1400px;
            margin: 0 auto;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 2rem;
            color: white;
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .dashboard-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .filters-section {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 1rem;
            background: var(--light);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 1px rgba(67, 97, 238, 0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
            overflow: hidden;
        }

        /* .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--success));
        } */

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }

        .stat-title {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .chart-card h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }

        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .chart-card {
                padding: 1rem;
            }
            
            .chart-container {
                height: 300px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header h1 {
                font-size: 2rem;
            }
        }

        /* .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        } */

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Header -->
        <!-- <div class="dashboard-header">
            <h1>Sales And Inventory Dashboard</h1>
        </div> -->

        <!-- Filters Section -->
        <div class="filters-section card-hover">
            <form method="GET" action="" id="filterForm" style="display: flex; gap: 1.5rem; align-items: end; flex-wrap: wrap;">
                
                <!-- Month Filter -->
                <div class="filter-group">
                    <label for="month">Select Month</label>
                    <select name="month" id="month" onchange="document.getElementById('filterForm').submit()">
                        <?php
                        $months = [
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                        ];
                        foreach ($months as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $selectedMonth == $num ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Branch Filter (Only for Admin) -->
                <?php if ($role === 'admin'): ?>
                <div class="filter-group">
                    <label for="branch">Select Branch</label>
                    <select name="branch" id="branch" onchange="document.getElementById('filterForm').submit()">
                        <option value="0" <?= $selectedBranch == 0 ? 'selected' : '' ?>>All Branches</option>
                        <?php foreach ($branches as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $selectedBranch == $id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card card-hover">
                <ion-icon class="stat-icon" name="cube-outline"></ion-icon>
                <div class="stat-title">Products Available</div>
                <div class="stat-value"><?= number_format($totalProducts) ?></div>
                <div class="stat-change">Total Products</div>
            </div>

            <div class="stat-card card-hover">
                <ion-icon class="stat-icon" name="receipt-outline"></ion-icon>
                <div class="stat-title">Sales Transactions</div>
                <div class="stat-value"><?= number_format($totalSales) ?></div>
                <div class="stat-change">Successful orders</div>
            </div>

            <?php if ($role === 'admin'): ?>
            <div class="stat-card card-hover">
                <ion-icon class="stat-icon" name="business-outline"></ion-icon>
                <div class="stat-title">Branches</div>
                <div class="stat-value"><?= number_format($totalBranches) ?></div>
                <div class="stat-change">Active locations</div>
            </div>

            <div class="stat-card card-hover">
                <ion-icon class="stat-icon" name="people-outline"></ion-icon>
                <div class="stat-title">Users</div>
                <div class="stat-value"><?= number_format($totalUsers) ?></div>
                <div class="stat-change">System users</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Charts Section -->
        <div class="charts-container">
            <!-- Monthly Sales Chart -->
            <div class="chart-card card-hover">
                <h3>Sales Trend</h3>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Current Month Sales Chart -->
            <div class="chart-card card-hover">
                <h3>Top Products - <?= $currentMonthName ?></h3>
                <div class="chart-container">
                    <canvas id="currentMonthChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Monthly Revenue Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesLabels = <?= json_encode($lineLabels) ?>;
        const salesData = <?= json_encode($lineData) ?>;

        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Sales (₱)',
                    data: salesData,
                    backgroundColor: 'rgba(67, 97, 238, 0.8)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: '#333',
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    },
                    title: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            size: 13
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                return `₱${context.parsed.y.toLocaleString()}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            color: '#666',
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            },
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#666',
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });

        // Current Month Sales Chart
        const currentMonthCtx = document.getElementById('currentMonthChart').getContext('2d');
        const currentMonthLabels = <?= json_encode($currentMonthLabels) ?>;
        const currentMonthQuantities = <?= json_encode($currentMonthQuantities) ?>;

        new Chart(currentMonthCtx, {
            type: 'bar',
            data: {
                labels: currentMonthLabels,
                datasets: [{
                    label: 'Quantity Sold',
                    data: currentMonthQuantities,
                    backgroundColor: 'rgba(120, 95, 2, 0.8)',
                    borderColor: 'rgba(120, 95, 2, 0.8)',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: '#333',
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    },
                    title: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            size: 13
                        },
                        bodyFont: {
                            size: 13
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            color: '#666',
                            precision: 0,
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#666',
                            maxRotation: 45,
                            minRotation: 45,
                            font: {
                                size: 10
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });
    </script>
</body>
</html>