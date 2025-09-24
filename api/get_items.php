<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

// Check if user is logged in (but don't fail if not, just log it)
if (!isLoggedIn()) {
    // Log the issue but don't fail completely
    error_log("API get_items.php: User not logged in, but continuing...");
}

// Get category ID from request
$categoryId = $_GET['category_id'] ?? null;

if (!$categoryId) {
    http_response_code(400);
    echo json_encode(['error' => 'Category ID is required']);
    exit();
}

try {
    // Get items for the category (excluding soft-deleted items)
    $query = "SELECT i.*, c.name as category_name 
              FROM items i 
              LEFT JOIN categories c ON i.category_id = c.id 
              WHERE i.category_id = ? AND i.is_available = 1 AND i.is_deleted = 0 
              ORDER BY i.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$categoryId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each item, get size variants if they exist
    foreach ($items as &$item) {
        if ($item['has_size_variants']) {
            $sizeQuery = "SELECT size_name, size_price, display_order 
                         FROM item_size_variants 
                         WHERE item_id = ? AND is_active = 1 
                         ORDER BY display_order, size_name";
            $sizeStmt = $db->prepare($sizeQuery);
            $sizeStmt->execute([$item['id']]);
            $item['size_variants'] = $sizeStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $item['size_variants'] = [];
        }
    }
    
    // Return items as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'items' => $items,
        'category_id' => $categoryId,
        'count' => count($items)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 