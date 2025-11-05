<?php
require_once "db_connection.php";

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
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
//   One row = one product sold
$salesWhere = "";
$params = [];
$types  = "";

if ($role === 'shop' && $branchId > 0) {
  $salesWhere = "WHERE s.branch_id = ?";
  $params[] = $branchId;
  $types .= "i";
}

$salesSql = "
  SELECT 
    s.sale_id,
    s.sale_date,
    s.quantity,
    p.product_name,
    b.branch_name,
    p.selling_price,
    (s.quantity * p.selling_price) AS total
  FROM sales s
  JOIN products p ON s.product_id = p.product_id
  JOIN branches b ON s.branch_id = b.branch_id
  $salesWhere
  ORDER BY s.sale_date DESC, s.sale_id DESC
";

if ($salesWhere) {
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
        <label>Filter:</label>
        <select name="role">
          <option value="">Product</option>
          <option value="Admin">Admin</option>
          <option value="Owner">Owner</option>
          <option value="Renter">Renter</option>
        </select>

        <label>| Sort:</label>
        <select name="branch">
          <option value="">Lowest</option>
          <option value="Manila">Manila</option>
          <option value="Cebu">Cebu</option>
          <option value="Davao">Davao</option>
        </select>
      </div>

      <div class="filter-right">
        <label>Search:</label>
        <input type="text" name="search" placeholder="Search product or branch...">
        <button type="button" class="btn btn-primary" onclick="openSaleModal()">+ Record Sale</button>
      </div>
    </div>
  </form>

  <!-- Sales table -->
  <div class="table-scroll" role="region" aria-label="Sales table">
    <table class="vertical" aria-describedby="caption-vertical">
      <thead>
        <tr>
          <th scope="col">Date</th>
          <th scope="col">Product</th>
          <th scope="col">Branch</th>
          <th scope="col" class="right">Quantity</th>
          <th scope="col" class="right">Unit Price</th>
          <th scope="col" class="right">Total</th>
          <th scope="col">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($salesRes && $salesRes->num_rows > 0): ?>
        <?php while ($r = $salesRes->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($r['sale_date'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="muted"><?= htmlspecialchars($r['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($r['branch_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="right"><?= (int)$r['quantity'] ?></td>
            <td class="right">₱<?= number_format((float)$r['selling_price'], 2) ?></td>
            <td class="right">₱<?= number_format((float)$r['total'], 2) ?></td>
            <td>
              <!-- Edit/Delete/Return not wired yet; you can implement later -->
              <button type="button" class="btn btn-warning btn-sm" disabled>Edit</button>
              <button type="button" class="btn btn-danger btn-sm" disabled>Delete</button>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="7" style="text-align:center;">No sales recorded yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php
// prepare branches array for modal (we already queried above)
$branches = [];
while ($b = $branchesRes->fetch_assoc()) {
  $branches[] = $b;
}
?>

<!-- Record Sale Modal (multi-item POS) -->
<div id="saleModal" class="modal-overlay">
  <div class="modal-box">
    <h4 id="saleModalTitle">RECORD SALE</h4>

    <form id="saleForm" onsubmit="return false;">
      <div class="form-row">
        <label for="saleDate">Date:</label>
        <input type="date" id="saleDate" name="sale_date" required>
      </div>

      <div class="form-row">
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

      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
        <button type="button" class="btn btn-secondary" onclick="addSaleRow()">Add Item</button>
        <div>
          <strong>Grand Total: ₱<span id="saleGrandTotal">0.00</span></strong>
        </div>
      </div>

      <div class="modal-buttons" style="margin-top:15px;">
        <button type="button" class="btn btn-primary" onclick="saveSale()">Confirm</button>
        <button type="button" class="btn btn-danger" onclick="closeSaleModal()">Cancel</button>
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

const USER_BRANCH_ID = <?= (int)$branchId ?>;
const USER_ROLE = "<?= $role ?>";

// ---------- MODAL OPEN/CLOSE ----------
function openSaleModal() {
  document.getElementById('saleForm').reset();

  // default date = today
  document.getElementById('saleDate').value = new Date().toISOString().split('T')[0];

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

    row.querySelector('.unit-price').textContent = price.toFixed(2);
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

function updateRowTotal(row) {
  const price = parseFloat(row.querySelector('input[name="unit_price[]"]').value || '0');
  const qty   = parseInt(row.querySelector('.qty-input').value || '0', 10);
  const total = price * qty;
  row.querySelector('.line-total').textContent = total.toFixed(2);
}

function updateSaleTotals() {
  let grand = 0;
  document.querySelectorAll('#saleItemsBody tr').forEach(row => {
    const price = parseFloat(row.querySelector('input[name="unit_price[]"]').value || '0');
    const qty   = parseInt(row.querySelector('.qty-input').value || '0', 10);
    grand += price * qty;
  });
  document.getElementById('saleGrandTotal').textContent = grand.toFixed(2);
}

// ---------- SAVE SALE (AJAX to record_sale.php) ----------
function saveSale() {
  const saleDate = document.getElementById('saleDate').value;
  const branchSelect = document.getElementById('saleBranch');
  const branchId = (USER_ROLE === 'shop' && USER_BRANCH_ID > 0)
    ? USER_BRANCH_ID
    : branchSelect.value;

  if (!saleDate || !branchId) {
    alert("Please select date and branch.");
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

  fetch('modules/record_sale.php', {
    method: 'POST',
    body: formData
  })
    .then(r => r.text())
    .then(text => {
      console.log("Record sale response:", text);
      let data;
      try { data = JSON.parse(text); }
      catch (e) { throw new Error("Not valid JSON: " + text); }

      if (data.success) {
        alert("Sale recorded successfully!");
        closeSaleModal();
        location.reload();
      } else {
        alert("Error: " + (data.message || "Failed to record sale."));
      }
    })
    .catch(err => {
      console.error("Record sale error:", err);
      alert("Error recording sale. Check console for details.");
    });
}
</script>
