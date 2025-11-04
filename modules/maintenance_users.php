<?php
require_once "db_connection.php";

// Get data for filter dropdowns
$roles = $conn->query("SELECT DISTINCT role FROM Users");
$branches = $conn->query("SELECT branch_name FROM Branches ORDER BY branch_name ASC");
?>

<div class="dashboard">

  <!-- 🔍 Filters -->
  <form id="filterForm" class="filter-form">
    <div class="filters">
      <div class="filter-left">
        <label>Role:</label>
        <select name="role" onchange="loadTable()">
          <option value="">All Roles</option>
          <?php while ($r = $roles->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($r['role']) ?>"><?= htmlspecialchars($r['role']) ?></option>
          <?php endwhile; ?>
        </select>

        <label>| Branch:</label>
        <select name="branch" onchange="loadTable()">
          <option value="">All Branches</option>
          <?php while ($b = $branches->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($b['branch_name']) ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="filter-right">
        <label>Search:</label>
        <input type="text" name="search" placeholder="Search user or branch..." onkeyup="loadTable()">
        <button type="button" class="btn btn-primary" onclick="openAddUserModal()">+ Add User</button>
      </div>
    </div>
  </form>asfas

  <!-- 📋 Table -->
  <div class="table-container">
    <table class="user-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Username</th>
          <th>Role</th>
          <th>Branch</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="userTableBody">
        <?php include "list_user.php"; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 🧩 Add User Modal (hidden by default) -->
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
          <?php
          $branches->data_seek(0);
          while ($b = $branches->fetch_assoc()):
          ?>
            <option value="<?= htmlspecialchars($b['branch_name']) ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="modal-buttons">
        <button type="submit" class="btn btn-success" id="saveBtn">Save</button>
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
  document.getElementById('userForm').action = "add_user.php";
  document.getElementById('userModal').classList.add('show');
}

// 🔹 Open modal for editing an existing user
function openEditUserModal(user) {
  currentUserId = user.id;
  document.getElementById('modalTitle').textContent = "EDIT USER";
  document.getElementById('userForm').action = "edit_user.php";

  // Fill form fields
  document.getElementById('username').value = user.username;
  document.getElementById('password').value = ''; // intentionally blank
  document.getElementById('phone').value = user.phone || '';
  document.getElementById('role').value = user.role;
  document.getElementById('branch').value = user.branch;

  document.getElementById('userModal').classList.add('show');
}

// 🔹 Close modal
function closeUserModal() {
  document.getElementById('userModal').classList.remove('show');
}
</script>
