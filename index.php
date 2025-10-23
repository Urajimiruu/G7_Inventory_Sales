<?php
session_start();
require 'db_connection.php'; 

$error = "";

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Prepared statement with MySQLi
    $stmt = $conn->prepare("SELECT user_id, username, password_hash, role, branch_id 
                            FROM Users 
                            WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

   if ($user && password_verify($password, $user["password_hash"])) {
    $_SESSION["user_id"]   = $user["user_id"];
    $_SESSION["username"]  = $user["username"];
    $_SESSION["role"]      = $user["role"];
    $_SESSION["branch_id"] = $user["branch_id"];

    
    if (strtolower($user["role"]) === "admin") {
        header("Location: admin_home.php");
        exit();
    } elseif (strtolower($user["role"]) === "shop") {
        header("Location: branchdb.php");
        exit();
    }
} else {
    $error = "Invalid username or password!";
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="preconnect" href="https://fonts.gstatic.com" />
  <link
    href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap"
    rel="stylesheet"
  />
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/general.css">
  <title>Login Page</title>
</head>

<body>
<div class="centered-box">

  <div class="box">
    <h1 class="h1">Welcome!</h1><br><br><br>
    <h2>Lorem ipsum dolor sit amet, consectetur adipiscing elit...</h2>
  </div>

  <div class="box-2">
    <h1 class="h1">Login</h1><br><br><br>

    <!-- Error message -->
    <?php if (!empty($error)): ?>
      <p style="color: red; font-weight: bold;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form class="login" method="POST" action=""> 
      <input name="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" 
             class="textbox" placeholder="Username" required><br><br><br><br>

      <input type="password" name="password" class="textbox" placeholder="Password" required><br><br><br><br>

      <button class="signin" type="submit">Sign in</button>
    </form>
  </div>
</div>
</body>
</html>
