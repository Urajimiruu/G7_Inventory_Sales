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
      </div>
    </div>
  </form>

  <!-- 📋 Table -->

    <div class="table-scroll" role="region" aria-label="Products table">
      <table class="vertical" aria-describedby="caption-vertical">
        <thead>
          <tr>
            <th scope="col">Product</th>
            <th scope="col">Unit</th>
            <th scope="col" class="right">Cost Price</th>
            <th scope="col" class="right">Selling Price</th>
            <th scope="col" class="right">Quantity</th>
            <th scope="col">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Wireless Mouse</td>
            <td class="muted">Peripherals</td>
            <td class="right">$20.00</td>
            <td class="right">$24.99</td>
            <td class="right">4</td>
            <td><button type="button" class="btn btn-primary" onclick="openRestockModal({ name: 'Wireless Mouse', quantity: 4 })">Restock</button></td>
          </tr>
          <tr>
            <td>Mechanical Keyboard</td>
            <td class="muted">Peripherals</td>
            <td class="right">$70.00</td>
            <td class="right">$89.00</td>
            <td class="right">5</td>
            <td><button type="button" class="btn btn-primary" onclick="openRestockModal({ name: 'Wireless Mouse', quantity: 4 })">Restock</button></td>
          </tr>
          <tr>
            <td>USB-C Hub</td>
            <td class="muted">Accessories</td>
            <td class="right">$30.00</td>
            <td class="right">$39.50</td>
            <td class="right">2</td>
            <td><button type="button" class="btn btn-primary" onclick="openRestockModal({ name: 'Wireless Mouse', quantity: 4 })">Restock</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  
<!-- 🧩 Restock Modal -->
<div id="restockModal" class="modal-overlay">
  <div class="modal-box">
    <h4 id="restockModalTitle">RESTOCK PRODUCT</h4>

    <form id="restockForm">

      <div class="form-row">
        <label>Item ID:</label>
        <input type="text" id="itemid" name="itemid" readonly>
      </div>

      <div class="form-row">
        <label>Name:</label>
        <input type="text" id="restockProduct" name="product" readonly>
      </div>

      <div class="form-row">
        <label>Stock:</label>
        <input type="text" id="restockCurrentQty" name="current_qty" readonly>
      </div>

      <div class="modal-buttons">
        <button type="button" class="btn btn-primary" onclick="saveRestock()">Confirm</button>
        <button type="button" class="btn btn-danger" onclick="closeRestockModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
let currentProduct = null;
let currentItemId = null;

// 🔹 Open modal and fill with product data
function openRestockModal(itemId, productName, currentQty) {
  currentItemId = itemId;
  currentProduct = productName;

  document.getElementById('itemid').value = itemId;
  document.getElementById('restockProduct').value = productName;
  document.getElementById('restockCurrentQty').value = currentQty;

  document.getElementById('restockModal').classList.add('show');
  document.querySelector('.topbar').classList.add('disabled');
}

// 🔹 Close modal
function closeRestockModal() {
  document.getElementById('restockModal').classList.remove('show');
  document.querySelector('.topbar').classList.remove('disabled');
}

// 🔹 Example save function (frontend only)
function saveRestock() {
  alert(`✅ Restocked item ID ${currentItemId} (${currentProduct}) successfully!`);
  closeRestockModal();
}
</script>
