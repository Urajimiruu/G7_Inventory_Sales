<div class="dashboard">

  <!-- 🔍 Filters -->
  <form id="filterForm" class="filter-form">
    <div class="filters">
      <div class="filter-left">
        <label>Filter:</label>
        <select name="role" onchange="loadTable()">
          <option value="">Product</option>
          <option value="Admin">Admin</option>
          <option value="Owner">Owner</option>
          <option value="Renter">Renter</option>
        </select>

        <label>| Sort:</label>
        <select name="branch" onchange="loadTable()">
          <option value="">Lowest</option>
          <option value="Manila">Manila</option>
          <option value="Cebu">Cebu</option>
          <option value="Davao">Davao</option>
        </select>
      </div>

      <div class="filter-right">
        <label>Search:</label>
        <input type="text" name="search" placeholder="Search user or branch..." onkeyup="loadTable()">
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
            <td><button type="button" class="btn btn-primary">Restock</button></td>
          </tr>
          <tr>
            <td>Mechanical Keyboard</td>
            <td class="muted">Peripherals</td>
            <td class="right">$70.00</td>
            <td class="right">$89.00</td>
            <td class="right">5</td>
            <td><button type="button" class="btn btn-primary">Restock</button></td>
          </tr>
          <tr>
            <td>USB-C Hub</td>
            <td class="muted">Accessories</td>
            <td class="right">$30.00</td>
            <td class="right">$39.50</td>
            <td class="right">2</td>
            <td><button type="button" class="btn btn-primary">Restock</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  


<!-- 🧩 User Modal (Add/Edit) -->
<div id="userModal" class="modal-overlay">
  <div class="modal-box">
    <h4 id="modalTitle">ADD USER / EDIT USER</h4>

    <form id="userForm" method="POST">
      <div class="form-row">
        <label>Username:</label>
        <input type="text" name="username" id="username" required>
      </div>

      <div class="form-row">
        <label>Password:</label>
        <input type="password" name="password" id="password" required>
      </div>

      <div class="form-row">
        <label>Phone No.:</label>
        <input type="text" name="phone" id="phone">
      </div>

      <div class="form-row">
        <label>Role:</label>
        <select name="role" id="role" required>
          <option value="">Select Role</option>
          <option value="Admin">Admin</option>
          <option value="Owner">Owner</option>
          <option value="Renter">Renter</option>
        </select>
      </div>

      <div class="form-row">
        <label>Branch:</label>
        <select name="branch" id="branch" required>
          <option value="">Select Branch</option>
          <option value="Manila">Manila</option>
          <option value="Cebu">Cebu</option>
          <option value="Davao">Davao</option>
        </select>
      </div>

      <div class="modal-buttons"> 
        <button type="button" class="btn btn-success" id="saveBtn" onclick="saveUser()">Save</button>
        <button type="button" class="btn btn-danger" onclick="closeUserModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>


<script>
let currentUserId = null;

// 🔹 Open modal for adding a user
function openAddUserModal() {
  currentUserId = null;
  document.getElementById('modalTitle').textContent = "ADD USER";
  document.getElementById('userForm').reset();
  document.getElementById('userModal').classList.add('show');
}

// 🔹 Open modal for editing an existing user
function openEditUserModal(user) {
  currentUserId = user.id;
  document.getElementById('modalTitle').textContent = "EDIT USER";

  document.getElementById('username').value = user.username;
  document.getElementById('password').value = '';
  document.getElementById('role').value = user.role;
  document.getElementById('branch').value = user.branch;

  document.getElementById('userModal').classList.add('show');
}



// 🔹 Close modal
function closeUserModal() {
  document.getElementById('userModal').classList.remove('show');
}
</script>
