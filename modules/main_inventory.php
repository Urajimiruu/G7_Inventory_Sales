<div class="dashboard">

  <!--Filters -->
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

  <!-- Table -->
  <div class="table-scroll" role="region">
    <table class="vertical">
          <div id="salesLoading" class="loading-indicator" style="display: none;">
        <div class="loading-spinner"></div>
        <p>Loading sales data...</p>
      </div>
      <thead>
        <tr>
          <th>Product Name</th>
          <th style="width: 150px;">Unit</th>
          <th class="right">Cost Price</th>
          <th class="right">Selling Price</th>
          <th class="right" style="width: 150px;">Quantity</th>
          <th style="width: 150px;">Action</th>
        </tr>
      </thead>

      <tbody id="mainInvBody">
        <?php include "modules/list_main_inventory.php"; ?>
      </tbody>
    </table>
  </div>

  <!-- <div class="pagination" id="pagination"></div> -->

</div>


  <!-- Restock Modal -->
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

      <!-- New field: how many to add -->
      <div class="form-row">
        <label>Quantity to Add:</label>
        <input type="number" id="restockAddQty" name="add_qty" min="1" required>
      </div>

      <div class="modal-buttons">
        <button type="button" id="restockConfirmBtn" class="btn btn-primary" onclick="saveRestock()">Confirm</button>
        <button type="button" id="restockCancelBtn" class="btn btn-danger" onclick="closeRestockModal()">Cancel</button>
      </div>

    </form>
  </div>
</div>
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
<script>
  
  let currentProduct = null;
  let currentItemId = null;

  // ---------- LOADING INDICATOR FUNCTIONS ----------
  function showLoadingIndicator() {
    const tableBody = document.getElementById('mainInvBody');
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

//   function loadMainInventory() {
//     const form = document.getElementById("filterForm");
//     const formData = new FormData(form);
//     const params = new URLSearchParams(formData);

//     fetch("modules/list_main_inventory.php?" + params.toString())
//     .then(res => res.text())
//     .then(html => {
//         document.getElementById("mainInvBody").innerHTML = html;
//     });
// }

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

    setRestockButtonsEnabled(false);

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
          alert(`Restocked ${addQty} of "${currentProduct}" successfully!`);
          location.reload();
        } else {
          alert(" Error: " + (data.message || "Unknown error"));
        }
      })
      .catch(err => {
        console.error("Restock error:", err);
        alert("Failed to restock: " + err.message);
      })
      .finally(() => {
          // always re-enable buttons and restore cursor
          setRestockButtonsEnabled(true);
      });
  }

  function setRestockButtonsEnabled(enabled) {
    const confirmBtn = document.getElementById('restockConfirmBtn');
    const cancelBtn  = document.getElementById('restockCancelBtn');

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

  function loadMainInventory(page = 1) {
    const form = document.getElementById("filterForm");
    const formData = new FormData(form);
    formData.append('page', page); // send current page
    const params = new URLSearchParams(formData);
    showLoadingIndicator();

    fetch("modules/list_main_inventory.php?" + params.toString())
      .then(res => res.text())
      .then(html => {
          document.getElementById("mainInvBody").innerHTML = html;
          hideLoadingIndicator();
      })
      .catch(err => {
          console.error("Error loading inventory:", err);
          document.getElementById("mainInvBody").innerHTML = 
            "<tr><td colspan='6' style='text-align:center;'>Error loading data</td></tr>";
      });
  }

</script>
