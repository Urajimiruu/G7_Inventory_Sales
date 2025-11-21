<aside class="sidebar">
  <div class="logo">
    <h2>BIZZTRACK</h2>
  </div>

  <?php
  $current_page = $_GET['page'] ?? 'home';
  ?>

  <ul class="navbar">
    <li><a href="?page=home" class="<?= $current_page === 'home' ? 'active' : '' ?>">Home</a></li>
    <li><a href="?page=sales" class="<?= $current_page === 'sales' ? 'active' : '' ?>">Sales Transaction</a></li>
    <li><a href="?page=returns" class="<?= $current_page === 'returns' ? 'active' : '' ?>">Returned Sales</a></li>
    <?php if ($role === 'admin' || $role === 'shop'): ?>
    <li class="dropdown <?= in_array($current_page, ['main_inventory', 'branch_inventory', 'stock_transfer']) ? 'open' : '' ?>">
      <button>
        Inventory <span class="arrow">▸</span>
      </button>
      <ul>
        <?php if ($role === 'admin'): ?>
        <li><a href="?page=main_inventory" class="<?= $current_page === 'main_inventory' ? 'active' : '' ?>">Main Inventory</a></li>
        <?php endif; ?>
        <li><a href="?page=branch_inventory" class="<?= $current_page === 'branch_inventory' ? 'active' : '' ?>">Branch Inventory</a></li>
        <?php if ($role === 'admin'): ?>
        <li><a href="?page=stock_transfer" class="<?= $current_page === 'stock_transfer' ? 'active' : '' ?>">Stock Transfer</a></li>
        <?php endif; ?>
      </ul>
    </li>

    <li class="dropdown <?= in_array($current_page, ['report_sales', 'report_profitloss', 'report_inventory']) ? 'open' : '' ?>">
      <button>
        Reports <span class="arrow"></span>
      </button>
      <ul>
        <li><a href="?page=report_sales" class="<?= $current_page === 'report_sales' ? 'active' : '' ?>">Sales</a></li>
        <li><a href="?page=report_profitloss" class="<?= $current_page === 'report_profitloss' ? 'active' : '' ?>">Profit / Loss</a></li>
        <li><a href="?page=report_inventory" class="<?= $current_page === 'report_inventory' ? 'active' : '' ?>">Inventory</a></li>
      </ul>
    </li>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <li class="dropdown <?= in_array($current_page, ['maintenance_products', 'maintenance_branches', 'maintenance_users']) ? 'open' : '' ?>">
      <button>
        Maintenance <span class="arrow"></span>
      </button>
      <ul>
        <li><a href="?page=maintenance_products" class="<?= $current_page === 'maintenance_products' ? 'active' : '' ?>">Products</a></li>
        <li><a href="?page=maintenance_branches" class="<?= $current_page === 'maintenance_branches' ? 'active' : '' ?>">Branches</a></li>
        <li><a href="?page=maintenance_users" class="<?= $current_page === 'maintenance_users' ? 'active' : '' ?>">Users</a></li>
      </ul>
    </li>
    <?php endif; ?>
  </ul>

  <form action="logout.php" method="post">
    <button type="submit" class="logout">Logout</button>
  </form>
</aside>
