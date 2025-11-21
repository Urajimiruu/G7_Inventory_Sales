<?php
// session_start();
// require 'db_connection.php'; 

// if (!isset($_SESSION["temp_user"])) {
//     exit("No user session found. Please login again.");
// }

// $user = $_SESSION["temp_user"];
// $phone = str_replace(' ', '', $user["phone_number"]);
// if (str_starts_with($phone, '0')) {
//     $phone = '+63' . substr($phone, 1);
// }

// if (!isset($_POST['otp']) || empty($_POST['otp'])) {
//     exit("No OTP provided.");
// }

// $otp = trim($_POST['otp']);
// $apiToken = "8573678c1478276334450776eda448118427e2a1";
// $url = "https://sms.iprogtech.com/api/v1/otp/verify_otp";


// $data = [
//     "api_token" => $apiToken,
//     "phone_number" => $phone,
//     "otp" => $otp
// ];

// $payload = json_encode($data);

// $ch = curl_init($url);
// curl_setopt($ch, CURLOPT_POST, true);
// curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
// $response = curl_exec($ch);
// $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// curl_close($ch);


// $resp = json_decode($response, true);

// if ($httpCode == 200 && isset($resp["status"]) && $resp["status"] === "success") {
//     $_SESSION["otp_verified"] = true;

    
//     $_SESSION["user_id"] = $user["user_id"];
//     $_SESSION["username"] = $user["username"];
//     $_SESSION["role"] = $user["role"];
//     $_SESSION["branch_id"] = $user["branch_id"];
//     unset($_SESSION["temp_user"]);

  
//     echo "success:";
//     exit;
// } else {
//     if (isset($resp["message"])) {
//         exit("Invalid OTP: " . htmlspecialchars($resp["message"]));
//     } else {
//         exit("Invalid OTP or server error.");
//     }
// }



session_start();
require 'db_connection.php'; 

if (!isset($_SESSION["temp_user"])) {
    exit("No user session found. Please login again.");
}

$user = $_SESSION["temp_user"];

if (!isset($_POST['otp']) || empty($_POST['otp'])) {
    exit("No OTP provided.");
}

// Mock OTP verification (for testing)
// You can change the mock OTP here:
$mockOtp = "123456";

$enteredOtp = trim($_POST['otp']);

if ($enteredOtp === $mockOtp) {
    // Simulate successful verification
    $_SESSION["otp_verified"] = true;
    $_SESSION["user_id"] = $user["user_id"];
    $_SESSION["username"] = $user["username"];
    $_SESSION["role"] = $user["role"];
    $_SESSION["branch_id"] = $user["branch_id"];
    unset($_SESSION["temp_user"]);

    echo "OTP Verified Successfully";
    exit;
} else {
    echo "Invalid OTP. Try again.";
    exit;
}

// ?>
