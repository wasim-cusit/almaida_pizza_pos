<?php
/**
 * Search Items API
 * Fast Food POS System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    // Get search query from request
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($query)) {
        echo json_encode([
            'success' => true,
            'items' => [],
            'count' => 0
        ]);
        exit;
    }
    
    // Search items by name or description (excluding soft-deleted items)
    $searchQuery = "SELECT i.id, i.name, i.price, i.description, i.image, c.name as category_name 
                    FROM items i 
                    JOIN categories c ON i.category_id = c.id 
                    WHERE i.is_available = 1 AND i.is_deleted = 0
                    AND (i.name LIKE ? OR i.description LIKE ?) 
                    ORDER BY i.name 
                    LIMIT 50";
    
    $searchTerm = "%$query%";
    $stmt = $db->prepare($searchQuery);
    $stmt->execute([$searchTerm, $searchTerm]);
    $items = $stmt->fetchAll();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'items' => $items,
        'query' => $query,
        'count' => count($items)
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => 'Search failed'
    ]);
}
?> 