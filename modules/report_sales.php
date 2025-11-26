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

</div>

<script>
let viewMode = 'detailed';
let debounceTimer = null;

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

    fetch("fetch_sales_report.php?" + paramsToQuery(params))
        .then(res => res.text())
        .then(html => {
            document.getElementById("saleReportBody").innerHTML = html;
        })
        .catch(err => {
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
