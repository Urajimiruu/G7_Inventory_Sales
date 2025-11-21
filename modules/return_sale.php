<?php
require_once "../db_connection.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saleId = (int)($_POST['sale_id'] ?? 0);
    
    if ($saleId > 0) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get sale details first
            $stmt = $conn->prepare("SELECT product_id, quantity, branch_id FROM sales WHERE sale_id = ? AND status = 'active'");
            $stmt->bind_param("i", $saleId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $sale = $result->fetch_assoc();
                
                // Update inventory (increase stock - return to inventory)
                $updateStmt = $conn->prepare("UPDATE branchinventory SET quantity = quantity + ? WHERE product_id = ? AND branch_id = ?");
                $updateStmt->bind_param("iii", $sale['quantity'], $sale['product_id'], $sale['branch_id']);
                $updateStmt->execute();
                
                // Update sale status to 'returned' instead of deleting
                $updateSaleStmt = $conn->prepare("UPDATE sales SET status = 'returned' WHERE sale_id = ?");
                $updateSaleStmt->bind_param("i", $saleId);
                
                if ($updateSaleStmt->execute()) {
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'Sale returned successfully']);
                } else {
                    throw new Exception('Failed to update sale status');
                }
            } else {
                throw new Exception('Sale not found or already returned');
            }
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>