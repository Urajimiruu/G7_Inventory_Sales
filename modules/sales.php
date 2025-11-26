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
      </div>
    </div>
  </form>

  <!-- Sales table -->
  <div class="table-scroll" role="region" aria-label="Sales table">
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
          <select id="editSaleBranch" name="branch_id" required>
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
          <button type="button" class="btn btn-primary" onclick="submitEditSale()">Save changes</button>
          <button type="button" class="btn btn-danger" onclick="closeEditSaleModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

</div>

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
            location.reload();
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
          document.getElementById('editSaleBranch').disabled = false;
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

  // submit edit
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

    fetch('modules/edit_sale.php', {
      method: 'POST',
      body: formData
    })
      .then(r => r.text())
      .then(text => {
        let data;
        try { data = JSON.parse(text); } catch(e) { throw new Error('Invalid JSON: ' + text); }
        if (data.success) {
          alert('Sale updated successfully');
          closeEditSaleModal();
          location.reload();
        } else {
          alert('Update failed: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(err => {
        console.error('submitEditSale error', err);
        alert('Failed to update sale: ' + err.message);
      });
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
          location.reload();
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
          setSaleButtonsEnabled(true); // ✅ re-enable
          throw new Error("Not valid JSON: " + text); 
        }
        setSaleButtonsEnabled(true); // ✅ re-enable

        if (data.success) {
          alert("Sale recorded successfully!");
          closeSaleModal();
          location.reload();
        } else {
          alert("Error: " + (data.message || "Failed to record sale."));
        }
      })
      .catch(err => {
        setSaleButtonsEnabled(true); // ✅ re-enable
        console.error("Record sale error:", err);
        alert("Error recording sale. Check console for details.");
      });
  }

  function loadSales(page = 1) {
    const form = document.getElementById("filterForm");
    const formData = new FormData(form);
    formData.append('page', page);
    const params = new URLSearchParams(formData);

    fetch("modules/list_sales.php?" + params.toString())
        .then(res => res.text())
        .then(html => {
            document.getElementById("salesTableBody").innerHTML = html;
        });
}

  // Add event listener for customer type change
  document.addEventListener('DOMContentLoaded', function() {
    const customerTypeSelect = document.getElementById('customerType');
    if (customerTypeSelect) {
      customerTypeSelect.addEventListener('change', function() {
        // Update all row totals when customer type changes
        document.querySelectorAll('#saleItemsBody tr').forEach(row => {
          updateRowTotal(row);
        });
        updateSaleTotals();
      });
    }
    loadSales();
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


</script>