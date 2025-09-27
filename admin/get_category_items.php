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

$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

if ($category_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid category ID']);
    exit;
}

try {
    // Get items in the category
    $query = "SELECT i.*, c.name as category_name 
              FROM items i 
              JOIN categories c ON i.category_id = c.id 
              WHERE i.category_id = ? AND i.is_deleted = 0
              ORDER BY i.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$category_id]);
    $items = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
