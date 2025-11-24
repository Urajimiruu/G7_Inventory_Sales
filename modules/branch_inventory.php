<?php

require_once "db_connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$role     = $_SESSION['role'];
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// fetch dynamic dropdowns
$units = $conn->query("SELECT DISTINCT unit FROM Products WHERE unit <> '' ORDER BY unit ASC");
$products = $conn->query("SELECT product_id, product_name FROM Products ORDER BY product_name ASC");
$branches = $conn->query("SELECT branch_id, branch_name FROM Branches ORDER BY branch_name ASC");
?>

<div class="dashboard">

  <!-- 🔍 Filters -->
  <form id="filterForm" class="filter-form">
    <div class="filters">

      <div class="filter-left">

        <!-- PRODUCT FILTER -->
        <label>Product:</label>
        <select name="product_id" onchange="loadBranchInventory()">
          <option value="">All</option>
          <?php while ($p = $products->fetch_assoc()): ?>
            <option value="<?= $p['product_id'] ?>"><?= htmlspecialchars($p['product_name']) ?></option>
          <?php endwhile; ?>
        </select>

        <!-- UNIT FILTER -->
        <label>Unit:</label>
        <select name="unit" onchange="loadBranchInventory()">
          <option value="">All</option>
          <?php while ($u = $units->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($u['unit']) ?>"><?= htmlspecialchars($u['unit']) ?></option>
          <?php endwhile; ?>
        </select>

        <!-- BRANCH FILTER (admin only) -->
        <?php if ($role === 'admin'): ?>
        <label>Branch:</label>
        <select name="branch_id" onchange="loadBranchInventory()">
          <option value="">All</option>
          <?php while ($b = $branches->fetch_assoc()): ?>
            <option value="<?= $b['branch_id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
          <?php endwhile; ?>
        </select>
        <?php endif; ?>

        <!-- SORT -->
        <label>Sort:</label>
        <select name="sort" onchange="loadBranchInventory()">
          <option value="ASC">Lowest Qty</option>
          <option value="DESC">Highest Qty</option>
        </select>

      </div>

      <div class="filter-right">
        <label>Search:</label>
        <input type="text" name="search" placeholder="Search product..." onkeyup="loadBranchInventory()">

        <!-- RETURN BTN FOR SHOP ONLY -->
        <?php if ($role === 'shop'): ?>
          <button type="button" class="btn btn-primary"
            onclick="window.location.href='shop.php?page=returns'">Returns</button>
        <?php elseif ($role === 'admin'): ?>
          <button type="button" class="btn btn-primary"
            onclick="window.location.href='admin.php?page=returns'">Returns</button>
        <?php endif; ?>
      </div>

    </div>
  </form>

  <!-- TABLE -->
  <div class="table-scroll" role="region">
    <table class="vertical">
      <thead>
        <tr>
          <th>Branch</th>
          <th>Product</th>
          <th style="width: 150px;">Unit</th>
          <th class="right">Cost Price</th>
          <th class="right">Selling Price</th>
          <th class="right" style="width: 150px;">Quantity</th>
          <th style="width: 150px;">Status</th>
        </tr>
      </thead>
      <tbody id="branchInventoryBody">
        <!-- loaded via AJAX -->
      </tbody>
    </table>
  </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", loadBranchInventory);

function loadBranchInventory() {
    const form = document.getElementById("filterForm");
    const formData = new FormData(form);

    fetch("modules/list_branch_inventory.php?" + new URLSearchParams(formData), {
        method: "GET"
    })
    .then(res => res.text())
    .then(html => {
        document.getElementById("branchInventoryBody").innerHTML = html;
    });
}
</script>
