<?php
/**
 * Enhanced Order Processing API with Automatic Stock Deduction
 * Fast Food POS System - Multi-Branch Stock Management
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

// Function to get user's branch
function getUserBranch($user_id) {
    global $db;
    
    $query = "SELECT branch_id FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    return $result ? $result['branch_id'] : null;
}

// Function to check stock availability
function checkStockAvailability($branch_id, $items) {
    global $db;
    
    foreach ($items as $item) {
        $query = "SELECT current_stock FROM branch_items WHERE branch_id = ? AND item_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$branch_id, $item['id']]);
        $stock = $stmt->fetch();
        
        $available_stock = $stock ? $stock['current_stock'] : 0;
        
        if ($available_stock < $item['quantity']) {
            return [
                'available' => false,
                'item_id' => $item['id'],
                'item_name' => $item['name'],
                'requested' => $item['quantity'],
                'available_stock' => $available_stock
            ];
        }
    }
    
    return ['available' => true];
}

// Function to deduct stock
function deductStock($branch_id, $items, $order_id) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        foreach ($items as $item) {
            // Get current stock
            $query = "SELECT current_stock FROM branch_items WHERE branch_id = ? AND item_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$branch_id, $item['id']]);
            $stock = $stmt->fetch();
            
            if ($stock) {
                $previous_stock = $stock['current_stock'];
                $new_stock = $previous_stock - $item['quantity'];
                
                // Update branch stock
                $query = "UPDATE branch_items SET current_stock = ? WHERE branch_id = ? AND item_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$new_stock, $branch_id, $item['id']]);
                
                // Record stock movement
                $query = "INSERT INTO stock_movements (branch_id, item_id, movement_type, quantity, previous_stock, new_stock, reference_type, reference_id, notes, user_id) 
                         VALUES (?, ?, 'out', ?, ?, ?, 'order', ?, 'Order sale', ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id, $item['id'], $item['quantity'], $previous_stock, $new_stock, $order_id, $_SESSION['user_id']]);
                
                // Check for low stock alerts
                checkLowStockAlert($branch_id, $item['id'], $new_stock);
            }
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// Function to check for low stock alerts
function checkLowStockAlert($branch_id, $item_id, $current_stock) {
    global $db;
    
    // Get minimum stock level
    $query = "SELECT minimum_stock FROM branch_items WHERE branch_id = ? AND item_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$branch_id, $item_id]);
    $item = $stmt->fetch();
    
    if ($item && $item['minimum_stock'] > 0) {
        $alert_type = null;
        $threshold = null;
        
        if ($current_stock <= 0) {
            $alert_type = 'out_of_stock';
            $threshold = 0;
        } elseif ($current_stock <= $item['minimum_stock']) {
            $alert_type = 'low_stock';
            $threshold = $item['minimum_stock'];
        }
        
        if ($alert_type) {
            // Check if alert already exists
            $query = "SELECT id FROM stock_alerts WHERE branch_id = ? AND item_id = ? AND alert_type = ? AND is_resolved = 0";
            $stmt = $db->prepare($query);
            $stmt->execute([$branch_id, $item_id, $alert_type]);
            
            if (!$stmt->fetch()) {
                // Create new alert
                $query = "INSERT INTO stock_alerts (branch_id, item_id, alert_type, current_stock, threshold_stock) 
                         VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id, $item_id, $alert_type, $current_stock, $threshold]);
                
                // Create notification
                createStockNotification($branch_id, $item_id, $alert_type, $current_stock, $threshold);
            }
        }
    }
}

// Function to create stock notifications
function createStockNotification($branch_id, $item_id, $alert_type, $current_stock, $threshold) {
    global $db;
    
    // Get item name
    $query = "SELECT name FROM items WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    if ($item) {
        $title = $alert_type === 'out_of_stock' ? 'Out of Stock Alert' : 'Low Stock Alert';
        $message = $alert_type === 'out_of_stock' 
            ? "Item '{$item['name']}' is out of stock (Current: {$current_stock})"
            : "Item '{$item['name']}' is running low (Current: {$current_stock}, Min: {$threshold})";
        
        $priority = $alert_type === 'out_of_stock' ? 'urgent' : 'high';
        
        $query = "INSERT INTO notifications (branch_id, notification_type, title, message, priority, related_id, related_type) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$branch_id, $alert_type, $title, $message, $priority, $item_id, 'item']);
    }
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
    
    // Get user's branch
    $branch_id = getUserBranch($_SESSION['user_id']);
    if (!$branch_id) {
        throw new Exception('User branch not found');
    }
    
    // Check stock availability
    $stockCheck = checkStockAvailability($branch_id, $input['items']);
    if (!$stockCheck['available']) {
        throw new Exception("Insufficient stock for {$stockCheck['item_name']}. Available: {$stockCheck['available_stock']}, Requested: {$stockCheck['requested']}");
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
        
        // Create order record
        $query = "INSERT INTO orders (
            order_number, user_id, customer_id, branch_id, order_type, 
            subtotal, tax_amount, total_amount, payment_method, payment_status, 
            order_status, notes, table_number, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'pending', ?, ?, NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            sanitize($input['order_number']),
            $_SESSION['user_id'],
            $customerId,
            $branch_id,
            $input['order_type'] ?? 'dine_in',
            $subtotal,
            $taxAmount,
            $totalAmount,
            $input['payment_method'],
            sanitize($input['notes'] ?? ''),
            sanitize($input['table_number'] ?? '')
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
        
        // Deduct stock from branch
        deductStock($branch_id, $input['items'], $orderId);
        
        // Commit transaction
        $db->commit();
        
        // Get the complete order for response
        $query = "SELECT o.*, u.name as user_name, u.username, c.name as customer_name, c.contact as customer_phone,
                 b.name as branch_name
                 FROM orders o 
                 LEFT JOIN users u ON o.user_id = u.id 
                 LEFT JOIN customers c ON o.customer_id = c.id 
                 LEFT JOIN branches b ON o.branch_id = b.id
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
        
        // Prepare print data
        $printData = [
            'order_number' => $order['order_number'],
            'date_time' => date('Y-m-d H:i:s'),
            'cashier' => $order['user_name'] ?? 'Admin',
            'customer' => $order['customer_name'] ?? 'Walk-in Customer',
            'branch' => $order['branch_name'] ?? 'Main Branch',
            'table_number' => $order['table_number'] ?? '',
            'items' => $orderItems,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'payment_method' => $input['payment_method']
        ];
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Order processed successfully with stock deduction',
            'order' => $order,
            'order_id' => $orderId,
            'print_data' => $printData,
            'stock_deducted' => true
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
