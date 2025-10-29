<header class="topbar">
  <div class="user-info">
    <p><?= htmlspecialchars($_SESSION["username"]); ?></p>
    <p><?= ucfirst($_SESSION["role"]); ?></p>
  </div>
</header>
