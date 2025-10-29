<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Redirect back to login
header("Location: index.php");
exit;
?>
