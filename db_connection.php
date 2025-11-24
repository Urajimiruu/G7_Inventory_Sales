<?php
$servername = "localhost";  // or 127.0.0.1
$username   = "root";
$password   = "Vladimir#1124";
$dbname     = "SalesAndInventory";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
?>
