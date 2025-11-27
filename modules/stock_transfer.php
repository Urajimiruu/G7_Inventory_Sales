<?php
// modules/stock_transfer.php
require_once "db_connection.php";

// Load products
$productsRes = $conn->query("
  SELECT product_id, product_name
  FROM products
  ORDER BY product_name ASC
");

// Load products again for modal (separate result set)
$productsRes2 = $conn->query("
  SELECT product_id, product_name
  FROM products
  ORDER BY product_name ASC
");


// Load branches
$branchesRes = $conn->query("
  SELECT branch_id, branch_name
  FROM branches
  ORDER BY branch_name ASC
");
?>

<div class="dashboard">

  <!-- Filters -->
  <form id="filterForm" class="filter-form">
      <div class="filters">
          <div class="filter-left">

              <label>Product:</label>
              <select name="product_id" id="filterProduct" onchange="loadTransfers()">
                  <option value="">All Products</option>
                  <?php while ($p = $productsRes->fetch_assoc()): ?>
                      <option value="<?= $p['product_id'] ?>">
                          <?= htmlspecialchars($p['product_name']) ?>
                      </option>
                  <?php endwhile; ?>
              </select>

              <label>From:</label>
              <input type="date" name="from_date" onchange="loadTransfers()">

              <label>To:</label>
              <input type="date" name="to_date" onchange="loadTransfers()">

          </div>

          <div class="filter-right">
              <button type="button" class="btn btn-primary" onclick="openTransferModal()">Transfer</button>
          </div>
      </div>
  </form>

  <!-- Transfer Table -->
  <div class="table-scroll" role="region" aria-label="Products table">
      <table class="vertical">
          <thead>
              <tr>
                  <th>Product</th>
                  <th class="right" style="width: 200px;">Quantity</th>
                  <th class="right">Branch</th>
                  <th class="right" style="width: 200px;">Transfer Date</th>
              </tr>
          </thead>

          <tbody id="transferTableBody">
              <!-- Loaded via AJAX -->
          </tbody>
      </table>
  </div>


  <!-- Transfer Modal -->
  <div id="transferModal" class="modal-overlay transfer-overlay">
    <div class="modal-box transfer-box">
      <h4 id="transferModalTitle">TRANSFER STOCK</h4>

      <form id="transferForm" onsubmit="return false;">

        <div class="form-row transfer-field">
          <label>Date:</label>
          <input type="date" id="transferDate" name="date" required>
        </div>

        <div class="form-row transfer-field">
          <label>Product:</label>
          <select id="transferProduct" name="product_id" required>
              <option value="">Select Product</option>
              <?php while ($p = $productsRes2->fetch_assoc()): ?>
                <option value="<?= (int)$p['product_id'] ?>">
                  <?= htmlspecialchars($p['product_name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endwhile; ?>
          </select>
        </div>

        <div class="form-row transfer-field">
          <label>Branch:</label>
          <select id="transferBranch" name="branch_id" required>
            <option value="">Select Branch</option>
            <?php while ($b = $branchesRes->fetch_assoc()): ?>
              <option value="<?= (int)$b['branch_id'] ?>">
                <?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="form-row transfer-field">
          <label>Current Stock (Main):</label>
          <input type="number" id="currentStock" name="current_stock" readonly>
        </div>

        <div class="form-row transfer-field">
          <label>Quantity to Transfer:</label>
          <input type="number" id="transferQty" name="quantity" min="1" required>
        </div>

        <div class="modal-buttons">
          <button type="button" class="btn btn-primary" onclick="saveTransfer()">Confirm</button>
          <button type="button" class="btn btn-danger" onclick="closeTransferModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

</div>

<script>

  // function loadTransfers() {
  //     const form = document.getElementById("filterForm");
  //     const formData = new FormData(form);
  //     const params = new URLSearchParams(formData);

  //     fetch("modules/list_stock_transfers.php?" + params.toString())
  //         .then(res => res.text())
  //         .then(html => {
  //             document.querySelector("#transferTableBody").innerHTML = html;
  //         });
  // }

  let currentPage = 1;
  const PAGE_LIMIT = 10;

function buildQueryParams(page = 1) {
    const form = document.getElementById("filterForm");
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);

    params.set("page", page);
    params.set("limit", PAGE_LIMIT);
    return params.toString();
}

function loadTransfers(page = 1) {
  const form = document.getElementById("filterForm");
  const formData = new FormData(form);
  formData.append('page', page); // send current page
  const params = new URLSearchParams(formData);

  fetch("modules/list_stock_transfers.php?" + params.toString())
    .then(res => res.text())
    .then(html => {
        document.getElementById("transferTableBody").innerHTML = html;
    })
    .catch(err => {
        console.error("Error loading inventory:", err);
        document.getElementById("transferTableBody").innerHTML = 
          "<tr><td colspan='6' style='text-align:center;'>Error loading data</td></tr>";
    });
}
  
  document.addEventListener("DOMContentLoaded", loadTransfers);

  // open modal
  function openTransferModal() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('transferDate').value = today;

    document.getElementById('transferProduct').value = '';
    document.getElementById('transferBranch').value = '';
    document.getElementById('currentStock').value = '';
    document.getElementById('transferQty').value = '';

    document.getElementById('transferModal').classList.add('show');
    document.querySelector('.topbar')?.classList.add('disabled');
  }

  function closeTransferModal() {
    document.getElementById('transferModal').classList.remove('show');
    document.querySelector('.topbar')?.classList.remove('disabled');
  }

  // when product changes, fetch current stock from maininventory
  document.addEventListener('DOMContentLoaded', () => {
    const productSelect = document.getElementById('transferProduct');
    const stockInput = document.getElementById('currentStock');

    productSelect.addEventListener('change', () => {
      const productId = productSelect.value;
      if (!productId) {
        stockInput.value = '';
        return;
      }

      fetch('modules/get_main_stock.php?product_id=' + encodeURIComponent(productId))
        .then(r => r.text())
        .then(text => {
          console.log('Stock response:', text);
          let data;
          try { data = JSON.parse(text); }
          catch (e) { throw new Error('Invalid JSON: ' + text); }

          stockInput.value = data.quantity ?? 0;
        })
        .catch(err => {
          console.error('Stock fetch error:', err);
          stockInput.value = 0;
        });
    });
  });

  // save transfer
  function saveTransfer() {
    const date   = document.getElementById('transferDate').value;
    const prodId = document.getElementById('transferProduct').value;
    const branch = document.getElementById('transferBranch').value;
    const qty    = parseInt(document.getElementById('transferQty').value, 10);

    if (!date || !prodId || !branch || isNaN(qty) || qty < 1) {
      alert('Please fill out all fields with valid values.');
      return;
    }

    const formData = new FormData();
    formData.append('product_id', prodId);
    formData.append('branch_id', branch);
    formData.append('quantity', qty);
    formData.append('date', date);

    fetch('modules/transfer_stock.php', {
      method: 'POST',
      body: formData
    })
      .then(r => r.text())
      .then(text => {
        console.log('Raw transfer response:', text);
        let data;
        try { data = JSON.parse(text); }
        catch (e) { throw new Error('Not valid JSON: ' + text); }

        if (data.success) {
          alert('Stock transferred successfully!');
          location.reload();
        } else {
          alert('Transfer failed: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(err => {
        console.error('Transfer error:', err);
        alert('Transfer failed. Check console for details.');
      });
  }



</script>
