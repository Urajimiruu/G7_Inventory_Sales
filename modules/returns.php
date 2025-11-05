<div class="dashboard">

  <!-- Filters -->
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
        <select name="role2">
          <option value="">Product</option>
          <option value="Admin">Admin</option>
          <option value="Owner">Owner</option>
          <option value="Renter">Renter</option>
        </select>

        <label>| From:</label>
        <input type="date" id="from-date" name="from_date">

        <label>To:</label>
        <input type="date" id="to-date" name="to_date">
      </div> 

    </div> 
  </form>

  <!-- Table -->
  <div class="table-scroll" role="region" aria-label="Products table">
    <table class="vertical" aria-describedby="caption-vertical">
      <thead>
        <tr>
          <th scope="col">Date</th>
          <th scope="col">Product</th>
          <th scope="col">Branch</th>
          <th scope="col" class="right">Quantity</th>
          <th scope="col" class="right">Unit Price</th>
          <th scope="col" class="right">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>11/4/2025</td>
          <td class="muted">Wireless Mouse</td>
          <td class="right">Lipa</td>
          <td class="right">16</td>
          <td class="right">$30.00</td>
          <td class="right">$64345.00</td>
        </tr>
        <tr>
          <td>11/4/2025</td>
          <td class="muted">Monitor</td>
          <td class="right">Lemery</td>
          <td class="right">5</td>
          <td class="right">$300.00</td>
          <td class="right">$3545455.00</td>
        </tr>
        <tr>
          <td>11/4/2025</td>
          <td class="muted">Keyboard</td>
          <td class="right">Lipa</td>
          <td class="right">126</td>
          <td class="right">$60.00</td>
          <td class="right">$454454.00</td>
        </tr>
      </tbody>
    </table>
  </div>

</div>
