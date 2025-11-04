<div class="dashboard">

  <!-- 🔍 Filters -->
  <form id="filterForm" class="filter-form">
    <div class="filters">
      <div class="filter-left">
        <label>Filter:</label>
        <select name="filter1">
          <option value=""></option>
          <option value="Product">Product</option>
          <option value="Owner">Owner</option>
          <option value="Renter">Renter</option>
        </select>

        <label>Filter:</label>
        <select name="filter2">
          <option value=""></option>
          <option value="Product">Product</option>
          <option value="Owner">Owner</option>
          <option value="Renter">Renter</option>
        </select>

        <label>| From:</label>
        <select name="fromBranch">
          <option value=""></option>
          <option value="Lipa">Lipa</option>
          <option value="Batangas">Batangas</option>
          <option value="Lemery">Lemery</option>
        </select>

        <label>To:</label>
        <select name="toBranch">
          <option value=""></option>
          <option value="Lipa">Lipa</option>
          <option value="Batangas">Batangas</option>
          <option value="Lemery">Lemery</option>
        </select>
      </div>

      <div class="filter-right">
        <!-- if you want this to open the modal without item, pass '' -->
        <button type="button" class="btn btn-primary" onclick="openTransferModal('')">Transfer</button>
      </div>
    </div>
  </form>

  <!-- 📋 Table -->

  <div class="table-scroll" role="region" aria-label="Products table">
    <table class="vertical" aria-describedby="caption-vertical">
      <thead>
        <tr>
          <th scope="col">Product</th>
          <th scope="col">Quantity</th>
          <th scope="col" class="right">Branch</th>
          <th scope="col" class="right">Transfer Date</th>
        </tr>
      </thead>
      <tbody>
        <tr data-itemid="ITEM-001">
          <td>Wireless Mouse</td>
          <td class="right qty">18</td>
          <td>Lipa</td>
          <td>11/4/2025</td>
        </tr>
        <tr data-itemid="ITEM-002">
          <td>Mechanical Keyboard</td>
          <td class="right qty">38</td>
          <td>Lipa</td>
          <td>11/4/2025</td>
        </tr>
        <tr data-itemid="ITEM-003">
          <td>USB-C Hub</td>
          <td class="right qty">56</td>
          <td>Lemery</td>
          <td>11/4/2025</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- 🧩 Transfer Modal -->
  <div id="transferModal" class="modal-overlay">
    <div class="modal-box">
      <h4 id="transferModalTitle">TRANSFER STOCK</h4>

      <form id="transferForm">

        <div class="form-row">
          <label>Date:</label>
          <input type="date" id="transferDate" name="date" required>
        </div>

        <div class="form-row">
          <label>Item ID:</label>
          <input type="text" id="itemid" name="itemid" readonly>
        </div>

        <div class="form-row">
          <label>Branch:</label>
          <select id="transferBranch" name="branch" required>
            <option value="">Select Branch</option>
            <option value="Main Branch">Main Branch</option>
            <option value="North Branch">North Branch</option>
            <option value="South Branch">South Branch</option>
          </select>
        </div>

        <div class="form-row">
          <label>Quantity:</label>
          <input type="number" id="transferQty" name="quantity" min="1" required>
        </div>

        <div class="modal-buttons">
          <button type="button" class="btn btn-primary" onclick="saveTransfer()">Confirm</button>
          <button type="button" class="btn btn-danger" onclick="closeTransferModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

</div>

<script>
let currentItemId = null;

// safe open: accepts empty id or itemId string
function openTransferModal(itemId) {
  currentItemId = itemId || '';
  document.getElementById('itemid').value = currentItemId;

  // Auto-fill date with today
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('transferDate').value = today;

  // if you want to prefill quantity from table, try to read it
  if (currentItemId) {
    const row = document.querySelector('tr[data-itemid="' + currentItemId + '"]');
    if (row) {
      const qtyCell = row.querySelector('.qty');
      if (qtyCell) {
        // put current quantity into transferQty as default (optional)
        document.getElementById('transferQty').value = qtyCell.textContent.trim();
      }
    } else {
      document.getElementById('transferQty').value = '';
    }
  } else {
    document.getElementById('transferQty').value = '';
  }

  document.getElementById('transferModal').classList.add('show');
  document.querySelector('.topbar')?.classList.add('disabled');
}

function closeTransferModal() {
  document.getElementById('transferModal').classList.remove('show');
  document.querySelector('.topbar')?.classList.remove('disabled');
  // optional: clear fields
  // document.getElementById('transferForm').reset();
}

function saveTransfer() {
  const date = document.getElementById('transferDate').value;
  const branch = document.getElementById('transferBranch').value;
  const qty = parseInt(document.getElementById('transferQty').value, 10);

  if (!date || !branch || !qty || qty < 1) {
    alert("⚠️ Please fill out all fields with valid values.");
    return;
  }

  // Example confirmation
  alert(`✅ Transfer confirmed!\n\nItem ID: ${currentItemId || '(none)'}\nBranch: ${branch}\nQuantity: ${qty}\nDate: ${date}`);

  // OPTIONAL: update the table quantity visually (decrease by qty)
  if (currentItemId) {
    const row = document.querySelector('tr[data-itemid="' + currentItemId + '"]');
    if (row) {
      const qtyCell = row.querySelector('.qty');
      if (qtyCell) {
        // parse current, subtract qty, clamp >= 0
        const current = parseInt(qtyCell.textContent.trim(), 10) || 0;
        const newVal = Math.max(0, current - qty);
        qtyCell.textContent = String(newVal);
      }
    }
  }

  closeTransferModal();
}
</script>
