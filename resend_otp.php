<?php
session_start();

        if (!isset($_SESSION["temp_user"])) {
            exit("No user session!");
        }

        $user = $_SESSION["temp_user"];
        $apiToken = "8573678c1478276334450776eda448118427e2a1"; 
        $url = "https://sms.iprogtech.com/api/v1/otp/send_otp";

        $otp = rand(100000, 999999);
        $_SESSION["otp_code"] = $otp;
        $_SESSION["otp_time"] = time();

        $phone = str_replace(' ', '', $user["phone_number"]);
        if (str_starts_with($phone, '0')) {
            $phone = '+63' . substr($phone, 1);
        }

        $message = "Your OTP is $otp";

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

        if (
            $httpCode == 200 &&
            (
                (isset($resp["status"]) && ($resp["status"] == 200 || strtolower($resp["status"]) == "success")) ||
                (isset($resp["message"]) && stripos($resp["message"], "OTP sent") !== false)
            )
        ) {
            echo "✅ OTP resent successfully! (" . $otp . ")";
        } else {
            $msg = isset($resp["message"]) ? $resp["message"] : "Unknown error or invalid API response.";
            echo "❌ Failed to resend OTP: " . htmlspecialchars($msg) . " | HTTP: $httpCode | Response: " . htmlspecialchars($response);
        }

?>
