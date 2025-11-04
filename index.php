<?php
session_start();
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    unset($_SESSION["otp_sent"]);
}

require 'db_connection.php';
$error = "";

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
        $_SESSION["temp_user"] = $user;
        $_SESSION["otp_verified"] = false;

        // generate OTP
        $otp = rand(100000, 999999);
        $_SESSION["otp_code"] = $otp;
        $_SESSION["otp_time"] = time();

        // format phone number
        $phone = $user["phone_number"];
        $phone = str_replace(' ', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '+63' . substr($phone, 1);
        }

        // send via iprogtech
        $apiToken = "8573678c1478276334450776eda448118427e2a1"; 
        $url = "https://sms.iprogtech.com/api/v1/otp/send_otp";

        $message = "Your OTP is $otp"; // ✅ replaced placeholder with actual number

        $data = [
            "api_token" => $apiToken,
            "phone_number" => $phone,
            "message" => "Your OTP is :otp"
        ];

        $payload = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resp = json_decode($response, true);

        if ($httpCode == 200) {
            if (
                (isset($resp["status"]) && ($resp["status"] == 200 || strtolower($resp["status"]) == "success")) ||
                (isset($resp["message"]) && stripos($resp["message"], "OTP sent") !== false)
            ) {
                $_SESSION["otp_sent"] = true;
                // echo "<pre>";
                // echo "✅ OTP Sent Successfully\n";
                // echo "Phone: " . $phone . "\n";
                // echo "Message: " . $message . "\n";
                // echo "Response: " . htmlspecialchars(json_encode($resp, JSON_PRETTY_PRINT)) . "\n";
                // echo "</pre>";
                // exit;
            } else {
                $error = "OTP may have been sent, but API returned: " . htmlspecialchars(json_encode($resp));
            }
        } else {
            $error = "❌ Failed to send OTP. HTTP Code: " . $httpCode . " | Response: " . htmlspecialchars($response);
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
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="css/style.css">
  <title>Login Page</title>
</head>

<body>
<div class="login-container">
  <!-- Left Side -->
  <div class="login-left">
    <div class="brand-section">
      <div class="company-logo"></div>
      <h1 class="brand-title">BIZZTRACK</h1>
      <p class="brand-subtitle">Integrated Sales and Inventory Monitoring</p>
    </div>
  </div>

  <!-- Right Side -->
  <div class="login-right">
    <div class="login-box">
      <?php if (!empty($error)): ?>
        <p class="error-msg"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <?php if (empty($_SESSION["otp_sent"])): ?>
        <h2>USER LOGIN</h2>
        <form method="POST" action="">
          <label>USERNAME :</label>
          <input type="text" name="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" required>

          <label>PASSWORD :</label>
          <input type="password" name="password" required>

          <button type="submit" class="btn-login">Login</button>
        </form>
      <?php else: ?>
        <h2>Enter OTP</h2>
        <div id="otp-section">
          <input type="text" id="otp" maxlength="6" placeholder="6-digit OTP" class="textbox" style="width:200px;"><br><br>
          <button id="verifyBtn" onclick="verifyOTP()">Verify OTP</button>
          <button id="resendBtn" onclick="resendOTP()" disabled>Resend OTP (<span id="countdown">30</span>s)</button>
          <p id="otpMessage" style="color:green;"></p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
let cooldown = 30;
let timer;

window.onload = () => {
  if (document.getElementById('resendBtn')) startCooldown();
};

function verifyOTP() {
  const otp = document.getElementById('otp').value.trim();
  const messageBox = document.getElementById('otpMessage');
  
  if (otp === "") {
    messageBox.style.color = "red";
    messageBox.innerText = "Please enter the OTP.";
    return;
  }

  fetch('verify_otp.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'otp=' + encodeURIComponent(otp)
  })
  .then(res => res.text())
  .then(data => {
    messageBox.innerText = data;
    if (data.toLowerCase().includes("verified") || data.toLowerCase().includes("success")) {
      messageBox.style.color = "green";
      setTimeout(() => { window.location.href = "redirect.php"; }, 1500);
    } else {
      messageBox.style.color = "red";
    }
  })
  .catch(err => {
    messageBox.style.color = "red";
    messageBox.innerText = "Error verifying OTP.";
    console.error(err);
  });
}

function resendOTP() {
  fetch('resend_otp.php')
    .then(res => res.text())
    .then(data => {
      const messageBox = document.getElementById('otpMessage');
      messageBox.style.color = data.toLowerCase().includes("success") ? "green" : "red";
      messageBox.innerText = data;
      startCooldown();
    })
    .catch(err => {
      document.getElementById('otpMessage').innerText = "Error resending OTP.";
      console.error(err);
    });
}

function startCooldown() {
  cooldown = 30;
  const btn = document.getElementById('resendBtn');
  btn.disabled = true;
  document.getElementById('countdown').innerText = cooldown;

  clearInterval(timer);
  timer = setInterval(() => {
    cooldown--;
    document.getElementById('countdown').innerText = cooldown;
    if (cooldown <= 0) {
      clearInterval(timer);
      btn.disabled = false;
      document.getElementById('countdown').innerText = "0";
    }
  }, 1000);
}
</script>
</body>
</html>
