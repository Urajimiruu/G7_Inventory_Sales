<div class="dashboard"> <!-- display sales data dito -->

<div class="grid grid--4-cols">
          <div class="feature">
            <ion-icon class="feature-icon" name="copy-outline"></ion-icon>
            <p class="feature-title">Products Available</p>
            <p class="feature-text">
              9583
            </p>
          </div>
          <div class="feature">
            <ion-icon class="feature-icon" name="pricetag-outline"></ion-icon>
            <p class="feature-title">Sales Made</p>
            <p class="feature-text">
              3534
            </p>
          </div>
          <div class="feature">
            <ion-icon class="feature-icon" name="storefront-outline"></ion-icon>
            <p class="feature-title">Branches</p>
            <p class="feature-text">
              4
            </p>
          </div>
          <div class="feature">
            <ion-icon class="feature-icon" name="person-outline"></ion-icon>
            <p class="feature-title">Users</p>
            <p class="feature-text">
              7
            </p>
          </div>
        </div>


<div class="grid grid--2-cols">

<div class="graph">
          <canvas id="salesChart"></canvas>
  <script>
    const ctx = document.getElementById('salesChart');

    new Chart(ctx, {
      type: 'bar', // other options: 'line', 'pie', 'doughnut', etc.
      data: {
        labels: ['January', 'February', 'March', 'April', 'May'],
        datasets: [{
          label: 'Sales (₱)',
          data: [1200, 1500, 1100, 1800, 1600],
          backgroundColor: '#4e79a7',
          borderRadius: 5
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: true },
          title: {
            display: true,
            text: 'Monthly Sales Data'
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { stepSize: 500 }
          }
        }
      }
    });
  </script>

</div>
<div class="graph">
<canvas id="myLineChart"></canvas>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('myLineChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
        datasets: [{
            label: 'Sales',
            data: [120, 150, 170, 140, 180, 200, 220],
            borderColor: 'blue',
            fill: false,
            tension: 0.3
        }]
    },
    options: {
        responsive: false,
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>
</div>
</div>

</div>
