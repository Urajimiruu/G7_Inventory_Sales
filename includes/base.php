<?php
if (!isset($_SESSION["otp_verified"]) || !$_SESSION["otp_verified"]) {
    header("Location: index.php");
    exit();
}

// variables expected from parent file: $role, $page, $title, $description
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="css/admin.css">
  <link rel="stylesheet" href="css/users.css">
  <title><?= htmlspecialchars($title) ?></title>
</head>
<body>
  <div class="container">
    <?php include "sidebar.php"; ?>

    <main class="main-content">
      <?php include "topbar.php"; ?>

      <section class="module-header">
        <h2><?= htmlspecialchars($title) ?></h2>
        <p><?= htmlspecialchars($description) ?></p>
      </section>

      <section class="module-content">
        <?php include "modules/$page.php"; ?>
      </section>
    </main>
  </div>

  <script>
  document.addEventListener("DOMContentLoaded", function() {
    const dropdownButtons = document.querySelectorAll(".sidebar .dropdown > button");

    dropdownButtons.forEach(button => {
      button.addEventListener("click", function() {
        const parentLi = this.parentElement;
        const arrow = this.querySelector(".arrow");

        const isOpen = parentLi.classList.toggle("open");
        arrow.textContent = isOpen ? "▾" : "▸"; // change arrow direction

        // Optional: close others
        dropdownButtons.forEach(otherBtn => {
          if (otherBtn !== button) {
            otherBtn.parentElement.classList.remove("open");
            const otherArrow = otherBtn.querySelector(".arrow");
            if (otherArrow) otherArrow.textContent = "▸";
          }
        });
      });
    });
  });

  </script>


</body>
</html>
