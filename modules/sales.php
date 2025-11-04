<div class="dashboard">

  <!-- 🔍 Filters -->
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
        <input type="text" name="search" placeholder="Search user or branch...">
        <button type="button" class="btn btn-primary" onclick="openSaleModal()">+ Add User</button>
      </div>
    </div>
  </form>

  <!-- 📋 Table -->

    <div class="table-scroll" role="region" aria-label="Products table">
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
          <tr>
            <td>11/4/2025</td>
            <td class="muted">Wireless Mouse</td>
            <td>Batangas</td>
            <td class="right">67</td>
            <td class="right">$67.00</td>
            <td class="right"> $947695.00</td>
            <td><button type="button" class="btn btn-warning btn-sm" onclick="openSaleModal()">Edit</button>
            <button type="button" class="btn btn-danger btn-sm">Delete</button>
            <button type="button" class="btn btn-success" onclick="saveSale()">Return</button>
          </tr>
          <tr>
            <td>11/4/2025</td>
            <td class="muted">Keyboard</td>
            <td>Lemery</td>
            <td class="right">67</td>
            <td class="right">$89.00</td>
            <td class="right">$354834.00</td>
            <td><button type="button" class="btn btn-warning btn-sm" onclick="openSaleModal()">Edit</button>
            <button type="button" class="btn btn-danger btn-sm">Delete</button>
            <button type="button" class="btn btn-success" onclick="saveSale()">Return</button>
          </tr>
          <tr>
            <td>11/4/2025</td>
            <td class="muted">Monitor</td>
            <td>Lipa</td>
            <td class="right">67</td>
            <td class="right">$50</td>
            <td class="right">$5465654.00</td>
            <td><button type="button" class="btn btn-warning btn-sm" onclick="openSaleModal()">Edit</button>
            <button type="button" class="btn btn-danger btn-sm">Delete</button>
            <button type="button" class="btn btn-success" onclick="saveSale()">Return</button>
          </tr>
        </tbody>
      </table>
    </div>
  
<!-- 🧾 Record/Edit Sale Modal -->
<div id="saleModal" class="modal-overlay">
  <div class="modal-box">
    <h4 id="saleModalTitle">RECORD SALE / EDIT SALE</h4>

    <form id="saleForm">
      <div class="form-row">
        <label for="saleDate">Date:</label>
        <input type="date" id="saleDate" name="sale_date" required>
      </div>

      <div class="form-row">
        <label for="saleBranch">Branch:</label>
        <select id="saleBranch" name="branch" required>
          <option value="">Select Branch</option>
          <option value="Lipa">Lipa</option>
          <option value="Batangas">Batangas</option>
          <option value="Tanauan">Tanauan</option>
        </select>
      </div>

      <div class="form-row">
        <label for="saleItem">Item:</label>
        <select id="saleItem" name="item" required>
          <option value="">Select Item</option>
          <option value="Keyboard">Keyboard</option>
          <option value="Monitor">Monitor</option>
          <option value="Mouse">Mouse</option>
          <option value="Laptop">Laptop</option>
          <option value="Headset">Headset</option>
        </select>
      </div>

      <div class="form-row double">
        <label>Qty / Price:</label>
        <div class="double-inputs">
          <input type="number" id="saleQty" name="quantity" min="1" placeholder="Qty" required>
          <input type="number" id="salePrice" name="price" min="0" placeholder="Price" required>
        </div>
      </div>

      <div class="form-row">
        <label for="saleTotal">Total:</label>
        <input type="text" id="saleTotal" name="total" readonly>
      </div>

      <div class="modal-buttons">
        <button type="button" class="btn btn-primary" onclick="saveSale()">Confirm</button>
        <button type="button" class="btn btn-danger" onclick="closeSaleModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>



<script>
let currentSaleId = null;

// 🔹 Open modal (can be used for Record or Edit)
function openSaleModal(saleId = null, itemName = "", quantity = "", price = "", date = "", branch = "") {
  currentSaleId = saleId;

  document.getElementById('saleModalTitle').textContent = saleId ? "EDIT SALE" : "RECORD SALE";

  // Reset form fields
  document.getElementById('saleForm').reset();

  // Fill form if editing
  if (saleId) {
    document.getElementById('saleItem').value = itemName;
    document.getElementById('saleQty').value = quantity;
    document.getElementById('salePrice').value = price;
    document.getElementById('saleDate').value = date;
    document.getElementById('saleBranch').value = branch;
    updateSaleTotal();
  } else {
    // Default date to today
    document.getElementById('saleDate').value = new Date().toISOString().split('T')[0];
  }

  // Show modal
  document.getElementById('saleModal').classList.add('show');
  document.querySelector('.topbar')?.classList.add('disabled');
}

// 🔹 Close modal
function closeSaleModal() {
  document.getElementById('saleModal').classList.remove('show');
  document.querySelector('.topbar')?.classList.remove('disabled');
}

// 🔹 Auto calculate total
document.getElementById('saleQty').addEventListener('input', updateSaleTotal);
document.getElementById('salePrice').addEventListener('input', updateSaleTotal);

function updateSaleTotal() {
  const qty = parseFloat(document.getElementById('saleQty').value) || 0;
  const price = parseFloat(document.getElementById('salePrice').value) || 0;
  document.getElementById('saleTotal').value = (qty * price).toFixed(2);
}

// 🔹 Example save function (frontend only)
function saveSale() {
  const date = document.getElementById('saleDate').value;
  const branch = document.getElementById('saleBranch').value;
  const item = document.getElementById('saleItem').value;
  const qty = document.getElementById('saleQty').value;
  const price = document.getElementById('salePrice').value;
  const total = document.getElementById('saleTotal').value;

  if (!date || !branch || !item || !qty || !price) {
    alert("⚠️ Please fill out all fields before confirming.");
    return;
  }

  if (currentSaleId) {
    alert(`✅ Sale updated!\n\nID: ${currentSaleId}\nItem: ${item}\nQty: ${qty}\nPrice: ${price}\nTotal: ${total}\nBranch: ${branch}\nDate: ${date}`);
  } else {
    alert(`✅ Sale recorded!\n\nItem: ${item}\nQty: ${qty}\nPrice: ${price}\nTotal: ${total}\nBranch: ${branch}\nDate: ${date}`);
  }

  closeSaleModal();
}
</script>




