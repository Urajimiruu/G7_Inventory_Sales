<div class="dashboard">

  <!-- 🔍 Filters -->
  <form id="filterForm" class="filter-form">
    <div class="filters">

      <div class="filter-left">
        <label>Unit:</label>
        <select name="unit" onchange="loadMainInventory()">
          <option value="">All Units</option>

          <?php
          require_once "db_connection.php";
          $units = $conn->query("SELECT DISTINCT unit FROM Products ORDER BY unit ASC");
          while ($u = $units->fetch_assoc()):
          ?>
            <option value="<?= htmlspecialchars($u['unit']) ?>">
              <?= strtoupper($u['unit']) ?>
            </option>
          <?php endwhile; ?>
        </select>

        <label>| Sort:</label>
        <select name="sort" onchange="loadMainInventory()">
          <option value="ASC">Lowest Qty</option>
          <option value="DESC">Highest Qty</option>
        </select>
      </div>

      <div class="filter-right">
        <label>Search:</label>
        <input type="text" name="search" placeholder="Search product..." onkeyup="loadMainInventory()">
      </div>

    </div>
  </form>

  <!-- 📋 Table -->
  <div class="table-scroll" role="region">
    <table class="vertical">
      <thead>
        <tr>
          <th>Product Name</th>
          <th>Unit</th>
          <th class="right">Cost Price</th>
          <th class="right">Selling Price</th>
          <th class="right">Quantity</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody id="mainInvBody">
        <?php include "modules/list_main_inventory.php"; ?>
      </tbody>
    </table>
  </div>

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
        <label>Current Stock:</label>
        <input type="text" id="restockCurrentQty" name="current_qty" readonly>
      </div>

      <!-- 🔢 New field: how many to add -->
      <div class="form-row">
        <label>Quantity to Add:</label>
        <input type="number" id="restockAddQty" name="add_qty" min="1" required>
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

  // Open modal
  function openRestockModal(itemId, productName, currentQty) {
    currentItemId = itemId;
    currentProduct = productName;

    document.getElementById('itemid').value = itemId;
    document.getElementById('restockProduct').value = productName;
    document.getElementById('restockCurrentQty').value = currentQty;
    document.getElementById('restockAddQty').value = ""; // clear

    document.getElementById('restockModal').classList.add('show');
  }

  // Close modal
  function closeRestockModal() {
    document.getElementById('restockModal').classList.remove('show');
  }

  function loadMainInventory() {
    const form = document.getElementById("filterForm");
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);

    fetch("modules/list_main_inventory.php?" + params.toString())
    .then(res => res.text())
    .then(html => {
        document.getElementById("mainInvBody").innerHTML = html;
    });
}

document.addEventListener("DOMContentLoaded", loadMainInventory);

  function saveRestock() {
    const addQty = parseInt(document.getElementById('restockAddQty').value, 10);

    if (isNaN(addQty) || addQty <= 0) {
      alert("Please enter a valid quantity to add.");
      return;
    }

    const formData = new FormData();
    formData.append('itemid', currentItemId);
    formData.append('add_qty', addQty);

    fetch('modules/restock_product.php', {
      method: 'POST',
      body: formData
    })
      .then(response => {
        if (!response.ok) {
          // HTTP error (404, 500, etc)
          throw new Error("HTTP error " + response.status);
        }
        // Read as text first so we can see what PHP really returns
        return response.text();
      })
      .then(text => {
        console.log("Raw response from restock_product.php:", text);

        let data;
        try {
          data = JSON.parse(text);
        } catch (e) {
          throw new Error("Response is not valid JSON: " + e.message);
        }

        if (data.success) {
          alert(`✅ Restocked ${addQty} of "${currentProduct}" successfully!`);
          location.reload();
        } else {
          alert("❌ Error: " + (data.message || "Unknown error"));
        }
      })
      .catch(err => {
      console.error("Restock error:", err);
      alert("❌ Failed to restock: " + err.message);
    });

  }

</script>
