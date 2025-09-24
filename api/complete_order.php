    <?php
/**
 * Complete Order API
 * Fast Food POS System
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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    // Extract order data
    $items = $input['items'] ?? [];
    $customerInfo = $input['customer'] ?? [];
    $orderType = $input['order_type'] ?? 'dine_in';
    $tableNumber = $input['table_number'] ?? null;
    $paymentMethod = $input['payment_method'] ?? 'cash';
    $notes = $input['notes'] ?? '';
    
    if (empty($items)) {
        throw new Exception('No items in order');
    }
    
    // Calculate totals
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += ($item['price'] * $item['quantity']);
    }
    
    $taxAmount = $subtotal * 0.15; // 15% tax
    $totalAmount = $subtotal + $taxAmount;
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Generate order number
        $orderNumber = 'ORD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insert customer if provided
        $customerId = null;
        if (!empty($customerInfo['name']) || !empty($customerInfo['contact'])) {
            $customerQuery = "INSERT INTO customers (name, contact, email, address, postcode, created_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE contact = VALUES(contact), address = VALUES(address), postcode = VALUES(postcode)";
            $customerStmt = $db->prepare($customerQuery);
            $customerStmt->execute([
                $customerInfo['name'] ?? '',
                $customerInfo['contact'] ?? '',
                $customerInfo['email'] ?? '',
                $customerInfo['address'] ?? '',
                $customerInfo['postcode'] ?? ''
            ]);
            $customerId = $db->lastInsertId();
        }
        
        // Insert order
        $orderQuery = "INSERT INTO orders (
            order_number, user_id, customer_id, order_type, table_number,
            subtotal, tax_amount, total_amount, payment_method, notes, order_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $orderStmt = $db->prepare($orderQuery);
        $orderStmt->execute([
            $orderNumber,
            $_SESSION['user_id'],
            $customerId,
            $orderType,
            $tableNumber,
            $subtotal,
            $taxAmount,
            $totalAmount,
            $paymentMethod,
            $notes
        ]);
        
        $orderId = $db->lastInsertId();
        
        // Insert order items
        $itemQuery = "INSERT INTO order_items (
            order_id, item_id, item_name, size_name, quantity, unit_price, total_price, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $itemStmt = $db->prepare($itemQuery);
        
        foreach ($items as $item) {
            // Create item name with size if available
            $itemName = $item['name'];
            if (isset($item['sizeName']) && !empty($item['sizeName'])) {
                $itemName = $item['name'] . ' (' . $item['sizeName'] . ')';
            }
            
            $itemStmt->execute([
                $orderId,
                $item['id'],
                $itemName,
                $item['sizeName'] ?? '',
                $item['quantity'],
                $item['price'],
                $item['price'] * $item['quantity'],
                $item['notes'] ?? ''
            ]);
        }
        
        // Commit transaction
        $db->commit();
        
        // Return success response with order details
        echo json_encode([
            'success' => true,
            'message' => 'Order completed successfully',
            'order' => [
                'id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'order_type' => $orderType,
                'table_number' => $tableNumber,
                'payment_method' => $paymentMethod,
                'customer' => $customerInfo
            ]
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
        'error' => 'Order completion failed'
    ]);
}
?> 