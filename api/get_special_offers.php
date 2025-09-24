<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Fetch active special offers
    $query = "SELECT * FROM special_offers WHERE is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'offers' => $offers
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 