<?php
require_once "db_connection.php";

// Get dropdown data
$unit = $conn->query("SELECT DISTINCT unit FROM Products");
?>

<div class="dashboard">

  <!-- Filters / header -->
  <form id="filterForm" class="filter-form">
    <div class="filters">
      <div class="filter-left">
        <label>Filter:</label>
        <select name="unit" onchange="loadProducts()">
          <option value="">All Units</option>
          <?php while ($u = $unit->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($u['unit']) ?>"><?= strtoupper($u['unit']) ?></option>
          <?php endwhile; ?>
        </select>

        <label>| Sort:</label>
        <select name="sort" onchange="loadProducts()">
          <option value="ASC">Lowest Price</option>
          <option value="DESC">Highest Price</option>
        </select>
      </div>
      <div class="filter-right">
        <label>Search:</label>
        <input type="text" name="search" placeholder="Search product details..." onkeyup="loadProducts()">
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
      <tbody id="productTableBody">
        <?php include "list_products.php"; ?>
      </tbody>
    </table>
  </div>


<!-- Product Modal -->
<div id="productModal" class="product-modal-overlay">
  <div class="product-modal-box">
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
        <select id="productUnit" name="unit" required>
            <option value="">-- Select Unit --</option>
            <option value="pcs">pcs</option>
            <option value="box">box</option>
            <option value="pack">pack</option>
            <option value="pair">pair</option>
            <option value="set">set</option>
            <option value="kg">kg</option>
            <option value="g">g</option>
            <option value="mg">mg</option>
            <option value="L">L</option>
            <option value="mL">mL</option>
            <option value="dozen">dozen</option>
        </select>
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
        <button type="button" class="btn btn-primary" onclick="submitProduct()">Save</button>
        <button type="button" class="btn btn-danger" onclick="closeProductModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>


<script>
let currentProductId = null;

function loadProducts() {
    const form = document.getElementById("filterForm");
    const formData = new FormData(form);

    const params = new URLSearchParams(formData);

    fetch("list_products.php?" + params.toString())
        .then(res => res.text())
        .then(html => {
            document.getElementById("productTableBody").innerHTML = html;
        });
}


// --- Add Product ---
function openAddProductModal() {
    currentProductId = null;
    document.getElementById("productModalTitle").textContent = "ADD PRODUCT";
    document.getElementById("productForm").reset();
    document.getElementById("productModal").classList.add("show");
}

// --- Edit Product ---
function openEditProductModal(product) {
    currentProductId = product.product_id;

    document.getElementById('productModalTitle').textContent = "EDIT PRODUCT";
    document.getElementById('productName').value = product.product_name;
    document.getElementById('productDesc').value = product.description;
    document.getElementById('productUnit').value = product.unit;
    document.getElementById('costPrice').value = product.cost_price;
    document.getElementById('sellingPrice').value = product.selling_price;

    document.getElementById('productModal').classList.add('show');

    const topbar = document.querySelector('.topbar');
    if (topbar) {
        topbar.style.backgroundColor = 'transparent';
        topbar.style.borderBottom = 'none';
    }
}


// --- Save Product ---
function submitProduct() {
    const form = document.getElementById('productForm');
    const formData = new FormData(form);

    let url = currentProductId ? "edit_product.php" : "add_product.php";
    if (currentProductId) formData.append("product_id", currentProductId);

    fetch(url, {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("Product saved successfully!");
            closeProductModal();
            loadProducts();
        } else {
            alert("Error:\n" + data.errors.join("\n"));
        }
    })
    .catch(err => alert("Request failed: " + err));
}


// --- Delete Product ---
function deleteProduct(id) {
    if (!confirm("Delete this product?")) return;

    fetch("delete_product.php", {
        method: "POST",
        body: new URLSearchParams({ product_id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("Product deleted.");
            loadProducts();
        } else {
            alert("Error: " + data.message);
        }
    });
}

function closeProductModal() {
    document.getElementById("productModal").classList.remove("show");
}

</script>

