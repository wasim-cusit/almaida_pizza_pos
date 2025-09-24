<?php
/**
 * Kitchen Display System
 * Fast Food POS System - Real-time Order Tracking
 */

require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get active orders
$query = "SELECT o.*, u.name as user_name, c.name as customer_name,
          (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          LEFT JOIN customers c ON o.customer_id = c.id 
          WHERE o.order_status IN ('pending', 'preparing', 'ready')
          ORDER BY o.created_at ASC";

$stmt = $db->prepare($query);
$stmt->execute();
$activeOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Display - Fast Food POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            padding: 20px;
            color: white;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        
        .header h1 {
            font-size: 3em;
            margin-bottom: 10px;
            color: #20bf55;
        }
        
        .header p {
            font-size: 1.2em;
            opacity: 0.8;
        }
        
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .order-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            border-color: #20bf55;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .order-number {
            font-size: 1.5em;
            font-weight: bold;
            color: #20bf55;
        }
        
        .order-time {
            font-size: 1.1em;
            opacity: 0.8;
        }
        
        .order-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        
        .status-pending {
            background: #f59e0b;
            color: white;
        }
        
        .status-preparing {
            background: #3b82f6;
            color: white;
        }
        
        .status-ready {
            background: #10b981;
            color: white;
        }
        
        .order-info {
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 1.1em;
        }
        
        .info-label {
            opacity: 0.8;
        }
        
        .order-items {
            margin-bottom: 20px;
        }
        
        .item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .item:last-child {
            border-bottom: none;
        }
        
        .item-name {
            font-weight: 500;
        }
        
        .item-quantity {
            background: #20bf55;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: bold;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 1em;
        }
        
        .btn-primary {
            background: #20bf55;
            color: white;
        }
        
        .btn-secondary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            opacity: 0.7;
        }
        
        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #20bf55;
        }
        
        .refresh-info {
            text-align: center;
            margin-top: 20px;
            opacity: 0.7;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .orders-grid {
                grid-template-columns: 1fr;
            }
            
            .order-card {
                padding: 20px;
            }
            
            .order-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-utensils"></i> Kitchen Display</h1>
        <p>Real-time order tracking for kitchen staff</p>
    </div>
    
    <?php if (empty($activeOrders)): ?>
    <div class="empty-state">
        <i class="fas fa-check-circle"></i>
        <h2>No Active Orders</h2>
        <p>All orders have been completed or are ready for pickup</p>
    </div>
    <?php else: ?>
    <div class="orders-grid">
        <?php foreach ($activeOrders as $order): ?>
        <div class="order-card">
            <div class="order-header">
                <div>
                    <div class="order-number"><?php echo htmlspecialchars($order['order_number']); ?></div>
                    <div class="order-time"><?php echo date('H:i', strtotime($order['created_at'])); ?></div>
                </div>
                <span class="order-status status-<?php echo $order['order_status']; ?>">
                    <?php echo ucfirst($order['order_status']); ?>
                </span>
            </div>
            
            <div class="order-info">
                <div class="info-row">
                    <span class="info-label">Customer:</span>
                    <span><?php echo htmlspecialchars($order['customer_name'] ?? 'Walk-in'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Cashier:</span>
                    <span><?php echo htmlspecialchars($order['user_name'] ?? 'Admin'); ?></span>
                </div>
                <?php if ($order['table_number']): ?>
                <div class="info-row">
                    <span class="info-label">Table:</span>
                    <span><?php echo htmlspecialchars($order['table_number']); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Items:</span>
                    <span><?php echo $order['item_count']; ?> items</span>
                </div>
            </div>
            
            <div class="order-items">
                <?php
                $query = "SELECT * FROM order_items WHERE order_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$order['id']]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php foreach ($items as $item): ?>
                <div class="item">
                    <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                    <span class="item-quantity">x<?php echo $item['quantity']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="order-actions">
                <?php if ($order['order_status'] === 'pending'): ?>
                <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'preparing')" class="btn btn-primary">
                    <i class="fas fa-utensils"></i> Start Preparing
                </button>
                <?php elseif ($order['order_status'] === 'preparing'): ?>
                <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'ready')" class="btn btn-warning">
                    <i class="fas fa-check-circle"></i> Mark Ready
                </button>
                <?php elseif ($order['order_status'] === 'ready'): ?>
                <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'completed')" class="btn btn-primary">
                    <i class="fas fa-check-double"></i> Mark Completed
                </button>
                <?php endif; ?>
                
                <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'cancelled')" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <div class="refresh-info">
        <p>🔄 Auto-refreshing every 30 seconds</p>
    </div>
    
    <script>
        function updateOrderStatus(orderId, status) {
            const statusNames = {
                'preparing': 'Preparing',
                'ready': 'Ready',
                'completed': 'Completed',
                'cancelled': 'Cancelled'
            };
            
            if (confirm(`Are you sure you want to mark this order as ${statusNames[status]}?`)) {
                fetch('update_order_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        order_id: orderId,
                        status: status 
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Order status updated to ${statusNames[status]} successfully!`);
                        location.reload();
                    } else {
                        alert('Error updating order status: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating order status');
                });
            }
        }
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                location.reload();
            }
        });
    </script>
</body>
</html> 