<aside class="sidebar">
  <div class="logo">
    <h2>BIZZTRACK</h2>
  </div>

  <?php
  $current_page = $_GET['page'] ?? 'home';
  ?>

  <ul class="navbar">
    <li><a href="?page=home" class="<?= $current_page === 'home' ? 'active' : '' ?>">
          <ion-icon name="home-outline"></ion-icon>
          <span>Home</span>
        </a>
    </li>
    <li><a href="?page=sales" class="<?= $current_page === 'sales' ? 'active' : '' ?>">
          <ion-icon name="cash-outline"></ion-icon>
          <span>Sales Transaction</span>
        </a>
    </li>
    <li><a href="?page=returns" class="<?= $current_page === 'returns' ? 'active' : '' ?>">
          <ion-icon name="arrow-undo-outline"></ion-icon>
          <span>Returned Sales</span>
        </a>
    </li>
    <?php if ($role === 'admin' || $role === 'shop'): ?>
    <li class="dropdown <?= in_array($current_page, ['main_inventory', 'branch_inventory', 'stock_transfer']) ? 'open' : '' ?>">
      <button>
        <ion-icon name="cube-outline"></ion-icon>
        <span>Inventory</span>
        <span class="arrow">▸</span>
      </button>
      <ul>
        <?php if ($role === 'admin'): ?>
        <li><a href="?page=main_inventory" class="<?= $current_page === 'main_inventory' ? 'active' : '' ?>">
              <ion-icon name="server-outline"></ion-icon>
              <span>Main Inventory</span>
            </a>
        </li>
        <?php endif; ?>
        <li><a href="?page=branch_inventory" class="<?= $current_page === 'branch_inventory' ? 'active' : '' ?>">
              <ion-icon name="storefront-outline"></ion-icon>
              <span>Branch Inventory</span>
            </a>
        </li>
        <?php if ($role === 'admin'): ?>
        <li><a href="?page=stock_transfer" class="<?= $current_page === 'stock_transfer' ? 'active' : '' ?>">
              <ion-icon name="swap-horizontal-outline"></ion-icon>
              <span>Stock Transfer</span>
            </a>
        </li>
        <?php endif; ?>
      </ul>
    </li>

    <li class="dropdown <?= in_array($current_page, ['report_sales', 'report_profitloss', 'report_inventory']) ? 'open' : '' ?>">
      <button>
        <ion-icon name="bar-chart-outline"></ion-icon>
        <span>Reports</span>
        <span class="arrow">▸</span>
      </button>
      <ul>
        <li><a href="?page=report_sales" class="<?= $current_page === 'report_sales' ? 'active' : '' ?>">
              <ion-icon name="receipt-outline"></ion-icon>
              <span>Sales</span>
            </a>
        </li>
        <li><a href="?page=report_profitloss" class="<?= $current_page === 'report_profitloss' ? 'active' : '' ?>">
              <ion-icon name="trending-up-outline"></ion-icon>
              <span>Profit / Loss</span>
            </a>
        </li>
        <li><a href="?page=report_inventory" class="<?= $current_page === 'report_inventory' ? 'active' : '' ?>">
              <ion-icon name="analytics-outline"></ion-icon>
              <span>Inventory</span>
            </a>
        </li>
      </ul>
    </li>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <li class="dropdown <?= in_array($current_page, ['maintenance_products', 'maintenance_branches', 'maintenance_users']) ? 'open' : '' ?>">
      <button>
        <ion-icon name="settings-outline"></ion-icon>
        <span>Maintenance</span>
        <span class="arrow">▸</span>
      </button>
      <ul>
        <li><a href="?page=maintenance_products" class="<?= $current_page === 'maintenance_products' ? 'active' : '' ?>">
              <ion-icon name="pricetag-outline"></ion-icon>
              <span>Products</span>
             </a>
          </li>
        <li><a href="?page=maintenance_branches" class="<?= $current_page === 'maintenance_branches' ? 'active' : '' ?>">
              <ion-icon name="business-outline"></ion-icon>
              <span>Branches</span>
            </a>
        </li>
        <li><a href="?page=maintenance_users" class="<?= $current_page === 'maintenance_users' ? 'active' : '' ?>">
              <ion-icon name="people-outline"></ion-icon>
              <span>Users</span>
            </a>
        </li>
      </ul>
    </li>
    <?php endif; ?>
  </ul>

  <form action="logout.php" method="post">
    <button type="submit" class="logout">
      <ion-icon name="log-out-outline"></ion-icon>
      <span>Logout</span>
    </button>
  </form>
</aside>
