<?php
require_once "db_connection.php";

// Get dropdown data
$roles = $conn->query("SELECT DISTINCT role FROM Users");
$branches = $conn->query("SELECT branch_id, branch_name FROM Branches ORDER BY branch_name ASC");
?>

<div class="dashboard">

  <!-- Filters -->
  <form id="filterForm" class="filter-form">
    <div class="filters">
      <div class="filter-left">
        <label>Role:</label>
        <select name="role" onchange="loadTable()">
          <option value="">All Roles</option>
          <?php while ($r = $roles->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($r['role']) ?>"><?= strtoupper($r['role']) ?></option>
          <?php endwhile; ?>
        </select>

        <label>| Branch:</label>
        <select name="branch" onchange="loadTable()">
          <option value="">All Branches</option>
          <?php while ($b = $branches->fetch_assoc()): ?>
            <option value="<?= $b['branch_id'] ?>"><?= strtoupper($b['branch_name']) ?></option>
          <?php endwhile; ?>
        </select>

        <label>| Sort:</label>
        <select id="sort" onchange="loadTable()">
            <option value="ASC">A → Z</option>
            <option value="DESC">Z → A</option>
        </select>
      </div>

      <div class="filter-right">
        <label>Search:</label>
        <input type="text" name="search" placeholder="Search user or branch..." onkeyup="loadTable()">
        <button type="button" class="btn btn-primary" onclick="openAddUserModal()">+ Add User</button>
      </div>
    </div>
  </form>

  <!-- User Table -->
  <div class="table-scroll" role="region" aria-label="User Table">
    <table class="vertical" aria-describedby="caption-vertical">
      <thead>
        <tr>
          <th scope="col" style="width: 100px;">#</th>
          <th scope="col">Username</th>
          <th scope="col" style="width: 150px;">Role</th>
          <th scope="col" style="width: 150px;">Phone</th>
          <th scope="col">Branch</th>
          <th scope="col" style="width: 150px;">Actions</th>
        </tr>
      </thead>
      <tbody id="userTableBody">
        <?php include "list_user.php"; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit User Modal -->
<div id="userModal" class="user-modal-overlay">
  <div class="user-modal-box">
    <h4 id="modalTitle">ADD USER</h4>

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
          <option value="admin">ADMIN</option>
          <option value="shop">SHOP</option>
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
            <option value="<?= htmlspecialchars($b['branch_id']) ?>"><?= strtoupper($b['branch_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="modal-buttons">
        <button type="submit" class="btn btn-primary" id="saveBtn">Save</button>
        <button type="button" class="btn btn-danger" onclick="closeUserModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
let currentUserId = null;

function loadTable() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);

    const params = new URLSearchParams(formData);
    params.append('sort', document.getElementById('sort').value);

    fetch("list_user.php?" + params.toString())
        .then(response => response.text())
        .then(data => {
            document.getElementById("userTableBody").innerHTML = data;
        });
}


// --- Open modal for adding a user ---
function openAddUserModal() {
  currentUserId = null;
  document.getElementById('modalTitle').textContent = "ADD USER";
  document.getElementById('userForm').reset();
  document.getElementById('userModal').classList.add('show');

  // Hide topbar background and border
  const topbar = document.querySelector('.topbar');
  if (topbar) {
    topbar.style.backgroundColor = 'transparent';
    topbar.style.borderBottom = 'none';
  }
}

// --- Open modal for editing a user ---
function openEditUserModal(user) {
    currentUserId = user.id;
    document.getElementById('modalTitle').textContent = "EDIT USER";

    // Fetch latest user data from backend
    fetch('get_user.php?id=' + encodeURIComponent(currentUserId))
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const userData = data.user;

                document.getElementById('username').value = userData.username;
                document.getElementById('password').value = '';
                document.getElementById('phone').value = userData.phone_number || '';
                document.getElementById('role').value = userData.role.toLowerCase();
                
                // Set branch value if role is 'shop', otherwise disable
                const branchDropdown = document.getElementById('branch');
                branchDropdown.innerHTML = ''; // clear options

                userData.branches.forEach(b => {
                    const option = document.createElement('option');
                    option.value = b.branch_name;
                    option.textContent = b.branch_name;
                    if (b.branch_name === userData.branch_name) option.selected = true;
                    branchDropdown.appendChild(option);
                });

                // Apply enable/disable based on role
                document.getElementById('role').dispatchEvent(new Event('change'));

                document.getElementById('userModal').classList.add('show');
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(err => alert("Request failed: " + err));

    // Hide topbar background and border
    const topbar = document.querySelector('.topbar');
    if (topbar) {
        topbar.style.backgroundColor = 'transparent';
        topbar.style.borderBottom = 'none';
    }

    
}

document.getElementById("userForm").addEventListener("submit", function (e) {
    e.preventDefault();

    let formData = new FormData(this);

    // Determine whether it's add or edit
    let url = "";
    if (currentUserId) {
        url = "edit_user.php";
        formData.append("user_id", currentUserId);
    } else {
        url = "add_user.php";
    }

    fetch(url, {
        method: "POST",
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeUserModal();
                loadTable();

                if (currentUserId) {
                    alert("User updated successfully.");
                } else {
                    alert("User added successfully.");
                }
            } else {
                alert("Error:\n" + data.errors.join("\n"));
            }
        })
        .catch(err => alert("Request failed: " + err));
});


// Dynamically enable/disable branch dropdown based on role selection
document.getElementById('role').addEventListener('change', function () {
    const role = this.value.toLowerCase();
    const branchDropdown = document.getElementById('branch');

    if (role === 'shop') {
        branchDropdown.disabled = false;
        // If currently empty, select first available branch
        if (branchDropdown.options.length > 0 && !branchDropdown.value) {
            branchDropdown.selectedIndex = 0;
        }
    } else {
        branchDropdown.disabled = true;
        branchDropdown.value = ""; // reset value
    }
});

// --- Close modal ---
function closeUserModal() {
  document.getElementById('userModal').classList.remove('show');
}

function deleteUser(userId) {
    if (!confirm("Are you sure you want to delete this user?")) return;

    fetch('delete_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'user_id=' + encodeURIComponent(userId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert("User deleted successfully.");
            loadTable(); // reload the table
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(err => alert("Request failed: " + err));
}

</script>
