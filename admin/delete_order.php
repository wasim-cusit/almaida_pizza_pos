<?php
/**
 * Delete Order API
 * Fast Food POS System - Admin Panel
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Admin privileges required.'
    ]);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['order_id'])) {
        throw new Exception('Order ID is required');
    }
    
    $orderId = intval($input['order_id']);
    
    if ($orderId <= 0) {
        throw new Exception('Invalid order ID');
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Check if order exists
        $query = "SELECT id, order_number FROM orders WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        // Delete order items first (foreign key constraint)
        $query = "DELETE FROM order_items WHERE order_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$orderId]);
        
        // Delete the order
        $query = "DELETE FROM orders WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$orderId]);
        
        // Commit transaction
        $db->commit();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Order deleted successfully',
            'order_number' => $order['order_number']
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => 'Order deletion failed'
    ]);
}
?> 