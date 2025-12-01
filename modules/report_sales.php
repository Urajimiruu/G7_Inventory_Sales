<?php
// report_sales.php
require_once "db_connection.php";
// session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);
?>
<div class="dashboard sale-report-dashboard">


  <div class="sale-report-header">
    <div class="sale-filters-left">

      <select id="saleFilterBranch" onchange="loadSales(1)">
        <option value="">All Branches</option>
        <?php
        $bq = $conn->query("SELECT branch_id, branch_name FROM Branches ORDER BY branch_name");
        while ($b = $bq->fetch_assoc()) {
            echo "<option value='{$b['branch_id']}'>{$b['branch_name']}</option>";
        }
        ?>
      </select>

      <select id="saleFilterProduct" onchange="loadSales(1)">
        <option value="">All Products</option>
        <?php
        $pq = $conn->query("SELECT product_id, product_name FROM Products ORDER BY product_name");
        while ($p = $pq->fetch_assoc()) {
            echo "<option value='{$p['product_id']}'>{$p['product_name']}</option>";
        }
        ?>
      </select>

      <label><b>From:</b></label>
      <input type="date" id="saleFrom" onchange="loadSales(1)">

      <label><b>To:</b></label>
      <input type="date" id="saleTo" onchange="loadSales(1)">

      <label><b>Sort:</b></label>
      <select id="saleSort" onchange="loadSales(1)">
        <option value="date_desc">Newest</option>
        <option value="date_asc">Oldest</option>
        <option value="total_sales_desc">Highest Sales</option>
        <option value="profit_desc">Highest Profit</option>
      </select>

      <div class="view-toggle">
        <button id="viewDetailed" class="view-btn active" onclick="setView('detailed')">Detailed</button>
        <button id="viewSummary" class="view-btn" onclick="setView('summary')">Summary</button>
      </div>

      <select id="groupByDate" onchange="updateSummaryHeader(); loadSales(1);">
        <option value="none">Overall Totals</option>
        <option value="daily">Group by Date (Daily)</option>
      </select>

    </div>

    <div class="sale-filters-right">
      <input type="text" id="saleSearch" placeholder="Search..." onkeyup="debouncedLoadSales()">
      <button type="button" class="btn btn-success sale-export-btn" onclick="exportSales()">Export</button>
    </div>
  </div>

  <div class="table-scroll sale-report-table-wrapper">
    <table class="user-table sale-report-table">
        <div id="salesLoading" class="loading-indicator" style="display: none;">
        <div class="loading-spinner"></div>
        <p>Loading sales data...</p>
      </div>
      <thead id="saleTableHead">
        <tr id="saleHeaderRow">
          <th>Branch</th>
          <th>Product</th>
          <th>Date</th>
          <th class="right">Qty</th>
          <th class="right">Selling Price</th>
          <th class="right">Total Sales</th>
          <th class="right">Total Cost</th>
          <th class="right">Profit</th>
        </tr>
      </thead>
      <tbody id="saleReportBody">
      </tbody>
    </table>
  </div>

  <div id="salesReportTotals"></div>

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
  
let viewMode = 'detailed';
let debounceTimer = null;
// ---------- LOADING INDICATOR FUNCTIONS ----------
  function showLoadingIndicator() {
    const tableBody = document.getElementById('saleReportBody');
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


function setView(mode) {
    viewMode = mode;
    document.getElementById('viewDetailed').classList.toggle('active', mode === 'detailed');
    document.getElementById('viewSummary').classList.toggle('active', mode === 'summary');

    // toggle grouping selector
    document.getElementById('groupByDate').style.display = (mode === 'summary') ? 'inline-block' : 'none';

    // adjust table headers
    const headerRow = document.getElementById('saleHeaderRow');

    if (mode === 'detailed') {
        headerRow.innerHTML = `
          <th>Branch</th>
          <th>Product</th>
          <th>Date</th>
          <th class="right">Qty</th>
          <th class="right">Selling Price</th>
          <th class="right">Total Sales</th>
          <th class="right">Total Cost</th>
          <th class="right">Profit</th>
        `;
    } else {
        const groupMode = document.getElementById('groupByDate').value;
        if (groupMode === 'daily') {
            headerRow.innerHTML = `
              <th>Branch</th>
              <th>Product</th>
              <th>Date</th>
              <th class="right">Total Qty</th>
              <th class="right">Total Sales</th>
              <th class="right">Total Cost</th>
              <th class="right">Profit</th>
            `;
        } else {
            headerRow.innerHTML = `
              <th>Branch</th>
              <th>Product</th>
              <th class="right">Total Qty</th>
              <th class="right">Total Sales</th>
              <th class="right">Total Cost</th>
              <th class="right">Profit</th>
            `;
        }
    }

    loadSales(1);
}

function gatherParams(page = salesCurrentPage) {
    return {
        view: viewMode,
        branch: document.getElementById('saleFilterBranch').value || '',
        product: document.getElementById('saleFilterProduct').value || '',
        from: document.getElementById('saleFrom').value || '',
        to: document.getElementById('saleTo').value || '',
        sort: document.getElementById('saleSort').value || '',
        group: document.getElementById('groupByDate').value || 'none',
        search: document.getElementById('saleSearch').value.trim() || '',
        page: page,
        limit: SALES_LIMIT
    };
}

function paramsToQuery(params) {
    const q = new URLSearchParams();
    for (const k in params) {
        if (params[k] !== '') q.append(k, params[k]);
    }
    return q.toString();
}

let salesCurrentPage = 1;
const SALES_LIMIT = 50;

function loadSales(page = 1) {
    salesCurrentPage = page;
    const params = gatherParams(page);
    showLoadingIndicator(); 

    fetch("fetch_sales_report.php?" + paramsToQuery(params))
        .then(res => res.text())
        .then(html => {

            // 1. Insert rows into table
            document.getElementById("saleReportBody").innerHTML = html;

            // 2. Extract totals from the response
            const temp = document.createElement("div");
            temp.innerHTML = html;
            const totals = temp.querySelector("#salesTotalsData");

            if (totals) {
                const qty    = Number(totals.dataset.totalQty);
                const sales  = Number(totals.dataset.totalSales);
                const cost   = Number(totals.dataset.totalCost);
                const profit = Number(totals.dataset.totalProfit);
                

                document.getElementById("salesReportTotals").innerHTML = `
                    <div class="sales-total-card-wrapper">
                        <div class="sales-total-card">
                          <b>Total Qty</b><br>
                          <span class="inv-total-number">${qty.toLocaleString()}</span>
                        </div>
                        <div class="sales-total-card">
                          <b>Total Sales</b><br>
                          <span class="inv-total-number">₱${sales.toLocaleString()}</span>
                        </div>
                        <div class="sales-total-card">
                          <b>Total Cost</b><br>
                          <span class="inv-total-number">₱${cost.toLocaleString()}</span>
                        </div>
                        <div class="sales-total-card">
                          <b>Total Profit</b><br>
                          <span class="inv-total-number">₱${profit.toLocaleString()}</span>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById("salesReportTotals").innerHTML = "";
                 
            }
            hideLoadingIndicator();
        })
        .catch(err => {
            console.error(err);
            document.getElementById("saleReportBody").innerHTML =
                '<tr><td colspan="10" style="text-align:center;">Request failed</td></tr>';
        });
}

function debouncedLoadSales() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadSales, 300);
}

function exportSales() {
    const params = gatherParams();
    const query = new URLSearchParams(params).toString();
    window.open('export_sales_report_pdf.php?' + query, '_blank');
}

document.addEventListener('DOMContentLoaded', function () {
    setView('detailed');
    document.getElementById('groupByDate').style.display = 'none';
});

function updateSummaryHeader() {
    if (viewMode !== 'summary') return;

    const headerRow = document.getElementById('saleHeaderRow');
    const groupMode = document.getElementById('groupByDate').value;

    if (groupMode === 'daily') {
        headerRow.innerHTML = `
          <th>Branch</th>
          <th>Product</th>
          <th>Date</th>
          <th class="right">Total Qty</th>
          <th class="right">Total Sales</th>
          <th class="right">Total Cost</th>
          <th class="right">Profit</th>
        `;
    } else {
        headerRow.innerHTML = `
          <th>Branch</th>
          <th>Product</th>
          <th class="right">Total Qty</th>
          <th class="right">Total Sales</th>
          <th class="right">Total Cost</th>
          <th class="right">Profit</th>
        `;
    }
}

</script>
