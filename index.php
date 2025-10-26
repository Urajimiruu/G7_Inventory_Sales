<?php
session_start();
require 'db_connection.php'; 

$error = "";

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
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
        $_SESSION["temp_user"] = $user; // store temporarily until OTP verified
        $_SESSION["otp_verified"] = false;
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
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet"/>
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

    <?php if (!empty($error)): ?>
      <p style="color: red; font-weight: bold;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form class="login" method="POST" action=""> 
      <input name="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" 
             class="textbox" placeholder="Username" required><br><br><br><br>

      <input type="password" name="password" class="textbox" placeholder="Password" required><br><br><br><br>

      <button class="signin" type="submit">Sign in</button>
    </form>

    <?php if (isset($_SESSION["temp_user"]) && !$_SESSION["otp_verified"]): ?>
      <div id="otp-section" style="margin-top:20px;">
        <h3>Enter OTP</h3>
        <input type="text" id="otp" maxlength="6" placeholder="6-digit OTP" class="textbox" style="width:200px;"><br><br>
        <button id="verifyBtn" onclick="verifyOTP()">Verify OTP</button>
        <button id="resendBtn" onclick="resendOTP()" disabled>Resend OTP (<span id="countdown">30</span>s)</button>
        <p id="otpMessage" style="color:green;"></p>
      </div>

      <script>
      let cooldown = 30;
      let timer;

      // Automatically send OTP on load
      window.onload = () => {
          sendOTP();
          startCooldown();
      };

      function sendOTP() {
        fetch('send_otp.php')
          .then(res => res.text())
          .then(data => {
            document.getElementById('otpMessage').innerText = data;
          });
      }

      function verifyOTP() {
        const otp = document.getElementById('otp').value;
        fetch('verify_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'otp=' + otp
        })
        .then(res => res.text())
        .then(data => {
          document.getElementById('otpMessage').innerText = data;
          if (data.includes("Login success")) {
            window.location.href = "redirect.php"; // handles redirect based on role
          }
        });
      }

      function resendOTP() {
        sendOTP();
        startCooldown();
      }

      function startCooldown() {
        cooldown = 30;
        document.getElementById('resendBtn').disabled = true;
        timer = setInterval(() => {
          cooldown--;
          document.getElementById('countdown').innerText = cooldown;
          if (cooldown <= 0) {
            clearInterval(timer);
            document.getElementById('resendBtn').disabled = false;
          }
        }, 1000);
      }
      </script>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
