<?php
require_once "db_connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);
?>

<div class="dashboard profitloss-dashboard">

  <!-- Search + Filters + Export -->
  <div class="profitloss-header">

      <div class="profitloss-filters-left">
          <!-- Branch Filter -->
          <label><b>Branch:</b></label>
          <select id="filterBranch" onchange="loadProfitLoss()">
              <option value="">All Branches</option>
              <?php
              $bq = $conn->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name");
              while ($b = $bq->fetch_assoc()) {
                  echo "<option value='{$b['branch_id']}'>{$b['branch_name']}</option>";
              }
              ?>
          </select>

          <!-- Product Filter -->
          <label><b>| Product:</b></label>
          <select id="filterProduct" onchange="loadProfitLoss()">
              <option value="">All Products</option>
              <?php
              $pq = $conn->query("SELECT product_id, product_name FROM products ORDER BY product_name");
              while ($p = $pq->fetch_assoc()) {
                  echo "<option value='{$p['product_id']}'>{$p['product_name']}</option>";
              }
              ?>
          </select>

          <!-- Sort Option -->
          <label><b>| Sort:</b></label>
          <select id="sortProfit" onchange="loadProfitLoss()">
              <option value="">Sort By Profit</option>
              <option value="asc">Lowest → Highest</option>
              <option value="desc">Highest → Lowest</option>
          </select>
      </div>

      <div class="profitloss-filters-right">
          <label><b>Search:</b></label>
          <input type="text" id="profitLossSearch" placeholder="Search branch or product..." onkeyup="loadProfitLoss()">
          <button type="button" class="btn btn-success profitloss-export-btn" onclick="exportProfitLoss()">Export</button>
      </div>

  </div>


  <!-- Table -->
  <div class="table-scroll profitloss-table-wrapper">
    <table class="user-table profitloss-table">
        <div id="salesLoading" class="loading-indicator" style="display: none;">
        <div class="loading-spinner"></div>
        <p>Loading sales data...</p>
      <thead>
        <tr>
          <th>Branch</th>
          <th>Product</th>
          <th class="right">Total Quantity Sold</th>
          <th class="right">Total Sales</th>
          <th class="right">Returned Sales</th>
          <th class="right">Net Sales</th>
          <th class="right">Total Cost</th>
          <th class="right">Profit / Loss</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="profitLossBody">
        <!-- AJAX rows will load here -->
      </tbody>
    </table>
  </div>

  <div id="profitLossPagination"></div>
  <div id="profitLossTotals"></div>

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
let currentProfitPage = 1;
const profitLimit = 50; // change this if you want more rows per page



    // ---------- LOADING INDICATOR FUNCTIONS ----------
  function showLoadingIndicator() {
    const tableBody = document.getElementById('profitLossBody');
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

function loadProfitLoss(page = 1) {
    currentProfitPage = page;

    const search  = document.getElementById('profitLossSearch').value.trim();
    const branch  = document.getElementById('filterBranch').value;
    const product = document.getElementById('filterProduct').value;
    const sort    = document.getElementById('sortProfit').value;

    const params = new URLSearchParams({
        search: search,
        branch: branch,
        product: product,
        sort: sort,
        page: page,
        limit: profitLimit
    });
    showLoadingIndicator();

    fetch('fetch_profitloss_report.php?' + params.toString())
    .then(res => res.text())
    .then(response => {

        const parts = response.split("<!--PAGINATION-->");
        const tableRows = parts[0];
        const paginationHtml = parts[1] || "";

        document.getElementById('profitLossBody').innerHTML = tableRows;
        document.getElementById('profitLossPagination').innerHTML = paginationHtml;

        // Read totals (hidden data inside tableRows)
        const temp = document.createElement("div");
        temp.innerHTML = tableRows;
        const totals = temp.querySelector("#profitLossTotalsData");

        if (totals) {
            const qtySold       = Number(totals.dataset.totalQtySold || 0);
            const totalSales    = Number(totals.dataset.totalSalesActive || 0);
            const returnedSales = Number(totals.dataset.totalReturned || 0);
            const netSales      = Number(totals.dataset.netSales || 0);
            const totalCost     = Number(totals.dataset.totalCost || 0);
            const totalProfit   = Number(totals.dataset.totalProfit || 0);

            document.getElementById("profitLossTotals").innerHTML = `
                <div class="pl-total-card-wrapper">
               <!-- <div class="pl-total-card">
                        <b>Total Qty Sold</b><br>
                        <span class="inv-total-number">${qtySold.toLocaleString()}</span>
                    </div> -->
                    <div class="pl-total-card">
                        <b>Total Sales</b><br>
                        <span class="inv-total-number">₱${totalSales.toLocaleString()}</span>
                    </div>
                    <div class="pl-total-card">
                        <b>Total Returned Sales</b><br>
                        <span class="inv-total-number">₱${returnedSales.toLocaleString()}</span>
                    </div>
                    <div class="pl-total-card">
                        <b>Net Sales</b><br>
                        <span class="inv-total-number">₱${netSales.toLocaleString()}</span>
                    </div>
                    <div class="pl-total-card">
                        <b>Total Cost</b><br>
                        <span class="inv-total-number">₱${totalCost.toLocaleString()}</span>
                    </div>
                    <div class="pl-total-card">
                        <b>Total Profit</b><br>
                        <span class="inv-total-number">₱${totalProfit.toLocaleString()}</span>
                    </div>
                </div>
            `;
        }
        hideLoadingIndicator();
    });

}

// Export with same filters
function exportProfitLoss() {
    const search  = document.getElementById('profitLossSearch').value.trim();
    const branch  = document.getElementById('filterBranch').value;
    const product = document.getElementById('filterProduct').value;
    const sort    = document.getElementById('sortProfit').value;

    const params = new URLSearchParams({
        search: search,
        branch: branch,
        product: product,
        sort: sort
    });

    window.location.href = 'export_profitloss_report_pdf.php?' + params.toString();
}

// Initial load
loadProfitLoss();
</script>
