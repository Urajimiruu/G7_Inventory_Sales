<div class="dashboard">

  <!-- Filters / header -->
  <form id="filterForm" class="filter-form">
    <div class="filters">
      <div class="filter-left"></div>
      <div class="filter-right">
        <label>Search:</label>
        <input type="text" name="search" placeholder="Search user or branch...">
        <button type="button" class="btn btn-primary" onclick="openAddBranchModal()">+ Add Branch</button>
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
          <th>Location</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <!-- Example rows (static demo data) -->
        <tr>
          <td>001</td>
          <td>Sugar Pack</td>
          <td>Main Branch</td>
          <td>
            <button type="button" class="btn btn-warning btn-sm" onclick="openAddBranchModal()" >Edit</button>
            <button type="button" class="btn btn-danger btn-sm">Delete</button>
          </td>
        </tr>
        <tr>
          <td>002</td>
          <td>Coffee Beans</td>
          <td>Shop 2</td>
          <td>
            <button type="button" class="btn btn-warning btn-sm" onclick="openAddBranchModal()">Edit</button>
            <button type="button" class="btn btn-danger btn-sm">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>


<!-- Branch Modal -->
<div id="branchModal" class="modal-overlay">
  <div class="modal-box">
    <h4 id="modalTitle">ADD/EDIT BRANCH</h4>

    <form id="branchForm" onsubmit="return false;">
      <div class="form-row">
        <label for="branchName">Branch Name:</label>
        <input type="text" id="branchName" name="branch_name" placeholder="Enter branch name" required>
      </div>

      <div class="form-row">
        <label for="branchLocation">Location:</label>
        <input type="text" id="branchLocation" name="branch_location" placeholder="Enter location" required>
      </div>

      <div class="modal-buttons">
        <button type="button" class="btn btn-primary" onclick="saveBranch()">Save</button>
        <button type="button" class="btn btn-danger" onclick="closeBranchModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
let currentBranchId = null;

// --- Open modal for adding a branch ---
function openAddBranchModal() {
  currentBranchId = null;
  document.getElementById('modalTitle').textContent = "ADD/EDIT BRANCH";
  document.getElementById('branchForm').reset();
  document.getElementById('branchModal').classList.add('show');
  
  // Disable topbar background + border
  const topbar = document.querySelector('.topbar');
  if (topbar) {
    topbar.style.backgroundColor = 'transparent';
    topbar.style.borderBottom = 'none';
  }
}

// --- Open modal for editing a branch ---
function openEditBranchModal(branch) {
  currentBranchId = branch.id;
  document.getElementById('modalTitle').textContent = "EDIT BRANCH";

  document.getElementById('branchName').value = branch.name;
  document.getElementById('branchLocation').value = branch.location;
  document.getElementById('branchModal').classList.add('show');

  // Disable topbar background + border
  const topbar = document.querySelector('.topbar');
  if (topbar) {
    topbar.style.backgroundColor = 'transparent';
    topbar.style.borderBottom = 'none';
  }
}

// --- Close modal ---
function closeBranchModal() {
  document.getElementById('branchModal').classList.remove('show');
  
  // Restore topbar background + border
  const topbar = document.querySelector('.topbar');
  if (topbar) {
    topbar.style.backgroundColor = '';  // reverts to original CSS
    topbar.style.borderBottom = '';
  }
}

// --- Temporary save function (frontend only) ---
function saveBranch() {
  const name = document.getElementById('branchName').value.trim();
  const location = document.getElementById('branchLocation').value.trim();

  if (!name || !location) {
    alert("Please fill out all fields.");
    return;
  }

  alert(`Branch ${currentBranchId ? "updated" : "added"} successfully!`);
  closeBranchModal();
}
</script>
