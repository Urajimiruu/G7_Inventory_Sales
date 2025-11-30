<header class="topbar">

  <!-- Notification Bell -->
  <div class="notif-container">
    <ion-icon id="notifBell" name="notifications-outline"></ion-icon>
    <span id="notifCount" class="notif-count" style="display:none;"></span>

    <div id="notifDropdown" class="notif-dropdown">
      <div class="notif-header">Notifications</div>
      <div class="notif-content" id="notifContent"></div>
    </div>
  </div>

  <div class="user-info">
    <p><?= htmlspecialchars($_SESSION["username"]); ?></p>
    <p><?= ucfirst($_SESSION["role"]); ?></p>
  </div>

</header>


<script>
  const bell = document.getElementById("notifBell");
  const dropdown = document.getElementById("notifDropdown");
  const notifContent = document.getElementById("notifContent");
  const notifCount = document.getElementById("notifCount");

  let notifOpen = false;

  function loadNotifications() {
    fetch("modules/no_stock_notifications.php")
      .then(res => res.text())
      .then(html => {
        notifContent.innerHTML = html;

        // Count items
        let count = (html.match(/notif-item/g) || []).length;
        if (count > 0) {
          notifCount.textContent = count;
          notifCount.style.display = "block";
        } else {
          notifCount.style.display = "none";
        }
      });
  }

  bell.addEventListener("click", () => {
    notifOpen = !notifOpen;
    dropdown.style.display = notifOpen ? "block" : "none";

    if (notifOpen) loadNotifications();
  });

  // Close dropdown when clicking outside
  document.addEventListener("click", (e) => {
    if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.style.display = "none";
      notifOpen = false;
    }
  });

  document.addEventListener("DOMContentLoaded", loadNotifications);

</script>
