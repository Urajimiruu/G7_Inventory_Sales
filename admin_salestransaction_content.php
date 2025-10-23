

<section class="transaction-box">
        <h3>SALES TRANSACTION</h3>

        <!-- Filter Section -->
        <div class="filters">
          <label>Filter</label>
          <select>
            <option>All shops</option>
            <option>Shop A</option>
            <option>Shop B</option>
          </select>

          <label>Filter</label>
          <select>
            <option>All products</option>
            <option>Product A</option>
            <option>Product B</option>
          </select>

          <label>From</label>
          <input type="date">
          <label>To</label>
          <input type="date">

          <button class="add-btn">Add</button>
        </div>

        <!-- Table Section -->
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>Product</th>
                <th>Branch</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>2025-10-20</td>
                <td>Product X</td>
                <td>Lipa</td>
                <td>10</td>
                <td>$15.00</td>
                <td>$150.00</td>
                <td>
                  <button class="edit-btn">Edit</button>
                  <button class="delete-btn">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>