<?php
require_once "db_connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);
?>

<div class="dashboard inv-report-dashboard">

  <!-- Search + Export -->
  <div class="inv-report-header">

      <div class="inv-filters-left">
          <!-- Branch Filter -->
          <label><b>Branch:</b></label>
          <select id="filterBranch" onchange="loadInventory()">
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
          <select id="filterProduct" onchange="loadInventory()">
              <option value="">All Products</option>
              <?php
              $pq = $conn->query("SELECT product_id, product_name FROM products ORDER BY product_name");
              while ($p = $pq->fetch_assoc()) {
                  echo "<option value='{$p['product_id']}'>{$p['product_name']}</option>";
              }
              ?>
          </select>

          <!-- Sort by Stock -->
          <label><b>| Sort:</b></label>
          <select id="sortStock" onchange="loadInventory()">
              <option value="">Sort By Stock</option>
              <option value="asc">Lowest → Highest</option>
              <option value="desc">Highest → Lowest</option>
          </select>
      </div>

      <div class="inv-filters-right">
          <label><b>Search:</b></label>
          <input type="text" id="invSearch" placeholder="Search branch or product..." onkeyup="loadInventory()">
          <button type="button" class="btn btn-success inv-export-btn" onclick="exportInventory()">Export</button>
      </div>

  </div>

  <!-- Table -->
  <div class="table-scroll inv-report-table-wrapper">
    <table class="user-table inv-report-table">
      <thead>
        <tr>
          <th>Branch</th>
          <th>Product</th>
          <th>Unit</th>
          <th class="right">Cost Price</th>
          <th class="right">Selling Price</th>
          <th class="right">Quantity</th>
          <th class="right">Total Cost</th>
          <th class="right">Potential Revenue</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="invReportBody">
        <!-- AJAX rows will be loaded here -->
      </tbody>
    </table>
  </div>

  <div id="invReportPagination"></div>
  <div id="invReportTotals"></div>
</div>

<script>

    let currentProfitPage = 1;
    const profitLimit = 50; // change this if you want more rows per page

    function loadInventory(page = 1) {
        currentProfitPage = page;

        const search  = document.getElementById('invSearch').value.trim();
        const branch  = document.getElementById('filterBranch').value;
        const product = document.getElementById('filterProduct').value;
        const sort    = document.getElementById('sortStock').value;

        const params = new URLSearchParams({
            search: search,
            branch: branch,
            product: product,
            sort: sort,
            page: page,
            limit: profitLimit
        });

        fetch('fetch_inventory_report.php?' + params.toString())
            .then(res => res.text())
            .then(response => {
                // response contains TABLE + PAGINATION (split by delimiter)
                const [tableRows, paginationHtml] = response.split("<!--PAGINATION-->");
                document.getElementById('invReportBody').innerHTML = tableRows;
                document.getElementById('invReportPagination').innerHTML = paginationHtml;

                // Read totals embedded in the response
                const tempDiv = document.createElement("div");
                tempDiv.innerHTML = tableRows;
                const totalsDiv = tempDiv.querySelector("#invTotalsData");

                if (totalsDiv) {
                    const totalQty  = totalsDiv.dataset.totalQty;
                    const totalCost = parseFloat(totalsDiv.dataset.totalCost).toFixed(2);
                    const totalRev  = parseFloat(totalsDiv.dataset.totalRev).toFixed(2);

                    document.getElementById("invReportTotals").innerHTML = `
                        <div class="inv-total-card-wrapper">
                            <div class="inv-total-card">
                                <b>Total Quantity</b><br>
                                <span class="inv-total-number">${Number(totalQty).toLocaleString()}</span>
                            </div>
                            <div class="inv-total-card">
                                <b>Total Cost</b><br>
                                <span class="inv-total-number">₱${Number(totalCost).toLocaleString()}</span>
                            </div>
                            <div class="inv-total-card">
                                <b>Potential Revenue</b><br>
                                <span class="inv-total-number">₱${Number(totalRev).toLocaleString()}</span>
                            </div>
                        </div>
                    `;
                }
            });
    }

    // Placeholder for export logic
    function exportInventory() {
        const search  = document.getElementById('invSearch').value.trim();
        const branch  = document.getElementById('filterBranch').value;
        const product = document.getElementById('filterProduct').value;
        const sort    = document.getElementById('sortStock').value;

        const params = new URLSearchParams({
            search: search,
            branch: branch,
            product: product,
            sort: sort
        });

        // Open export in new tab
        window.open('export_inventory_pdf.php?' + params.toString(), '_blank');
    }

    loadInventory();
    
</script>
