<?php
session_start();
require 'db_connection.php'; 

// Make sure user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'shop') {
    // Not logged in or wrong role
    header("Location: index.php");
    exit();
}

// Get branch info
$branchName = "";

if (isset($_SESSION['branch_id'])) {
    $branchId = $_SESSION['branch_id'];

    $stmt = $conn->prepare("SELECT branch_name FROM Branches WHERE branch_id = ? LIMIT 1");
    $stmt->bind_param("i", $branchId);
    $stmt->execute();
    $result = $stmt->get_result();
    $branch = $result->fetch_assoc();

    if ($branch) {
        $branchName = $branch['branch_name'];
    } else {
        $branchName = "Unknown Branch";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Branch Dashboard</title>
</head>
<body>
    <h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h1>
    <h2>Branch: <?= htmlspecialchars($branchName) ?></h2>
</body>
</html>
