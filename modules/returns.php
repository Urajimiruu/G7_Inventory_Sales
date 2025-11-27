<?php
require_once "db_connection.php";


if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}

$role     = strtolower($_SESSION['role'] ?? '');
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// Get filter parameters
$filterProduct = $_GET['product'] ?? '';
$filterFromDate = $_GET['from_date'] ?? '';
$filterToDate = $_GET['to_date'] ?? '';
$filterBranch = $_GET['branch'] ?? '';

// Load branches for filter (admin sees all, shop sees only their branch)
if ($role === 'shop' && $branchId > 0) {
  $branchSql = "SELECT branch_id, branch_name FROM branches WHERE branch_id = ?";
  $stmt = $conn->prepare($branchSql);
  $stmt->bind_param("i", $branchId);
  $stmt->execute();
  $branchesRes = $stmt->get_result();
} else {
  $branchesRes = $conn->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name ASC");
}

// Load products for filter
$productSql = "SELECT product_id, product_name FROM products ORDER BY product_name ASC";
$productRes = $conn->query($productSql);

// Build returns query with filters
$returnsWhere = "WHERE s.status = 'returned'";
$params = [];
$types  = "";

if ($role === 'shop' && $branchId > 0) {
  $returnsWhere .= " AND s.branch_id = ?";
  $params[] = $branchId;
  $types .= "i";
}

// Add branch filter for non-shop users
if ($role !== 'shop' && !empty($filterBranch)) {
  $returnsWhere .= " AND s.branch_id = ?";
  $params[] = $filterBranch;
  $types .= "i";
}

// Add product filter
if (!empty($filterProduct)) {
  $returnsWhere .= " AND p.product_id = ?";
  $params[] = $filterProduct;
  $types .= "i";
}

// Add date filters (only if dates are provided)
if (!empty($filterFromDate)) {
  $returnsWhere .= " AND s.return_date >= ?";
  $params[] = $filterFromDate;
  $types .= "s";
}

if (!empty($filterToDate)) {
  $returnsWhere .= " AND s.return_date <= ?";
  $params[] = $filterToDate;
  $types .= "s";
}

$returnsSql = "
  SELECT 
    s.sale_id,
    s.sale_date,
    s.return_date,
    s.quantity,
    p.product_name,
    b.branch_name,
    p.selling_price,
    (s.quantity * p.selling_price) AS total
  FROM sales s
  JOIN products p ON s.product_id = p.product_id
  JOIN branches b ON s.branch_id = b.branch_id
  $returnsWhere
  ORDER BY s.return_date desc
";

if ($params) {
  $stmtReturns = $conn->prepare($returnsSql);
  $stmtReturns->bind_param($types, ...$params);
  $stmtReturns->execute();
  $returnsRes = $stmtReturns->get_result();
} else {
  $returnsRes = $conn->query($returnsSql);
}

// Calculate totals based on filtered results
$totalReturns = 0;
$totalAmount = 0;
$totalQuantity = 0;

if ($returnsRes && $returnsRes->num_rows > 0) {
  $totalReturns = $returnsRes->num_rows;
  $returnsRes->data_seek(0); // Reset pointer
  while ($row = $returnsRes->fetch_assoc()) {
    $totalAmount += $row['total'];
    $totalQuantity += $row['quantity'];
  }
  $returnsRes->data_seek(0); // Reset again for display
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Returned Sales - Inventory System</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .returns-header {
      background: linear-gradient(135deg, #dc3545, #c82333);
      color: white;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
    }
    
    .returns-header h1 {
      margin: 0;
      font-size: 24px;
    }
    
    .returns-header p {
      margin: 5px 0 0 0;
      opacity: 0.9;
    }
    
    .status-returned {
      color: #dc3545;
      font-weight: bold;
      padding: 4px 8px;
      background: #f8d7da;
      border-radius: 4px;
      font-size: 12px;
    }
    
    .no-returns {
      text-align: center;
      padding: 60px 20px;
      color: #6c757d;
      background: #f8f9fa;
      border-radius: 8px;
      border: 2px dashed #dee2e6;
    }
    
    .no-returns i {
      font-size: 64px;
      margin-bottom: 15px;
      display: block;
    }
    
    .returns-summary {
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .summary-item {
      text-align: center;
    }
    
    .summary-item .value {
      font-size: 20px;
      font-weight: bold;
      color: #dc3545;
    }
    
    .summary-item .label {
      font-size: 12px;
      color: #6c757d;
      text-transform: uppercase;
    }
    
    .filter-form {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      border: 1px solid #dee2e6;
    }
    
    .filters {
      display: flex;
      gap: 15px;
      align-items: end;
      flex-wrap: wrap;
    }
    
    .filter-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    
    .filter-group label {
      font-weight: bold;
      font-size: 12px;
      color: #495057;
    }
    
    .filter-group select,
    .filter-group input {
      padding: 8px 12px;
      border: 1px solid #ced4da;
      border-radius: 4px;
      font-size: 14px;
      background: white;
    }
    
    .filter-actions {
      display: flex;
      gap: 10px;
      align-items: end;
    }
    
    .btn {
      padding: 8px 16px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }
    
    .btn-secondary {
      background: #6c757d;
      color: white;
    }
    
    .btn:hover {
      opacity: 0.9;
    }
    
    .muted {
      color: #6c757d;
      font-size: 0.9em;
    }

    /* Auto-submit styling */
    .auto-submit {
      transition: all 0.3s ease;
    }
    
    .auto-submit:focus {
      border-color: #007bff;
      box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }
  </style>
</head>
<body>
  <div class="main-container">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="content">
      <div class="dashboard">

        <!-- Filters Form -->
        <form method="GET" class="filter-form" id="filterForm">
          <input type="hidden" name="page" value="returns">
          <div class="filters">
            <!-- Product Filter -->
            <div class="filter-group">
              <label for="product">Product</label>
              <select id="product" name="product" class="auto-submit">
                <option value="">All Products</option>
                <?php 
                $productRes->data_seek(0); // Reset pointer
                while ($product = $productRes->fetch_assoc()): 
                ?>
                  <option value="<?= $product['product_id'] ?>" 
                    <?= $filterProduct == $product['product_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($product['product_name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <!-- Branch Filter (only for non-shop users) -->
            <?php if ($role !== 'shop'): ?>
            <div class="filter-group">
              <label for="branch">Branch</label>
              <select id="branch" name="branch" class="auto-submit">
                <option value="">All Branches</option>
                <?php 
                $branchesRes->data_seek(0); // Reset pointer
                while ($branch = $branchesRes->fetch_assoc()): 
                ?>
                  <option value="<?= $branch['branch_id'] ?>" 
                    <?= $filterBranch == $branch['branch_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($branch['branch_name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <?php endif; ?>

            <!-- Date Filters -->
            <div class="filter-group">
              <label for="from_date">From Date</label>
              <input type="date" id="from_date" name="from_date" value="<?= htmlspecialchars($filterFromDate) ?>" class="auto-submit">
            </div>

            <div class="filter-group">
              <label for="to_date">To Date</label>
              <input type="date" id="to_date" name="to_date" value="<?= htmlspecialchars($filterToDate) ?>" class="auto-submit">
            </div>

            <!-- Clear Filters Button Only -->
            <div class="filter-actions">
              <a href="?page=returns" class="btn btn-secondary">Clear All Filters</a>
            </div>
          </div>
        </form>
<div id="returnsContainer">
        <!-- Returns Summary -->
        <div class="returns-summary">
          <div class="summary-item">
            <div class="value"><?= $totalReturns ?></div>
            <div class="label">Total Returns</div>
          </div>
          <div class="summary-item">
            <div class="value"><?= number_format($totalQuantity) ?></div>
            <div class="label">Items Returned</div>
          </div>
          <div class="summary-item">
            <div class="value">₱<?= number_format($totalAmount, 2) ?></div>
            <div class="label">Total Value</div>
          </div>
        </div>

        <!-- Returns Table -->
        <div class="table-scroll" role="region" aria-label="Returned sales table">
          <table class="vertical" aria-describedby="caption-returns">
            <thead>
              <tr>
                <th scope="col">Return Date</th>
                <th scope="col">Original Sale Date</th>
                <th scope="col">Product</th>
                <th scope="col">Branch</th>
                <th scope="col" class="right">Quantity</th>
                <th scope="col" class="right">Unit Price</th>
                <th scope="col" class="right">Total Value</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($returnsRes && $returnsRes->num_rows > 0): ?>
                <?php while ($return = $returnsRes->fetch_assoc()): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($return['return_date']) ?></strong></td>
                    <td class="muted"><?= htmlspecialchars($return['sale_date']) ?></td>
                    <td><?= htmlspecialchars($return['product_name']) ?></td>
                    <td><?= htmlspecialchars($return['branch_name']) ?></td>
                    <td class="right"><?= (int)$return['quantity'] ?></td>
                    <td class="right">₱<?= number_format((float)$return['selling_price'], 2) ?></td>
                    <td class="right">₱<?= number_format((float)$return['total'], 2) ?></td>
                    <td>
                      <span class="status-returned">Returned</span>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="8" class="no-returns">
                    <h3>No Returned Sales</h3>
                    <p>
                      <?php if ($filterProduct || $filterBranch || $filterFromDate || $filterToDate): ?>
                        No returned sales found matching your filter criteria.
                      <?php else: ?>
                        There are no returned sales records to display.
                      <?php endif; ?>
                    </p>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
 <script>
document.addEventListener('DOMContentLoaded', function() {

    const filterForm = document.getElementById('filterForm');

    function updateTableOnly() {
        const formData = new FormData(filterForm);
        const query = new URLSearchParams(formData).toString();

        // Load same page but via AJAX
       
          fetch("<?php echo $_SERVER['PHP_SELF']; ?>?page=returns&" + query)

            .then(response => response.text())
            .then(fullHTML => {

                // Create a virtual DOM
                const parser = new DOMParser();
                const doc = parser.parseFromString(fullHTML, "text/html");

                // Extract the returnsContainer content
                const newContent = doc.querySelector("#returnsContainer").innerHTML;

                // Replace only the container
                document.getElementById("returnsContainer").innerHTML = newContent;

            });
    }

    // Trigger AJAX on change
    document.querySelectorAll('.auto-submit').forEach(field => {
        field.addEventListener('change', updateTableOnly);
    });

});
</script>


</body>
</html>