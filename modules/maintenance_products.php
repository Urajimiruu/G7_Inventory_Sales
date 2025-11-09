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
        <input type="text" name="search" placeholder="Search user or branch...">
        <button type="button" class="btn btn-primary" onclick="openAddProductModal()">+ Add Product</button>
      </div>
    </div>
  </form>

  <!-- Sales table -->
  <div class="table-scroll" role="region" aria-label="Sales table">
    <table class="vertical" aria-describedby="caption-vertical">
      <thead>
        <tr>
          <th class="right">ID</th>
          <th>Name</th>
          <th>Description</th>
          <th>Unit</th>
          <th class="right">Cost Price</th>
          <th class="right">Selling Price</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <!-- Example rows (static demo data) -->
        <tr>
          <td class="right">001</td>
          <td>Sugar Pack</td>
          <td>Candy</td>
          <td>Piece</td>
          <td class="right">$20</td>
          <td class="right">$30</td>
          <td>
            <button type="button" class="btn btn-warning btn-sm" onclick="openAddBranchModal()" >Edit</button>
            <button type="button" class="btn btn-danger btn-sm">Delete</button>
          </td>
        </tr>
        <tr>
          <td class="right">002</td>
          <td>Coffee Beans</td>
          <td>Coffee</td>
          <td>Piece</td>
          <td class="right">$10</td>
          <td class="right">$20</td>
          <td>
            <button type="button" class="btn btn-warning btn-sm" onclick="openAddBranchModal()">Edit</button>
            <button type="button" class="btn btn-danger btn-sm">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>


<!-- Product Modal -->
<div id="productModal" class="modal-overlay">
  <div class="modal-box">
    <h4 id="productModalTitle">ADD PRODUCT</h4>

    <form id="productForm" onsubmit="return false;">
      <div class="form-row">
        <label for="productName">Product Name:</label>
        <input type="text" id="productName" name="product_name" placeholder="Enter product name" required>
      </div>

      <div class="form-row">
        <label for="productDesc">Description:</label>
        <textarea id="productDesc" name="description" rows="3" placeholder="Enter product description" required></textarea>
      </div>

      <div class="form-row">
        <label for="productUnit">Unit:</label>
        <input type="text" id="productUnit" name="unit" placeholder="e.g., pcs, box, kg" required>
      </div>

      <div class="form-row">
        <label for="costPrice">Cost Price:</label>
        <input type="number" id="costPrice" name="cost_price" placeholder="0.00" step="0.01" min="0" required>
      </div>

      <div class="form-row">
        <label for="sellingPrice">Selling Price:</label>
        <input type="number" id="sellingPrice" name="selling_price" placeholder="0.00" step="0.01" min="0" required>
      </div>

      <div class="modal-buttons">
        <button type="button" class="btn btn-primary" onclick="saveProduct()">Save</button>
        <button type="button" class="btn btn-danger" onclick="closeProductModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
let currentProductId = null;

// --- Open modal for adding a product ---
function openAddProductModal() {
  currentProductId = null;
  document.getElementById('productModalTitle').textContent = "ADD PRODUCT";
  document.getElementById('productForm').reset();
  document.getElementById('productModal').classList.add('show');

  // Disable topbar background + border
  const topbar = document.querySelector('.topbar');
  if (topbar) {
    topbar.style.backgroundColor = 'transparent';
    topbar.style.borderBottom = 'none';
  }
}

// --- Open modal for editing an existing product ---
function openEditProductModal(product) {
  currentProductId = product.id;
  document.getElementById('productModalTitle').textContent = "EDIT PRODUCT";

  // Fill fields
  document.getElementById('productName').value = product.name;
  document.getElementById('productDesc').value = product.description;
  document.getElementById('productUnit').value = product.unit;
  document.getElementById('costPrice').value = product.cost_price;
  document.getElementById('sellingPrice').value = product.selling_price;

  document.getElementById('productModal').classList.add('show');

  // Disable topbar background + border
  const topbar = document.querySelector('.topbar');
  if (topbar) {
    topbar.style.backgroundColor = 'transparent';
    topbar.style.borderBottom = 'none';
  }
}

// --- Close modal ---
function closeProductModal() {
  document.getElementById('productModal').classList.remove('show');
  
  // Restore topbar background + border
  const topbar = document.querySelector('.topbar');
  if (topbar) {
    topbar.style.backgroundColor = '';
    topbar.style.borderBottom = '';
  }
}

// --- Temporary save (frontend only) ---
function saveProduct() {
  const name = document.getElementById('productName').value.trim();
  const desc = document.getElementById('productDesc').value.trim();
  const unit = document.getElementById('productUnit').value.trim();
  const cost = document.getElementById('costPrice').value.trim();
  const sell = document.getElementById('sellingPrice').value.trim();

  if (!name || !desc || !unit || !cost || !sell) {
    alert("Please fill out all fields.");
    return;
  }

  alert(`Product ${currentProductId ? "updated" : "added"} successfully!`);
  closeProductModal();
}
</script>

