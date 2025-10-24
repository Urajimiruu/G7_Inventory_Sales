<?php
$servername = "localhost";  // or 127.0.0.1
$username   = "root";
$password   = "p@ssw0rd";
$dbname     = "SalesAndInventory";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
?>
