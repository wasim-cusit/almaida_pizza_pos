<?php
/**
 * Update Order Status API
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

// Check if user is logged in and is admin or cashier
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'cashier')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Admin or cashier privileges required.'
    ]);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['order_id']) || !isset($input['status'])) {
        throw new Exception('Order ID and status are required');
    }
    
    $orderId = intval($input['order_id']);
    $status = $input['status'];
    
    if ($orderId <= 0) {
        throw new Exception('Invalid order ID');
    }
    
    // Validate status
    $validStatuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Invalid status. Valid statuses are: ' . implode(', ', $validStatuses));
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Check if order exists and belongs to user's branch
        $query = "SELECT id, order_number, order_status FROM orders WHERE id = ?";
        $params = [$orderId];
        
        // Add branch filtering for non-super-admin users
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
            if (isset($_SESSION['branch_id']) && $_SESSION['branch_id']) {
                $query .= " AND branch_id = ?";
                $params[] = $_SESSION['branch_id'];
            } else {
                throw new Exception('Access denied: No branch assigned');
            }
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order not found or access denied');
        }
        
        // Update order status (with branch check for non-super-admin users)
        $query = "UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?";
        $params = [$status, $orderId];
        
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
            if (isset($_SESSION['branch_id']) && $_SESSION['branch_id']) {
                $query .= " AND branch_id = ?";
                $params[] = $_SESSION['branch_id'];
            }
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        // Commit transaction
        $db->commit();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully',
            'order_number' => $order['order_number'],
            'old_status' => $order['order_status'],
            'new_status' => $status
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
        'error' => 'Order status update failed'
    ]);
}
?> 