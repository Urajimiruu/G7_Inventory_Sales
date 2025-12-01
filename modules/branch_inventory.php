<?php

require_once "db_connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$role     = $_SESSION['role'];
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// fetch dynamic dropdowns
$units = $conn->query("SELECT DISTINCT unit FROM Products WHERE unit <> '' ORDER BY unit ASC");
$products = $conn->query("SELECT product_id, product_name FROM Products ORDER BY product_name ASC");
$branches = $conn->query("SELECT branch_id, branch_name FROM Branches ORDER BY branch_name ASC");
?>

<div class="dashboard">

  <!--  Filters -->
  <form id="filterForm" class="filter-form">
    <div class="filters">

      <div class="filter-left">

        <!-- PRODUCT FILTER -->
        <label>Product:</label>
        <select name="product_id" onchange="loadBranchInventory()">
          <option value="">All</option>
          <?php while ($p = $products->fetch_assoc()): ?>
            <option value="<?= $p['product_id'] ?>"><?= htmlspecialchars($p['product_name']) ?></option>
          <?php endwhile; ?>
        </select>

        <!-- UNIT FILTER -->
        <label>Unit:</label>
        <select name="unit" onchange="loadBranchInventory()">
          <option value="">All</option>
          <?php while ($u = $units->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($u['unit']) ?>"><?= htmlspecialchars($u['unit']) ?></option>
          <?php endwhile; ?>
        </select>

        <!-- BRANCH FILTER (admin only) -->
        <?php if ($role === 'admin'): ?>
        <label>Branch:</label>
        <select name="branch_id" onchange="loadBranchInventory()">
          <option value="">All</option>
          <?php while ($b = $branches->fetch_assoc()): ?>
            <option value="<?= $b['branch_id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
          <?php endwhile; ?>
        </select>
        <?php endif; ?>

        <!-- SORT -->
        <label>Sort:</label>
        <select name="sort" onchange="loadBranchInventory()">
          <option value="ASC">Lowest Qty</option>
          <option value="DESC">Highest Qty</option>
        </select>

      </div>

      <div class="filter-right">
        <label>Search:</label>
        <input type="text" name="search" placeholder="Search product..." onkeyup="loadBranchInventory()">

        <!-- RETURN BTN FOR SHOP ONLY -->
        <?php if ($role === 'shop'): ?>
          <button type="button" class="btn btn-primary"
            onclick="window.location.href='shop.php?page=returns'">Returns</button>
        <?php elseif ($role === 'admin'): ?>
          <button type="button" class="btn btn-primary"
            onclick="window.location.href='admin.php?page=returns'">Returns</button>
        <?php endif; ?>
      </div>

    </div>
  </form>

  <!-- TABLE -->
  <div class="table-scroll" role="region">
   
    <table class="vertical">
      
       </div>
      
      <thead>
        
        <tr>
          <th>Branch</th>
          <th>Product</th>
          <th style="width: 150px;">Unit</th>
          <th class="right">Cost Price</th>
          <th class="right">Selling Price</th>
          <th class="right" style="width: 150px;">Quantity</th>
          <th style="width: 150px;">Status</th>
        </tr>
      </thead>
        
      <tbody id="branchInventoryBody">
        <!-- loaded via AJAX -->
      </tbody>
    </table>
      <div id="salesLoading" class="loading-indicator" style="display: none;">
        <div class="loading-spinner"></div>
        <p>Loading sales data...</p>
  </div>
        
</div>

<style>
/* ===== LOADING INDICATOR STYLES ===== */
.table-scroll {
  position: relative;
  min-height: 200px;
}

.loading-indicator {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.95);
  display: none;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 100;
  border-radius: 8px;
  backdrop-filter: blur(2px);
}

.loading-spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #007bff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 15px;
}

.loading-indicator p {
  margin: 0;
  color: #333;
  font-size: 16px;
  font-weight: 500;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* ===== SKELETON LOADING STYLES ===== */
.skeleton-row {
  display: flex;
  align-items: center;
  padding: 12px 8px;
  border-bottom: 1px solid #eee;
  gap: 10px;
}

.skeleton-cell {
  height: 16px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: loading 1.5s infinite;
  border-radius: 4px;
}

@keyframes loading {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ===== ERROR MESSAGE STYLES ===== */
.error-message {
  text-align: center;
  padding: 40px 20px !important;
  background: #f8f9fa;
  border-radius: 8px;
}

.error-content {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  background: #f8d7da;
  color: #721c24;
  padding: 16px 24px;
  border-radius: 8px;
  border: 1px solid #f5c6cb;
  max-width: 400px;
}

.error-icon {
  font-size: 24px;
}
</style>
<script>
document.addEventListener("DOMContentLoaded", loadBranchInventory);

// function loadBranchInventory() {
//     const form = document.getElementById("filterForm");
//     const formData = new FormData(form);

//     fetch("modules/list_branch_inventory.php?" + new URLSearchParams(formData), {
//         method: "GET"
//     })
//     .then(res => res.text())
//     .then(html => {
//         document.getElementById("branchInventoryBody").innerHTML = html;
//     });
// }


let currentPage = 1;
const PAGE_LIMIT = 10;

  // ---------- LOADING INDICATOR FUNCTIONS ----------
  function showLoadingIndicator() {
    const tableBody = document.getElementById('branchInventoryBody');
    const loadingIndicator = document.getElementById('salesLoading');
    
    // Show loading indicator
    loadingIndicator.style.display = 'flex';
    
    // Show skeleton loading in table
    tableBody.innerHTML = `
      <tr>
        <td colspan="8">
          <div class="skeleton-row">
            <div class="skeleton-cell" style="width: 150px;"></div>
            <div class="skeleton-cell" style="width: 120px;"></div>
            <div class="skeleton-cell" style="width: 100px;"></div>
            <div class="skeleton-cell" style="width: 80px;"></div>
            <div class="skeleton-cell" style="width: 90px;"></div>
            <div class="skeleton-cell" style="width: 100px;"></div>
            <div class="skeleton-cell" style="width: 110px;"></div>
            <div class="skeleton-cell" style="width: 200px;"></div>
          </div>
          <div class="skeleton-row">
            <div class="skeleton-cell" style="width: 150px;"></div>
            <div class="skeleton-cell" style="width: 120px;"></div>
            <div class="skeleton-cell" style="width: 100px;"></div>
            <div class="skeleton-cell" style="width: 80px;"></div>
            <div class="skeleton-cell" style="width: 90px;"></div>
            <div class="skeleton-cell" style="width: 100px;"></div>
            <div class="skeleton-cell" style="width: 110px;"></div>
            <div class="skeleton-cell" style="width: 200px;"></div>
          </div>
          <div class="skeleton-row">
            <div class="skeleton-cell" style="width: 150px;"></div>
            <div class="skeleton-cell" style="width: 120px;"></div>
            <div class="skeleton-cell" style="width: 100px;"></div>
            <div class="skeleton-cell" style="width: 80px;"></div>
            <div class="skeleton-cell" style="width: 90px;"></div>
            <div class="skeleton-cell" style="width: 100px;"></div>
            <div class="skeleton-cell" style="width: 110px;"></div>
            <div class="skeleton-cell" style="width: 200px;"></div>
          </div>
        </td>
      </tr>
    `;
  }
  function hideLoadingIndicator() {
    const loadingIndicator = document.getElementById('salesLoading');
    loadingIndicator.style.display = 'none';
  }

function buildQueryParams(page = 1) {
    const form = document.getElementById("filterForm");
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);

    params.set("page", page);
    params.set("limit", PAGE_LIMIT);
    return params.toString();
}

function loadBranchInventory(page = 1) {
  const form = document.getElementById("filterForm");
  const formData = new FormData(form);
  formData.append('page', page); // send current page
  const params = new URLSearchParams(formData);

  showLoadingIndicator();

  fetch("modules/list_branch_inventory.php?" + params.toString())
    .then(res => res.text())
    .then(html => {
        document.getElementById("branchInventoryBody").innerHTML = html;
         hideLoadingIndicator();
    })
   
    .catch(err => {
        console.error("Error loading inventory:", err);
        document.getElementById("branchInventoryBody").innerHTML = 
          "<tr><td colspan='6' style='text-align:center;'>Error loading data</td></tr>";
    });
}
</script>
