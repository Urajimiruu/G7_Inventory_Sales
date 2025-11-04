<?php
// modules/stock_transfer.php
require_once "db_connection.php";

// Load products
$productsRes = $conn->query("
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

// Optional: recent transfers to show in the table
$transfersRes = $conn->query("
  SELECT 
    st.transfer_id,
    p.product_name,
    b.branch_name,
    st.quantity,
    st.transfer_date
  FROM stocktransfers st
  JOIN products p ON st.product_id = p.product_id
  JOIN branches b ON st.branch_id = b.branch_id
  ORDER BY st.transfer_date DESC, st.transfer_id DESC
  LIMIT 50
");
?>

<div class="dashboard">

  <!-- 🔍 Filters (UI only for now) -->
  <form id="filterForm" class="filter-form">
    <div class="filters">
      <div class="filter-left">
        <label>Filter:</label>
        <select name="filter1">
          <option value=""></option>
          <option value="Product">Product</option>
          <option value="Branch">Branch</option>
        </select>

        <label>| From:</label>
        <select name="fromBranch">
          <option value=""></option>
          <?php
          $branchesRes2 = $conn->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name ASC");
          while ($b = $branchesRes2->fetch_assoc()):
          ?>
            <option value="<?= (int)$b['branch_id'] ?>">
              <?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endwhile; ?>
        </select>

        <label>To:</label>
        <select name="toBranch">
          <option value=""></option>
          <?php
          $branchesRes3 = $conn->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name ASC");
          while ($b = $branchesRes3->fetch_assoc()):
          ?>
            <option value="<?= (int)$b['branch_id'] ?>">
              <?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="filter-right">
        <button type="button" class="btn btn-primary" onclick="openTransferModal()">
          Transfer
        </button>
      </div>
    </div>
  </form>

  <!-- 📋 Recent transfers -->
  <div class="table-scroll" role="region" aria-label="Products table">
    <table class="vertical" aria-describedby="caption-vertical">
      <thead>
        <tr>
          <th scope="col">Product</th>
          <th scope="col" class="right">Quantity</th>
          <th scope="col" class="right">Branch</th>
          <th scope="col" class="right">Transfer Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($transfersRes && $transfersRes->num_rows > 0): ?>
          <?php while ($row = $transfersRes->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="right"><?= (int)$row['quantity'] ?></td>
              <td class="right"><?= htmlspecialchars($row['branch_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="right"><?= htmlspecialchars($row['transfer_date'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="4" style="text-align:center;">No transfers yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- 🧩 Transfer Modal -->
  <div id="transferModal" class="modal-overlay">
    <div class="modal-box">
      <h4 id="transferModalTitle">TRANSFER STOCK</h4>

      <form id="transferForm" onsubmit="return false;">

        <div class="form-row">
          <label>Date:</label>
          <input type="date" id="transferDate" name="date" required>
        </div>

        <div class="form-row">
          <label>Product:</label>
          <select id="transferProduct" name="product_id" required>
            <option value="">Select Product</option>
            <?php while ($p = $productsRes->fetch_assoc()): ?>
              <option value="<?= (int)$p['product_id'] ?>">
                <?= htmlspecialchars($p['product_name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="form-row">
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

        <div class="form-row">
          <label>Current Stock (Main):</label>
          <input type="number" id="currentStock" name="current_stock" readonly>
        </div>

        <div class="form-row">
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
// open modal (no itemid, we choose product from dropdown)
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
    alert('⚠️ Please fill out all fields with valid values.');
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
        alert('✅ Stock transferred successfully!');
        location.reload();
      } else {
        alert('❌ Transfer failed: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(err => {
      console.error('Transfer error:', err);
      alert('❌ Transfer failed. Check console for details.');
    });
}
</script>
