<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Generate the next sequential order number
$orderNumber = generateOrderNumber();

// Return the order number as JSON
header('Content-Type: application/json');
echo json_encode(['order_number' => $orderNumber]);
?> 