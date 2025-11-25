<?php
$servername = "mysql1001.site4now.net";  // or 127.0.0.1
$username   = "ab998d_sales";
$password   = "p@ssw0rd";
$dbname     = "db_ab998d_sales";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
?>
