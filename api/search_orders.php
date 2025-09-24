<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get search query
$query = $_GET['q'] ?? '';

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Search query too short']);
    exit;
}

try {
    // Search orders by order number, customer name, or contact
    $searchQuery = "SELECT o.*, u.name as user_name, c.name as customer_name, c.contact as customer_phone
                    FROM orders o 
                    LEFT JOIN users u ON o.user_id = u.id 
                    LEFT JOIN customers c ON o.customer_id = c.id 
                    WHERE o.order_number LIKE ? 
                       OR c.name LIKE ? 
                       OR c.contact LIKE ?
                    ORDER BY o.created_at DESC 
                    LIMIT 20";
    
    $searchTerm = "%{$query}%";
    $stmt = $db->prepare($searchQuery);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $orders = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 