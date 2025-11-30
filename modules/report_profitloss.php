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
      <thead>
        <tr>
          <th>Branch</th>
          <th>Product</th>
          <th class="right">Total Quantity Sold</th>
          <th class="right">Total Sales</th>
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

<script>
let currentProfitPage = 1;
const profitLimit = 50; // change this if you want more rows per page

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

    fetch('fetch_profitloss_report.php?' + params.toString())
    .then(res => res.text())
    .then(response => {

        const parts = response.split("<!--PAGINATION-->");
        const tableRows = parts[0];
        const paginationHtml = parts[1] || "";

        document.getElementById('profitLossBody').innerHTML = tableRows;
        document.getElementById('profitLossPagination').innerHTML = paginationHtml;

        // Read totals
        const temp = document.createElement("div");
        temp.innerHTML = tableRows;
        const totals = temp.querySelector("#profitLossTotalsData");

        if (totals) {
            const qty    = Number(totals.dataset.totalQty);
            const sales  = Number(totals.dataset.totalSales);
            const cost   = Number(totals.dataset.totalCost);
            const profit = Number(totals.dataset.totalProfit);

            document.getElementById("profitLossTotals").innerHTML = `
                <div class="pl-total-card-wrapper">
                    <div class="pl-total-card">
                        <b>Total Qty Sold</b><br>
                        <span class="inv-total-number">${qty.toLocaleString()}</span>
                    </div>
                    <div class="pl-total-card">
                        <b>Total Sales</b><br>
                        <span class="inv-total-number">₱${sales.toLocaleString()}</span>
                    </div>
                    <div class="pl-total-card">
                        <b>Total Cost</b><br>
                        <span class="inv-total-number">₱${cost.toLocaleString()}</span>
                    </div>
                    <div class="pl-total-card">
                        <b>Total Profit</b><br>
                        <span class="inv-total-number">₱${profit.toLocaleString()}</span>
                    </div>
                </div>
            `;
        }
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
