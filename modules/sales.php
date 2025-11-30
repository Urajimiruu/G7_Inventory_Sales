<?php
  require_once "db_connection.php";

  if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
  }

  $role     = strtolower($_SESSION['role'] ?? '');
  $branchId = (int)($_SESSION['branch_id'] ?? 0);

  // Load branches for dropdown (admin sees all, shop sees only their branch)
  if ($role === 'shop' && $branchId > 0) {
    $branchSql = "SELECT branch_id, branch_name FROM branches WHERE branch_id = ?";
    $stmt = $conn->prepare($branchSql);
    $stmt->bind_param("i", $branchId);
    $stmt->execute();
    $branchesRes = $stmt->get_result();
  } else {
    $branchesRes = $conn->query("SELECT branch_id, branch_name FROM branches ORDER BY branch_name ASC");
  }

  // prepare branches array for modal (we already queried above)
  $branches = [];
  while ($b = $branchesRes->fetch_assoc()) {
    $branches[] = $b;
  }

  // Load products with selling price
  $productSql = "
    SELECT product_id, product_name, selling_price
    FROM products
    ORDER BY product_name ASC
  ";
  $productRes = $conn->query($productSql);
  $products = [];
  while ($row = $productRes->fetch_assoc()) {
    $products[] = $row;
  }

  // Load existing sales list for table
  $salesWhere = "";
  $params = [];
  $types  = "";

  if ($role === 'shop' && $branchId > 0) {
    $salesWhere = "WHERE s.branch_id = ? and s.status != 'returned'";
    $params[] = $branchId;
    $types .= "i";
  }else{
    $salesWhere = "WHERE s.status != 'returned'";
  }

  $salesSql = "
    SELECT 
      s.sale_id,
      s.sale_date,
      s.quantity,
      p.product_name,
      s.status,
      b.branch_name,
      s.unit_price,
      s.customer_type,
      (s.quantity * s.unit_price) AS line_total
    FROM sales s
    JOIN products p ON s.product_id = p.product_id
    JOIN branches b ON s.branch_id = b.branch_id
    $salesWhere
    ORDER BY s.sale_date DESC, s.sale_id DESC
  ";

  if (!empty($params) && !empty($types)) {
    $stmtSales = $conn->prepare($salesSql);
    $stmtSales->bind_param($types, ...$params);
    $stmtSales->execute();
    $salesRes = $stmtSales->get_result();
  } else {
    $salesRes = $conn->query($salesSql);
  }
?>

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

<div class="dashboard">

  <!-- Filters / header -->
  <form id="filterForm" class="filter-form">
    <div class="filters">
      <div class="filter-left">

        <!-- Branch Filter (admin only) -->
        <?php if ($role === 'admin'): ?>
        <label>Branch:</label>
        <select name="branch_id" id="filterBranch" class="small-select" onchange="loadSales()">
            <option value="">All Branches</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= $b['branch_id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <!-- Product Filter -->
        <label>Product:</label>
        <select name="product_id" id="filterProduct" class="small-select" onchange="loadSales()">
            <option value="">All Products</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= $p['product_id'] ?>"><?= htmlspecialchars($p['product_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Date range -->
        <label>From:</label>
        <input type="date" name="from_date" onchange="loadSales()" class="small-date">

        <label>To:</label>
        <input type="date" name="to_date" onchange="loadSales()" class="small-date">
      </div>

      <div class="filter-right">
        <button type="button" class="btn btn-primary" onclick="openSaleModal()">Record Sale</button>

        <!-- IMPORT BUTTON -->
        <button type="button" id="openImportBtn" class="btn btn-secondary" style="margin-left:8px;">
          Import Sales
        </button>
        <!-- hidden file input -->
        <input type="file" id="importFileInput" accept=".xls,.xlsx" style="display:none;" />
      </div>

    </div>
  </form>

  <!-- Sales Container for AJAX updates -->
  <div id="salesContainer">
    <!-- Sales table -->
    <div class="table-scroll" role="region" aria-label="Sales table">
      <!-- Loading Indicator -->
      <div id="salesLoading" class="loading-indicator" style="display: none;">
        <div class="loading-spinner"></div>
        <p>Loading sales data...</p>
      </div>

      <table class="vertical" aria-describedby="caption-vertical">
        <thead>
          <tr>
            <th scope="col" style="width: 200px;">Date</th>
            <th scope="col">Product</th>
            <th scope="col">Branch</th>
            <th scope="col" class="right" style="width: 150px;">Quantity</th>
            <th scope="col" class="right">Unit Price</th>
            <th scope="col" class="right">Line Total</th>
            <th scope="col">Customer Type</th>
            <th scope="col" style="width: 225px;">Action</th>
          </tr>
        </thead>

        <tbody id="salesTableBody">
          <!-- Filled by AJAX -->
        </tbody>
      </table>
    </div>
  </div>

  <!-- Record Sale Modal -->
  <div id="saleModal" class="modal-overlay record-sale-overlay">
    <div class="modal-box record-sale-box">
      <h4 id="saleModalTitle" class="record-sale-title">RECORD SALE</h4>

      <form id="saleForm" onsubmit="return false;">
        <div class="form-row record-sale-field">
          <label for="saleDate">Date:</label>
          <input type="date" id="saleDate" name="sale_date" required>
        </div>

        <div class="form-row record-sale-field">
          <label for="saleBranch">Branch:</label>
          <select id="saleBranch" name="branch_id" required <?= ($role === 'shop' && $branchId > 0) ? 'disabled' : '' ?>>
            <option value="">Select Branch</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= (int)$b['branch_id'] ?>"
                <?= ($role === 'shop' && $branchId == $b['branch_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row record-sale-field">
          <label for="customerType">Customer Type:</label>
          <select id="customerType" name="customer_type" required>
            <option value="Regular" selected>Regular</option>
            <option value="Senior">Senior</option>
            <option value="PWD">PWD</option>
          </select>
        </div>

        <!-- Items table inside modal -->
        <div class="form-row">
          <label>Items:</label>
          <div class="table-scroll" style="max-height:250px;">
            <table class="vertical" id="saleItemsTable">
              <thead>
                <tr>
                  <th>Product</th>
                  <th class="right">Unit Price</th>
                  <th class="right">Quantity</th>
                  <th class="right">Line Total</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="saleItemsBody">
                <!-- rows added dynamically by JS -->
              </tbody>
            </table>
          </div>
        </div>

        <div class="sale-bottom-row">
          <button type="button" class="btn btn-secondary" onclick="addSaleRow()">Add Item</button>
          <div>
            <strong>Grand Total: ₱<span id="saleGrandTotal">0.00</span></strong>
          </div>
        </div>

        <div class="modal-buttons" style="margin-top:15px;">
          <button type="button" id="saleConfirmBtn" class="btn btn-primary" onclick="saveSale()">Confirm</button>
          <button type="button" id="saleCancelBtn" class="btn btn-danger" onclick="closeSaleModal()">Cancel</button>
        </div>

      </form>
    </div>
  </div>

  <!-- Edit Sale Modal -->
  <div id="editSaleModal" class="modal-overlay edit-sale-overlay">
    <div class="modal-box edit-sale-box">
      <h4 id="editSaleModalTitle">EDIT SALE</h4>

      <form id="editSaleForm" onsubmit="return false;">
        <input type="hidden" id="editSaleId" name="sale_id">

        <div class="form-row edit-sale-field">
          <label for="editSaleDate">Date:</label>
          <input type="date" id="editSaleDate" name="sale_date" required>
        </div>

        <div class="form-row edit-sale-field">
          <label for="editSaleBranch">Branch:</label>
          <select id="editSaleBranch" name="branch_id" disabled>
            <option value="">Select Branch</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= (int)$b['branch_id'] ?>"><?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row edit-sale-field">
          <label for="editSaleProduct">Product:</label>
          <select id="editSaleProduct" name="product_id" required>
            <option value="">Select Product</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= (int)$p['product_id'] ?>" data-price="<?= (float)$p['selling_price'] ?>"><?= htmlspecialchars($p['product_name'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row edit-sale-field">
          <label for="editSaleQty">Quantity:</label>
          <input type="number" id="editSaleQty" name="quantity" min="1" required>
        </div>

        <div class="form-row edit-sale-field">
          <label for="editCustomerType">Customer Type:</label>
          <select id="editCustomerType" name="customer_type" required>
            <option value="Regular">Regular</option>
            <option value="Senior">Senior</option>
            <option value="PWD">PWD</option>
          </select>
        </div>

        <div class="modal-buttons">
          <button type="button" class="btn btn-primary" onclick="submitEditSale()">Save Changes</button>
          <button type="button" class="btn btn-danger" onclick="closeEditSaleModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Import Sales Modal -->
  <div id="importModal" class="modal-overlay import-sale-overlay">
    <div class="modal-box import-sale-box" style="max-width:900px;">
      <h4 class="import-sale-title">IMPORT SALES</h4>

      <form id="importForm" onsubmit="return false;">
        <div class="form-row import-sale-field">
          <label for="importDate">Date:</label>
          <input type="date" id="importDate" name="sale_date" required>
        </div>

        <div class="form-row import-sale-field">
          <label for="importBranch">Branch:</label>
          <select id="importBranch" name="branch_id" required <?= ($role === 'shop' && $branchId > 0) ? 'disabled' : '' ?>>
            <option value="">Select Branch</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= (int)$b['branch_id'] ?>"
                <?= ($role === 'shop' && $branchId == $b['branch_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['branch_name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row import-sale-field">
          <label>Items:</label>
        </div>

        <!-- Parsed rows table -->
        <div class="form-row">
          <div class="table-scroll" style="max-height:320px;">
            <table class="vertical" id="importItemsTable">
              <thead>
                <tr>
                  <th>Product Name</th>
                  <th class="right">Unit Price</th>
                  <th class="right">Quantity</th>
                  <th class="right">Customer Type</th>
                  <th class="right">Line Total</th>
                </tr>
              </thead>
              <tbody id="importItemsBody">
                <!-- populated by JS -->
              </tbody>
            </table>
          </div>
        </div>

        <div class="sale-bottom-row" style="margin-top:12px;">
          <div></div>
          <div>
            <strong>Grand Total: ₱<span id="importGrandTotal">0.00</span></strong>
          </div>
        </div>

        <div class="modal-buttons" style="margin-top:15px;">
          <button type="button" id="importConfirmBtn" class="btn btn-primary" onclick="confirmImport()">Confirm Import</button>
          <button type="button" id="importCancelBtn" class="btn btn-danger" onclick="closeImportModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>


</div>

<!-- SheetJS -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
  // ---------- JS DATA FROM PHP ----------
  const PRODUCTS = <?=
    json_encode(array_map(function($p) {
      return [
        'id'    => (int)$p['product_id'],
        'name'  => $p['product_name'],
        'price' => (float)$p['selling_price'],
      ];
    }, $products));
  ?>;
  
  const BRANCHES = <?= json_encode($branches) ?>;

  const USER_BRANCH_ID = <?= (int)$branchId ?>;
  const USER_ROLE = "<?= $role ?>";

  // ---------- DISCOUNT CALCULATION ----------
  function calculateDiscountedPrice(unitPrice, quantity, customerType) {
    const subtotal = unitPrice * quantity;
    if (customerType === 'Senior' || customerType === 'PWD') {
      return subtotal * 0.8; // 20% discount
    }
    return subtotal;
  }

  function getDiscountedUnitPrice(originalPrice, customerType) {
    if (customerType === 'Senior' || customerType === 'PWD') {
      return originalPrice * 0.8; // 20% discount on unit price
    }
    return originalPrice;
  }

  function updateRowTotal(row) {
    const price = parseFloat(row.querySelector('input[name="unit_price[]"]').value || '0');
    const qty   = parseInt(row.querySelector('.qty-input').value || '0', 10);
    const customerType = document.getElementById('customerType').value;
    
    const discountedPrice = getDiscountedUnitPrice(price, customerType);
    const total = calculateDiscountedPrice(price, qty, customerType);
    
    row.querySelector('.unit-price').textContent = discountedPrice.toFixed(2);
    row.querySelector('.line-total').textContent = total.toFixed(2);
  }

  function updateSaleTotals() {
    let grand = 0;
    const customerType = document.getElementById('customerType').value;
    
    document.querySelectorAll('#saleItemsBody tr').forEach(row => {
      const price = parseFloat(row.querySelector('input[name="unit_price[]"]').value || '0');
      const qty   = parseInt(row.querySelector('.qty-input').value || '0', 10);
      grand += calculateDiscountedPrice(price, qty, customerType);
    });
    
    document.getElementById('saleGrandTotal').textContent = grand.toFixed(2);
  }

  // ---------- MODAL OPEN/CLOSE ----------
  function openSaleModal() {
    document.getElementById('saleForm').reset();

    // default date = today
    document.getElementById('saleDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('customerType').value = 'Regular';

    // if shop role, ensure branch select is set correctly
    if (USER_ROLE === 'shop' && USER_BRANCH_ID > 0) {
      const branchSelect = document.getElementById('saleBranch');
      branchSelect.value = USER_BRANCH_ID;
    }

    // clear items
    document.getElementById('saleItemsBody').innerHTML = "";
    document.getElementById('saleGrandTotal').textContent = "0.00";

    // start with 1 row
    addSaleRow();

    document.getElementById('saleModal').classList.add('show');
    document.querySelector('.topbar')?.classList.add('disabled');
  }

  function closeSaleModal() {
    document.getElementById('saleModal').classList.remove('show');
    document.querySelector('.topbar')?.classList.remove('disabled');
  }

  // ---------- RETURN SALE FUNCTION ----------
  function returnSale(saleId) {
    if (confirm('Are you sure you want to return this sale? This will mark the sale as returned.')) {
      const formData = new FormData();
      formData.append('sale_id', saleId);
      
      fetch('modules/return_sale.php', {
        method: 'POST',
        body: formData
      })
        .then(r => r.text())
        .then(text => {
          console.log("Return sale response:", text);
          let data;
          try { data = JSON.parse(text); }
          catch (e) { throw new Error("Not valid JSON: " + text); }

          if (data.success) {
            alert("Sale returned successfully!");
            // Only reload the sales table instead of the entire page
            loadSales();
          } else {
            alert("Error: " + (data.message || "Failed to return sale."));
          }
        })
        .catch(err => {
          console.error("Return sale error:", err);
          alert("Error returning sale. Check console for details.");
        });
    }
  }

  // ---------- ROW MANAGEMENT ----------
  function productOptionsHtml() {
    let html = '<option value="">Select Product</option>';
    PRODUCTS.forEach(p => {
      html += `<option value="${p.id}" data-price="${p.price}">${p.name}</option>`;
    });
    return html;
  }

  function addSaleRow() {
    const tbody = document.getElementById('saleItemsBody');
    const row = document.createElement('tr');

    row.innerHTML = `
      <td>
        <select name="product_id[]" class="product-select">
          ${productOptionsHtml()}
        </select>
      </td>
      <td class="right">
        ₱<span class="unit-price">0.00</span>
        <input type="hidden" name="unit_price[]" value="0">
      </td>
      <td class="right">
        <input type="number" name="quantity[]" class="qty-input" min="1" value="1" style="width:70px;">
      </td>
      <td class="right">
        ₱<span class="line-total">0.00</span>
      </td>
      <td>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeSaleRow(this)">Remove</button>
      </td>
    `;

    tbody.appendChild(row);
    attachRowEvents(row);
    updateSaleTotals();
  }

  function removeSaleRow(btn) {
    const row = btn.closest('tr');
    row.remove();
    updateSaleTotals();
  }

  function attachRowEvents(row) {
    const select = row.querySelector('.product-select');
    const qtyInput = row.querySelector('.qty-input');

    select.addEventListener('change', () => {
      const opt = select.selectedOptions[0];
      const price = parseFloat(opt?.getAttribute('data-price') || '0');

      // Store the original price (not discounted)
      row.querySelector('input[name="unit_price[]"]').value = price.toFixed(2);

      updateRowTotal(row);
      updateSaleTotals();
    });

    qtyInput.addEventListener('input', () => {
      let qty = parseInt(qtyInput.value || '0', 10);
      if (qty < 1) qty = 1;
      qtyInput.value = qty;

      updateRowTotal(row);
      updateSaleTotals();
    });
  }

  // ---------- EDIT / DELETE SALE JS ----------
  function openEditSaleModal(saleId) {
    // fetch sale details
    fetch('modules/get_sale.php?sale_id=' + encodeURIComponent(saleId))
      .then(r => r.text())
      .then(text => {
        let data;
        try { data = JSON.parse(text); } catch(e) { throw new Error('Invalid JSON from get_sale: ' + text); }
        if (!data.success) throw new Error(data.message || 'Failed to load sale');

        const sale = data.sale;
        // fill form
        document.getElementById('editSaleId').value = sale.sale_id;
        document.getElementById('editSaleDate').value = sale.sale_date;
        document.getElementById('editSaleProduct').value = sale.product_id;
        document.getElementById('editSaleQty').value = sale.quantity;
        document.getElementById('editCustomerType').value = sale.customer_type;

        // If user is shop, lock branch to user's branch
        if (USER_ROLE === 'shop') {
          document.getElementById('editSaleBranch').value = USER_BRANCH_ID;
          document.getElementById('editSaleBranch').disabled = true;
        } else {
          document.getElementById('editSaleBranch').disabled = true;
          document.getElementById('editSaleBranch').value = sale.branch_id;
        }

        document.getElementById('editSaleModal').classList.add('show');
        document.querySelector('.topbar')?.classList.add('disabled');
      })
      .catch(err => {
        console.error('openEditSaleModal error', err);
        alert('Failed to load sale details: ' + err.message);
      });
  }

  function closeEditSaleModal() {
    document.getElementById('editSaleModal').classList.remove('show');
    document.querySelector('.topbar')?.classList.remove('disabled');
  }

  function submitEditSale() {
    const saleId = document.getElementById('editSaleId').value;
    const saleDate = document.getElementById('editSaleDate').value;
    const branchId = document.getElementById('editSaleBranch').value;
    const productId = document.getElementById('editSaleProduct').value;
    const qty = parseInt(document.getElementById('editSaleQty').value, 10);
    const customerType = document.getElementById('editCustomerType').value;

    if (!saleId || !saleDate || !branchId || !productId || !qty || qty < 1 || !customerType) {
      alert('Please fill out all fields correctly.');
      return;
    }

    const formData = new FormData();
    formData.append('sale_id', saleId);
    formData.append('sale_date', saleDate);
    formData.append('branch_id', branchId);
    formData.append('product_id', productId);
    formData.append('quantity', qty);
    formData.append('customer_type', customerType);

    setEditButtonsEnabled(false);

    fetch('modules/edit_sale.php', {
      method: 'POST',
      body: formData
    })
      .then(r => r.text())
      .then(text => {
        let data;
        try { 
          data = JSON.parse(text); 
        } 
        catch(e) { 
          setEditButtonsEnabled(true);
          throw new Error('Invalid JSON: ' + text); 
        }

        setEditButtonsEnabled(true);

        if (data.success) {
          alert('Sale updated successfully');
          closeEditSaleModal();
          // Only reload the sales table instead of the entire page
          loadSales();
        } else {
          alert('Update failed: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(err => {
        setEditButtonsEnabled(true);
        console.error('submitEditSale error', err);
        alert('Failed to update sale: ' + err.message);
      });
  }

  function setEditButtonsEnabled(enabled) {
      const saveBtn = document.querySelector('#editSaleModal .btn.btn-primary');
      const cancelBtn = document.querySelector('#editSaleModal .btn.btn-danger');

      if (enabled) {
        if (saveBtn) saveBtn.disabled = false;
        if (cancelBtn) cancelBtn.disabled = false;
        document.body.style.cursor = "default";
      } else {
        if (saveBtn) saveBtn.disabled = true;
        if (cancelBtn) cancelBtn.disabled = true;
        document.body.style.cursor = "wait";
      }
  }

  function deleteSale(saleId) {
    if (!confirm('Delete this sale? This will return the items to inventory.')) return;

    const formData = new FormData();
    formData.append('sale_id', saleId);

    fetch('modules/delete_sale.php', {
      method: 'POST',
      body: formData
    })
      .then(r => r.text())
      .then(text => {
        let data;
        try { data = JSON.parse(text); } catch (e) { throw new Error('Invalid JSON: ' + text); }
        if (data.success) {
          alert('Sale deleted and inventory restored');
          // Only reload the sales table instead of the entire page
          loadSales();
        } else {
          alert('Delete failed: ' + (data.message || 'Unknown'));
        }
      })
      .catch(err => {
        console.error('deleteSale error', err);
        alert('Delete failed: ' + err.message);
      });
  }

  // ---------- SAVE SALE (AJAX to record_sale.php) ----------
  function saveSale() {
    const saleDate = document.getElementById('saleDate').value;
    const branchSelect = document.getElementById('saleBranch');
    const customerType = document.getElementById('customerType').value;
    const branchId = (USER_ROLE === 'shop' && USER_BRANCH_ID > 0)
      ? USER_BRANCH_ID
      : branchSelect.value;

    if (!saleDate || !branchId || !customerType) {
      alert("Please select date, branch, and customer type.");
      return;
    }

    const rows = document.querySelectorAll('#saleItemsBody tr');
    if (!rows.length) {
      alert("Please add at least one item.");
      return;
    }

    const formData = new FormData();
    formData.append('sale_date', saleDate);
    formData.append('branch_id', branchId);
    formData.append('customer_type', customerType);

    rows.forEach(row => {
      const productId = row.querySelector('.product-select').value;
      const qty       = row.querySelector('.qty-input').value;
      const price     = row.querySelector('input[name="unit_price[]"]').value;

      if (productId && qty > 0) {
        formData.append('product_id[]', productId);
        formData.append('quantity[]', qty);
        formData.append('unit_price[]', price);
      }
    });

    if (!formData.getAll('product_id[]').length) {
      alert("Please select products and quantities.");
      return;
    }

    setSaleButtonsEnabled(false);

    fetch('modules/record_sale.php', {
      method: 'POST',
      body: formData
    })
      .then(r => r.text())
      .then(text => {
        console.log("Record sale response:", text);
        let data;
        try { data = JSON.parse(text); }
        catch (e) { 
          setSaleButtonsEnabled(true); 
          throw new Error("Not valid JSON: " + text); 
        }
        setSaleButtonsEnabled(true); 

        if (data.success) {
          alert("Sale recorded successfully!");
          closeSaleModal();
          // Only reload the sales table instead of the entire page
          loadSales();
        } else {
          alert("Error: " + (data.message || "Failed to record sale."));
        }
      })
      .catch(err => {
        setSaleButtonsEnabled(true); 
        console.error("Record sale error:", err);
        alert("Error recording sale. Check console for details.");
      });
  }

  // ---------- LOADING INDICATOR FUNCTIONS ----------
  function showLoadingIndicator() {
    const tableBody = document.getElementById('salesTableBody');
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

  function loadSales(page = 1) {
    const form = document.getElementById("filterForm");
    const formData = new FormData(form);
    formData.append('page', page);
    const params = new URLSearchParams(formData);

    // Show loading state
    showLoadingIndicator();

    fetch("modules/list_sales.php?" + params.toString())
      .then(res => {
        if (!res.ok) {
          throw new Error('Network response was not ok');
        }
        return res.text();
      })
      .then(html => {
        document.getElementById("salesTableBody").innerHTML = html;
        hideLoadingIndicator();
      })
      .catch(error => {
        console.error('Error loading sales:', error);
        document.getElementById("salesTableBody").innerHTML = `
          <tr>
            <td colspan="8" class="error-message">
              <div class="error-content">
                <span class="error-icon"></span>
                <div>
                  <strong>Error loading sales data</strong>
                  <p>Please check your connection and try again</p>
                </div>
              </div>
            </td>
          </tr>
        `;
        hideLoadingIndicator();
      });
  }

  // ---------- INITIALIZATION ----------
  document.addEventListener('DOMContentLoaded', function() {
    const customerTypeSelect = document.getElementById('customerType');
    if (customerTypeSelect) {
      customerTypeSelect.addEventListener('change', function() {
        document.querySelectorAll('#saleItemsBody tr').forEach(row => {
          updateRowTotal(row);
        });
        updateSaleTotals();
      });
    }
    
    // Show loading on initial load
    showLoadingIndicator();
    
    // Small delay to show loading state (optional)
    setTimeout(() => {
      loadSales();
    }, 100);
  });

  function setSaleButtonsEnabled(enabled) {
    const confirmBtn = document.getElementById('saleConfirmBtn');
    const cancelBtn  = document.getElementById('saleCancelBtn');

    if (enabled) {
      confirmBtn.disabled = false;
      cancelBtn.disabled = false;
      document.body.style.cursor = "default";
    } else {
      confirmBtn.disabled = true;
      cancelBtn.disabled = true;
      document.body.style.cursor = "wait";
    }
  }

  // ---------- IMPORT SALES JS ----------

  // Allowed customer types
  const ALLOWED_CUSTOMER_TYPES = ['Regular', 'Senior', 'PWD'];

  // Helper: find product in PRODUCTS by name (case-insensitive, trims)
  function findProductByName(name) {
    if (!name) return null;
    const n = name.trim().toLowerCase();
    return PRODUCTS.find(p => p.name.trim().toLowerCase() === n) || null;
  }

  // File input handling
  const importBtn = document.getElementById('openImportBtn');
  const importFileInput = document.getElementById('importFileInput');

  importBtn.addEventListener('click', () => importFileInput.click());

  importFileInput.addEventListener('change', (ev) => {
    const file = ev.target.files[0];
    if (!file) return;

    // Validate extension
    const allowedExt = ['xlsx','xls'];
    const ext = file.name.split('.').pop().toLowerCase();
    if (!allowedExt.includes(ext)) {
      alert('Please select an Excel file (.xls or .xlsx).');
      importFileInput.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
      const data = new Uint8Array(e.target.result);
      const wb = XLSX.read(data, { type: 'array' });

      // Use first sheet
      const firstSheetName = wb.SheetNames[0];
      const ws = wb.Sheets[firstSheetName];

      // Convert to JSON with header auto-detection (assume headers: Product Name, Quantity, Customer Type)
      const rows = XLSX.utils.sheet_to_json(ws, { defval: '' });

      if (!rows.length) {
        alert('Excel file is empty.');
        return;
      }

      // Parse rows and validate
      const validRows = [];
      const invalidProducts = [];
      const invalidCustomerTypes = [];

      rows.forEach((r, idx) => {
        // Attempt common header keys (case-insensitive)
        // Accept keys like: Product Name, Product, product_name, Name
        const keys = Object.keys(r);
        let pname = '';
        let qty = '';
        let ctype = '';

        // find product name key
        for (const k of keys) {
          if (/product/i.test(k) || /name/i.test(k) && /product/i.test(k) ) {
            // prefer exact product name columns — but fallback to any containing "product" or "name"
            pname = r[k];
          }
        }
        // if still empty, try first column
        if (!pname) pname = r[keys[0]];

        // Quantity
        const qtyKey = keys.find(k => /qty|quantity/i.test(k));
        qty = qtyKey ? r[qtyKey] : (keys[1] ? r[keys[1]] : '');

        // Customer Type
        const ctKey = keys.find(k => /customer/i.test(k) || /type/i.test(k));
        ctype = ctKey ? r[ctKey] : (keys[2] ? r[keys[2]] : '');

        // Normalize
        pname = String(pname || '').trim();
        ctype = String(ctype || '').trim();
        qty = Number(String(qty || '').toString().trim()) || 0;

        // find product in DB copy (PRODUCTS)
        const prod = findProductByName(pname);

        if (!prod) {
          invalidProducts.push(pname || `Row ${idx+2}`);
          return; // skip listing non-existent product
        }

        if (!ALLOWED_CUSTOMER_TYPES.includes(ctype)) {
          invalidCustomerTypes.push(ctype || `Row ${idx+2}`);
          return; // skip invalid customer types
        }

        if (qty <= 0) {
          // skip zero qty rows silently (or we can warn — here we skip)
          return;
        }

        // Compute unit price from PRODUCTS price
        const unitPrice = prod.price || 0;
        const lineTotal = (unitPrice * qty);

        validRows.push({
          product_id: prod.id,
          product_name: prod.name,
          unit_price: unitPrice,
          quantity: qty,
          customer_type: ctype,
          line_total: lineTotal
        });
      });

      // Show alerts for invalid items (if any)
      let alertMsgs = [];
      if (invalidProducts.length) alertMsgs.push('The following products are not in the database and were skipped:\n' + invalidProducts.join(', '));
      if (invalidCustomerTypes.length) alertMsgs.push('The following customer types are invalid and were skipped:\n' + invalidCustomerTypes.join(', '));
      if (alertMsgs.length) alert(alertMsgs.join('\n\n'));

      if (!validRows.length) {
        alert('No valid rows to import after validation.');
        return;
      }

      // Populate import modal table
      populateImportTable(validRows);

      // open modal and prefill date & branch (date default to today)
      document.getElementById('importDate').value = new Date().toISOString().split('T')[0];
      if (USER_ROLE === 'shop' && USER_BRANCH_ID > 0) {
        document.getElementById('importBranch').value = USER_BRANCH_ID;
      }

      openImportModal();
    };

    reader.readAsArrayBuffer(file);
  });

  // Populate table function
  let IMPORT_ROWS = []; // hold parsed rows
  function populateImportTable(rows) {
    IMPORT_ROWS = rows; // store for submission
    const tbody = document.getElementById('importItemsBody');
    tbody.innerHTML = '';
    let grand = 0;

    rows.forEach(r => {
      let unit = Number(r.unit_price);
      if (r.customer_type === "Senior" || r.customer_type === "PWD") {
          unit = unit * 0.80;
      }

      const lineTotal = unit * r.quantity;
      grand += lineTotal;

      tbody.insertAdjacentHTML('beforeend', `
        <tr data-product-id="${r.product_id}">
          <td>${escapeHtml(r.product_name)}</td>
          <td class="right">₱${unit.toFixed(2)}</td>
          <td class="right">${r.quantity}</td>
          <td class="right">${escapeHtml(r.customer_type)}</td>
          <td class="right">₱${lineTotal.toFixed(2)}</td>
        </tr>
      `);
    });

    document.getElementById('importGrandTotal').textContent = grand.toFixed(2);
  }

  // open/close import modal
  function openImportModal() {
    document.getElementById('importModal').classList.add('show');
    document.querySelector('.topbar')?.classList.add('disabled');
  }

  function closeImportModal() {
    document.getElementById('importModal').classList.remove('show');
    document.querySelector('.topbar')?.classList.remove('disabled');
    // clear file input
    importFileInput.value = '';
    IMPORT_ROWS = [];
    document.getElementById('importItemsBody').innerHTML = '';
    document.getElementById('importGrandTotal').textContent = '0.00';
  }

  // Escape helper
  function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, function(m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[m]; });
  }

  // Confirm import: validate date/branch, check stock, submit
  function confirmImport() {
    const date = document.getElementById('importDate').value;
    let branch = document.getElementById('importBranch').value;
    if (USER_ROLE === 'shop' && USER_BRANCH_ID > 0) branch = USER_BRANCH_ID;

    if (!date || !branch) {
      alert('Please choose Date and Branch before confirming import.');
      return;
    }

    if (!IMPORT_ROWS.length) {
      alert('No rows to import.');
      return;
    }

    // Prepare product ids and quantities to check stock in backend
    const payload = {
      branch_id: branch,
      products: IMPORT_ROWS.map(r => ({ product_id: r.product_id, qty: r.quantity }))
    };

    // Check stocks
    fetch('modules/check_stock.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
      if (!data.success) throw new Error(data.message || 'Stock check failed.');

      // data.stocks => { product_id: availableQty, ... }
      const shortages = [];
      IMPORT_ROWS.forEach(r => {
        const avail = Number(data.stocks[r.product_id] || 0);
        if (r.quantity > avail) {
          shortages.push(`${r.product_name} (need ${r.quantity}, available ${avail})`);
        }
      });

      if (shortages.length) {
        alert('Insufficient stock for these products in selected branch:\n' + shortages.join('\n'));
        return; // keep modal open for review
      }

      // All good -> submit import
      submitImport(date, branch, IMPORT_ROWS);
    })
    .catch(err => {
      console.error('Stock check error', err);
      alert('Error checking stock: ' + err.message);
    });
  }

  // Submit import to server
  function submitImport(date, branchId, rows) {
    setImportButtonsEnabled(false);

    const fd = new FormData();
    fd.append('sale_date', date);
    fd.append('branch_id', branchId);

    rows.forEach(r => {
        let unit = Number(r.unit_price);
        if (r.customer_type === "Senior" || r.customer_type === "PWD") {
            unit = unit * 0.80;
        }
        fd.append('product_id[]', r.product_id);
        fd.append('quantity[]', r.quantity);
        fd.append('unit_price[]', unit);      // <-- send discounted!
        fd.append('customer_type[]', r.customer_type);
    });

    fetch('modules/import_sales.php', {
      method: 'POST',
      body: fd
    })
    .then(r => r.text())
    .then(txt => {
      let data;
      try { data = JSON.parse(txt); } catch(e) { throw new Error('Invalid JSON: ' + txt); }

      setImportButtonsEnabled(true);

      if (data.success) {
        alert('Import successful!');
        closeImportModal();
        loadSales();
      } else {
        alert('Import failed: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(err => {
      setImportButtonsEnabled(true);
      console.error('Import submit error', err);
      alert('Error submitting import: ' + err.message);
    });
  }

  function setImportButtonsEnabled(enabled) {
    const saveBtn = document.getElementById('importConfirmBtn');
    const cancelBtn = document.getElementById('importCancelBtn');
    if (saveBtn) saveBtn.disabled = !enabled;
    if (cancelBtn) cancelBtn.disabled = !enabled;
    document.body.style.cursor = enabled ? 'default' : 'wait';
  }

</script>