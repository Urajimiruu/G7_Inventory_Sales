<?php
session_start();

if (!isset($_SESSION["otp_verified"]) || !$_SESSION["otp_verified"]) {
    header("Location: index.php");
    exit;
}

if (strtolower($_SESSION["role"]) === "admin") {
    header("Location: admin_home.php");
} elseif (strtolower($_SESSION["role"]) === "shop") {
    header("Location: branchdb.php");
} else {
    echo "Unknown role.";
}
exit;
