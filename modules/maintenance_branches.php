<?php
require_once "db_connection.php";
?>

<div class="dashboard">

  <!-- Search / Add -->
  <form id="filterForm" class="filter-form">
    <div class="filters">
      <div class="filter-left"></div>
      <div class="filter-right">
        <label>Search:</label>
        <input type="text" name="search" placeholder="Search branch..." onkeyup="loadBranches()">
        <button type="button" class="btn btn-primary" onclick="openAddBranchModal()">+ Add Branch</button>
      </div>
    </div>
  </form>

  <!-- Branches table -->
  <div class="table-scroll" role="region" aria-label="Branches table">
    <table class="vertical" aria-describedby="caption-vertical">
      <thead>
        <tr>
          <th class="right">ID</th>
          <th>Name</th>
          <th>Location</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="branchTableBody">
        <?php include "list_branches.php"; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Branch Modal -->
<div id="branchModal" class="modal-overlay">
  <div class="modal-box">
    <h4 id="branchModalTitle">ADD BRANCH</h4>

    <form id="branchForm" onsubmit="return false;">
      <div class="form-row">
        <label for="branchName">Branch Name:</label>
        <input type="text" id="branchName" name="branch_name" placeholder="Enter branch name" required>
      </div>

      <div class="form-row">
        <label for="branchLocation">Location:</label>
        <input type="text" id="branchLocation" name="location" placeholder="Enter location" required>
      </div>

      <div class="modal-buttons">
        <button type="button" class="btn btn-primary" onclick="submitBranch()">Save</button>
        <button type="button" class="btn btn-danger" onclick="closeBranchModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
let currentBranchId = null;

function loadBranches() {
    const search = document.querySelector('input[name="search"]').value.trim();
    fetch("list_branches.php?search=" + encodeURIComponent(search))
        .then(res => res.text())
        .then(html => {
            document.getElementById("branchTableBody").innerHTML = html;
        });
}

// --- Add Branch ---
function openAddBranchModal() {
    currentBranchId = null;
    document.getElementById("branchModalTitle").textContent = "ADD BRANCH";
    document.getElementById("branchForm").reset();
    document.getElementById("branchModal").classList.add("show");
}

// --- Edit Branch ---
function openEditBranchModal(branch) {
    currentBranchId = branch.branch_id;
    document.getElementById("branchModalTitle").textContent = "EDIT BRANCH";
    document.getElementById("branchName").value = branch.branch_name;
    document.getElementById("branchLocation").value = branch.location;
    document.getElementById("branchModal").classList.add("show");
}

// --- Close Modal ---
function closeBranchModal() {
    document.getElementById("branchModal").classList.remove("show");
}

// --- Save Branch (Add/Edit) ---
function submitBranch() {
    const form = document.getElementById("branchForm");
    const formData = new FormData(form);
    let url = currentBranchId ? "edit_branches.php" : "add_branches.php";

    if (currentBranchId) formData.append("branch_id", currentBranchId);

    fetch(url, { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Branch saved successfully!");
                closeBranchModal();
                loadBranches();
            } else {
                alert("Error:\n" + data.errors.join("\n"));
            }
        })
        .catch(err => alert("Request failed: " + err));
}

// --- Delete Branch ---
function deleteBranch(id) {
    if (!confirm("Delete this branch?")) return;

    fetch("delete_branches.php", {
        method: "POST",
        body: new URLSearchParams({ branch_id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("Branch deleted successfully.");
            loadBranches();
        } else {
            alert("Error: " + data.message);
        }
    });
}
</script>
