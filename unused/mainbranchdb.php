<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Main Admin Dashboard</title>
</head>
<body>
    <h1>Welcome Admin <?= htmlspecialchars($_SESSION["username"]); ?>!</h1>
</body>
</html>
