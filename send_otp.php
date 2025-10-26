<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION["temp_user"])) {
    echo "Session expired. Please login again.";
    exit;
}

$user = $_SESSION["temp_user"];
$phone = $user["phone_number"];

$otp = rand(100000, 999999);
$_SESSION["otp"] = $otp;
$_SESSION["otp_time"] = time();

// Twilio API credentials
$account_sid = "";
$auth_token = "";
$from = "+19786446041"; // your Twilio number

$message = "Your OTP code is $otp. It will expire in 5 minutes.";

$data = [
    'To' => $phone,
    'From' => $from,
    'Body' => $message
];

$ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/$account_sid/Messages.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$account_sid:$auth_token");
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
$response = curl_exec($ch);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo "Error: " . curl_error($ch);
} else {
    $result = json_decode($response, true);
    if (isset($result["error_message"]) && $result["error_message"]) {
        echo "Twilio error: " . $result["error_message"];
    } elseif (isset($result["sid"])) {
        echo "✅ OTP sent successfully to $phone (SID: " . $result["sid"] . ")";
    } else {
        echo "❌ Unexpected response: " . $response;
    }
}
curl_close($ch);

