<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Main Admin Dashboard</title>
  <link rel="stylesheet" href="css/admin.css">
  <!-- <link rel="stylesheet" href="css/general.css"> -->
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="logo">
        <img class="circle" src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQ0qCreqkTZL0F0bF9kZctFE1XVFocO__70kw&s">
        <h2>BIZZTRACK</h2>
      </div>

      <nav>
        <ul class="navbar">
          <li><a href="admin_home.php">Home</a></li>
          <li><a href="admin_salestransaction.php">Sales Transaction</a></li>

          <!-- INVENTORY -->
          <li class="subnav">
            <button class="subnavbtn">Inventory &#8595;</button>
            <ul class="subnav-content">
              <a href="admin_main_inventory.php">Main Inventory</a>
              <a href="admin_branch_inventory.php">Branch Inventory</a>
              <a href="admin_stock_transfer.php">Stock Transfer</a>
            </ul>
          </li>

          <!-- REPORTS -->
          <li class="subnav">
            <button class="subnavbtn">Reports &#8595;</button>
            <ul class="subnav-content">
              <a href="#">Sales</a>
              <a href="#">Profit/Loss</a>
              <a href="#">Stock Transfers</a>
              <a href="#">Inventory</a>
            </ul>
          </li>

          <!-- MAINTENANCE -->
          <li class="subnav">
            <button class="subnavbtn">Maintenance &#8595;</button>
            <ul class="subnav-content">
              <a href="#">Products</a>
              <a href="#">Branches</a>
              <a href="#">Users</a>
            </ul>
          </li>
        </ul>
      </nav>

      <button class="logout">Log Out</button>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <header class="user-info">
        <p><?= htmlspecialchars($_SESSION["username"]); ?></p>
        <p>Admin</p>
        <hr></hr>
      </header>
      
      <?php include($content); ?>
    </main>
  </div>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.subnavbtn');

    buttons.forEach(btn => {
      const submenu = btn.nextElementSibling;

      btn.addEventListener('click', (e) => {
        e.stopPropagation();

        // Close other dropdowns
        document.querySelectorAll('.subnav-content').forEach(content => {
          if (content !== submenu) content.classList.remove('show');
        });

        // Toggle this one
        submenu.classList.toggle('show');
      });
    });

    // Close all menus when clicking outside
    document.addEventListener('click', () => {
      document.querySelectorAll('.subnav-content').forEach(submenu => {
        submenu.classList.remove('show');
      });
    });
  });
  </script>


</body>
</html>
