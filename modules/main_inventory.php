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
            <th scope="col">Product Name</th>
            <th scope="col">Unit</th>
            <th scope="col" class="right">Cost Price</th>
            <th scope="col" class="right">Selling Price</th>
            <th scope="col" class="right">Quantity</th>
            <th scope="col">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
          require_once "db_connection.php";

          $productsTable = "products";
          $mainInvTable  = "maininventory"; // change if needed

          $sql = "
            SELECT 
              p.product_id,
              p.product_name,
              p.unit,
              p.cost_price,
              p.selling_price,
              COALESCE(m.quantity, 0) AS quantity
            FROM $productsTable p
            LEFT JOIN $mainInvTable m ON p.product_id = m.product_id
            ORDER BY p.product_name ASC
          ";

          $result = $conn->query($sql);

          if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
              $productId   = (int)$row['product_id'];
              $productName = htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8');
              $unit        = htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8');
              $costPrice   = number_format((float)$row['cost_price'], 2);
              $sellPrice   = number_format((float)$row['selling_price'], 2);
              $qty         = (int)$row['quantity'];

              echo "<tr>
                      <td>{$productName}</td>
                      <td class='muted'>{$unit}</td>
                      <td class='right'>{$costPrice}</td>
                      <td class='right'>{$sellPrice}</td>
                      <td class='right'>{$qty}</td>
                      <td>
                        <button type='button' class='btn btn-primary'
                          onclick=\"openRestockModal({$productId}, '{$productName}', {$qty})\">
                          Restock
                        </button>
                      </td>
                    </tr>";
            }
          } else {
            echo "<tr><td colspan='6' style='text-align:center;'>No products found</td></tr>";
          }

          $conn->close();
          ?>
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
        <button type="button" class="btn btn-secondary" onclick="closeRestockModal()">Cancel</button>
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
