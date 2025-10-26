<?php
session_start();

if (!isset($_SESSION["otp"]) || !isset($_POST["otp"])) {
    echo "OTP session not found.";
    exit;
}

$entered = $_POST["otp"];
$real = $_SESSION["otp"];
$time_sent = $_SESSION["otp_time"];

if ((time() - $time_sent) > 300) { // 5 minutes
    unset($_SESSION["otp"]);
    echo "OTP expired. Please resend.";
    exit;
}

if ($entered == $real) {
    $_SESSION["otp_verified"] = true;
    $_SESSION["user_id"]   = $_SESSION["temp_user"]["user_id"];
    $_SESSION["username"]  = $_SESSION["temp_user"]["username"];
    $_SESSION["role"]      = $_SESSION["temp_user"]["role"];
    $_SESSION["branch_id"] = $_SESSION["temp_user"]["branch_id"];
    unset($_SESSION["temp_user"]);
    echo "Login success";
} else {
    echo "Invalid OTP.";
}
