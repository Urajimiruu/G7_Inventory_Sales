<?php

require_once "db_connection.php"; // adjust path if this file is not in /modules

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$role     = $_SESSION['role'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);
?>

<div class="dashboard">

  <!-- 🔍 Filters -->
  <form id="filterForm" class="filter-form">
    <div class="filters">
      <div class="filter-left">
        <label>Filter:</label>
        <select name="role">
          <option value="">Product</option>
          <option value="Admin">Admin</option>
          <option value="Owner">Owner</option>
          <option value="Renter">Renter</option>
        </select>

        <label>Filter:</label>
        <select name="role">
          <option value="">Product</option>
          <option value="Admin">Admin</option>
          <option value="Owner">Owner</option>
          <option value="Renter">Renter</option>
        </select>

        <label>| Sort:</label>
        <select name="branch">
          <option value="">Lowest</option>
          <option value="Manila">Manila</option>
          <option value="Cebu">Cebu</option>
          <option value="Davao">Davao</option>
        </select>
      </div>

      <div class="filter-right">
        <button type="button" class="btn btn-primary" onclick="window.location.href='admin.php?page=returns'">Returns</button>
      </div>
    </div>
  </form>

  <!-- 📋 Table -->

    <div class="table-scroll" role="region" aria-label="Products table">
      <table class="vertical" aria-describedby="caption-vertical">
       <thead>
      <tr>
        <th scope="col">Branch</th>
        <th scope="col">Product</th>
        <th scope="col">Unit</th>
        <th scope="col" class="right">Cost Price</th>
        <th scope="col" class="right">Selling Price</th>
        <th scope="col" class="right">Quantity</th>
        <th scope="col">Status</th>
      </tr>
    </thead>

       <tbody>
<tbody>
<?php
// base query
$sql = "
SELECT 
    bi.branch_id,
    bi.product_id,
    bi.quantity,
    p.product_name,
    p.unit,
    p.cost_price,
    p.selling_price,
    b.branch_name
FROM branchinventory bi
JOIN products  p ON bi.product_id = p.product_id
JOIN branches  b ON bi.branch_id = b.branch_id
";

// if role is "shop", restrict to their branch only
// adjust 'shop' to match exactly what you store in the Users.role column (Shop / SH0P / etc.)
if (strtolower($role) === 'shop' && $branchId > 0) {
    $sql .= " WHERE bi.branch_id = ? ";
}

$sql .= " ORDER BY b.branch_name, p.product_name";

if (strtolower($role) === 'shop' && $branchId > 0) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $branchId);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // admin / owner / whatever: show all branches
    $result = $conn->query($sql);
}

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $branchName   = htmlspecialchars($row['branch_name'], ENT_QUOTES, 'UTF-8');
    $productName  = htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8');
    $unit         = htmlspecialchars($row['unit'], ENT_QUOTES, 'UTF-8');
    $costPrice    = number_format((float)$row['cost_price'], 2);
    $sellingPrice = number_format((float)$row['selling_price'], 2);
    $qty          = (int)$row['quantity'];

    // status logic
    if ($qty === 0) {
      $statusText  = 'No Stock';
      $statusClass = 'no-stock';
    } elseif ($qty < 20) {
      $statusText  = 'Low Stock';
      $statusClass = 'low-stock';
    } else {
      $statusText  = 'On Stock';
      $statusClass = 'on-stock';
    }

    echo "
      <tr>
        <td>{$branchName}</td>
        <td>{$productName}</td>
        <td class='muted'>{$unit}</td>
        <td class='right'>₱{$costPrice}</td>
        <td class='right'>₱{$sellingPrice}</td>
        <td class='right'>{$qty}</td>
        <td><span class='status {$statusClass}'><span class='dot'></span>{$statusText}</span></td>
      </tr>
    ";
  }
} else {
  echo "<tr><td colspan='7' style='text-align:center;'>No branch inventory found</td></tr>";
}

$conn->close();
?>
</tbody>


      </table>
    </div>