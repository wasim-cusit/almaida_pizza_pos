<?php
/**
 * Process Order API
 * Fast Food POS System - Enhanced with QR Codes
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

// sanitize function is already defined in config/database.php

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Helper function to generate QR code data
function generateQRData($orderNumber, $totalAmount, $items) {
    $qrData = [
        'order_number' => $orderNumber,
        'total_amount' => $totalAmount,
        'items_count' => count($items),
        'timestamp' => date('Y-m-d H:i:s'),
        'pos_system' => 'Fast Food POS'
    ];
    return json_encode($qrData);
}

// Helper function to generate QR code URL
function generateQRCodeURL($data) {
    $encodedData = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . $encodedData;
}

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        throw new Exception('User not logged in');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $requiredFields = ['items', 'total_amount', 'payment_method'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Validate items array
    if (!is_array($input['items']) || empty($input['items'])) {
        throw new Exception('No items in order');
    }
    
    // Generate order number if not provided
    if (empty($input['order_number'])) {
        $input['order_number'] = 'ORD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Create customer record if customer info provided
        $customerId = null;
        if (!empty($input['customer_name'])) {
            $query = "INSERT INTO customers (name, postcode, phone, email, created_at) 
                      VALUES (?, ?, ?, ?, NOW()) 
                      ON DUPLICATE KEY UPDATE 
                      name = VALUES(name), 
                      postcode = VALUES(postcode), 
                      phone = VALUES(phone), 
                      email = VALUES(email)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                sanitize($input['customer_name']),
                sanitize($input['customer_postcode'] ?? ''),
                sanitize($input['customer_phone'] ?? ''),
                sanitize($input['customer_email'] ?? '')
            ]);
            $customerId = $db->lastInsertId();
        }
        
        // Calculate tax and total
        $subtotal = $input['total_amount'];
        $taxRate = 0.15; // 15% tax rate
        $taxAmount = $subtotal * $taxRate;
        $totalAmount = $subtotal + $taxAmount;
        
        // Get user's branch_id
        $branchId = $_SESSION['branch_id'] ?? null;
        if (!$branchId) {
            throw new Exception('User not assigned to any branch');
        }
        
        // Create order record
        $query = "INSERT INTO orders (
            order_number, user_id, customer_id, order_type, 
            subtotal, tax_amount, total_amount, payment_method, payment_status, 
            order_status, notes, table_number, branch_id, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'pending', ?, ?, ?, NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            sanitize($input['order_number']),
            $_SESSION['user_id'],
            $customerId,
            $input['order_type'] ?? 'dine_in',
            $subtotal,
            $taxAmount,
            $totalAmount,
            $input['payment_method'],
            sanitize($input['notes'] ?? ''),
            sanitize($input['table_number'] ?? ''),
            $branchId
        ]);
        
        $orderId = $db->lastInsertId();
        
        // Insert order items
        $query = "INSERT INTO order_items (
            order_id, item_id, item_name, size_name, quantity, 
            unit_price, total_price, notes, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $db->prepare($query);
        
        foreach ($input['items'] as $item) {
            // Create item name with size if available
            $itemName = $item['name'];
            if (isset($item['sizeName']) && !empty($item['sizeName'])) {
                $itemName = $item['name'] . ' (' . $item['sizeName'] . ')';
            }
            
            $stmt->execute([
                $orderId,
                $item['id'],
                sanitize($itemName),
                sanitize($item['sizeName'] ?? ''),
                $item['quantity'],
                $item['price'],
                $item['totalPrice'],
                sanitize($item['notes'] ?? '')
            ]);
        }
        
        // Commit transaction
        $db->commit();
        
        // Get the complete order for response
        $query = "SELECT o.*, u.name as user_name, u.username, c.name as customer_name, c.contact as customer_phone
                  FROM orders o 
                  LEFT JOIN users u ON o.user_id = u.id 
                  LEFT JOIN customers c ON o.customer_id = c.id 
                  WHERE o.id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get order items
        $query = "SELECT * FROM order_items WHERE order_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $order['items'] = $orderItems;
        
        // Generate QR code data
        $qrData = generateQRData($order['order_number'], $totalAmount, $orderItems);
        $qrCodeURL = generateQRCodeURL($qrData);
        
        // Prepare print data
        $printData = [
            'order_number' => $order['order_number'],
            'date_time' => date('Y-m-d H:i:s'),
            'cashier' => $order['user_name'] ?? 'Admin',
            'customer' => $order['customer_name'] ?? 'Walk-in Customer',
            'table_number' => $order['table_number'] ?? '',
            'items' => $orderItems,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'payment_method' => $input['payment_method'],
            'qr_code_url' => $qrCodeURL,
            'qr_data' => $qrData
        ];
        
        // Return success response with enhanced data
        echo json_encode([
            'success' => true,
            'message' => 'Order processed successfully',
            'order' => $order,
            'order_id' => $orderId,
            'print_data' => $printData,
            'qr_code_url' => $qrCodeURL,
            'qr_data' => $qrData
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
        'error' => 'Order processing failed'
    ]);
}
?> 