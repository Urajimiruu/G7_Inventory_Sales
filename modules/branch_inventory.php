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
            <th scope="col">Product</th>
            <th scope="col">Unit</th>
            <th scope="col" class="right">Cost Price</th>
            <th scope="col" class="right">Selling Price</th>
            <th scope="col" class="right">Quantity</th>
            <th scope="col">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Wireless Mouse</td>
            <td class="muted">Peripherals</td>
            <td class="right">$20.00</td>
            <td class="right">$24.99</td>
            <td class="right">4</td>
            <td><span class="status on-stock"><span class="dot"></span>On Stock</span></td>
          </tr>
          <tr>
            <td>Mechanical Keyboard</td>
            <td class="muted">Peripherals</td>
            <td class="right">$70.00</td>
            <td class="right">$89.00</td>
            <td class="right">5</td>
            <td><span class="status low-stock"><span class="dot"></span>Low Stock</span></td>
          </tr>
          <tr>
            <td>USB-C Hub</td>
            <td class="muted">Accessories</td>
            <td class="right">$30.00</td>
            <td class="right">$39.50</td>
            <td class="right">2</td>
            <td><span class="status no-stock"><span class="dot"></span>No Stock</span></td>
          </tr>
        </tbody>
      </table>
    </div>