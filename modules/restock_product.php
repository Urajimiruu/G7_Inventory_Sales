<?php
// Enable full error reporting during development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Return JSON responses
header('Content-Type: application/json');

// Connect to database
require_once "../db_connection.php";

// Validate POST parameters
if (!isset($_POST['itemid'], $_POST['add_qty'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
    exit;
}

$itemId = (int) $_POST['itemid'];
$addQty = (int) $_POST['add_qty'];

if ($itemId <= 0 || $addQty <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID or quantity.']);
    exit;
}

// Make sure your table and columns exist
$sql = "UPDATE maininventory 
        SET quantity = quantity + ? 
        WHERE product_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

// Bind parameters: first ? = addQty, second ? = productId
$stmt->bind_param("ii", $addQty, $itemId);

// Execute update query
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $stmt->error]);
}

// Clean up
$stmt->close();
$conn->close();
?>
