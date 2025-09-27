<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid item ID']);
    exit;
}

try {
    $db->beginTransaction();
    
    // Check if item is used in any orders
    $query = "SELECT COUNT(*) as count FROM order_items WHERE item_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $orderCount = $stmt->fetch()['count'];
    
    if ($orderCount > 0) {
        // Item is used in orders, perform soft delete
        $query = "UPDATE items SET is_deleted = 1, is_available = 0 WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        
        $db->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Item soft deleted (used in ' . $orderCount . ' orders)',
            'type' => 'soft_delete',
            'order_count' => $orderCount
        ]);
    } else {
        // Item is not used in orders, perform hard delete
        // Delete size variants first
        $query = "DELETE FROM item_size_variants WHERE item_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        
        // Delete the item
        $query = "DELETE FROM items WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        
        $db->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Item permanently deleted',
            'type' => 'hard_delete'
        ]);
    }
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
