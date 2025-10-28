<?php
session_start();
require 'db_connection.php'; 

$error = "";
$showOTPModal = false;

// Handle login form
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["username"], $_POST["password"])) {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT user_id, username, password_hash, role, branch_id, phone_number 
                            FROM Users 
                            WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user["password_hash"])) {
        // Temporarily store user data until OTP is verified
        $_SESSION["temp_user"] = [
            "user_id" => $user["user_id"],
            "username" => $user["username"],
            "role" => $user["role"],
            "branch_id" => $user["branch_id"],
            "phone_number" => $user["phone_number"]
        ];

        $_SESSION["otp_verified"] = false;
        $_SESSION["otp_code"] = "123456"; // mock OTP for now
        $showOTPModal = true;
    } else {
        $error = "Invalid username or password!";
    }
}

// Handle OTP verification
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["otp_code"])) {
    if (isset($_SESSION["otp_code"]) && $_POST["otp_code"] === $_SESSION["otp_code"]) {
        $_SESSION["otp_verified"] = true;

        // Move temp_user info into active session
        if (isset($_SESSION["temp_user"])) {
            $_SESSION["user_id"] = $_SESSION["temp_user"]["user_id"];
            $_SESSION["username"] = $_SESSION["temp_user"]["username"];
            $_SESSION["role"] = $_SESSION["temp_user"]["role"];
            $_SESSION["branch_id"] = $_SESSION["temp_user"]["branch_id"];
            $_SESSION["phone_number"] = $_SESSION["temp_user"]["phone_number"];
            unset($_SESSION["temp_user"]);
        }

        header("Location: redirect.php");
        exit;
    } else {
        $error = "Incorrect OTP. Try again.";
        // Don't reopen modal — just let user reattempt login
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
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="css/style.css">
  <title>Login Page</title>
</head>

<body>
<div class="login-container">
  <!-- Left -->
  <div class="login-left">
    <div class="brand-section">
      <div class="company-logo"></div>
      <h1 class="brand-title">BIZZTRACK</h1>
      <p class="brand-subtitle">Integrated Sales and Inventory Monitoring</p>
    </div>
  </div>

  <!-- Right -->
  <div class="login-right">
    <div class="login-box">
      <h2>USER LOGIN</h2>
      <?php if (!empty($error)): ?>
        <p class="error-msg"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="POST" action="">
        <label>USERNAME :</label>
        <input type="text" name="username" required>

        <label>PASSWORD :</label>
        <input type="password" name="password" required>

        <button type="submit" class="btn-login">Login</button>
      </form>
    </div>
  </div>
</div>

<!-- OTP Modal -->
<div id="otpModal" class="modal">
  <div class="modal-content">
    <h3>Enter OTP</h3>
    <form method="POST" action="">
      <input type="text" name="otp_code" maxlength="6" placeholder="6-digit code" required>
      <button type="submit">Verify</button>
    </form>
  </div>
</div>

<?php if ($showOTPModal): ?>
<script>
  document.getElementById('otpModal').style.display = 'flex';
</script>
<?php endif; ?>

</body>

</html>
