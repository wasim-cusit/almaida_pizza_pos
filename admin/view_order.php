<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = "View Order";

// Get order ID from request
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    header('Location: index.php?error=Invalid order ID');
    exit();
}

// Get order details
$query = "SELECT o.*, u.name as user_name, c.name as customer_name 
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          LEFT JOIN customers c ON o.customer_id = c.id 
          WHERE o.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: index.php?error=Order not found');
    exit();
}

// Get order items
$query = "SELECT * FROM order_items WHERE order_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['order_status'];
    $query = "UPDATE orders SET order_status = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$new_status, $order_id]);
    
    header('Location: view_order.php?id=' . $order_id . '&success=Status updated');
    exit();
}

include 'includes/header.php';
?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Override main CSS for admin pages to enable scrolling */
        body {
            overflow: auto !important;
            height: auto !important;
            min-height: 100vh;
        }
        
        .admin-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .order-details {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .order-number {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .order-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-preparing { background: #d1ecf1; color: #0c5460; }
        .status-ready { background: #d4edda; color: #155724; }
        .status-completed { background: #c3e6cb; color: #155724; }
        .status-cancelled { background: #f5c6cb; color: #721c24; }
        
        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #333;
            font-size: 16px;
        }
        
        .order-items {
            margin-bottom: 20px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .items-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .order-total {
            background: #20bf55;
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
        }
        
        .btn-admin {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin: 5px;
        }
        
        .btn-primary { background: #20bf55; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .status-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>📋 Order Details</h1>
                <p>Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
            </div>
            <div>
                <a href="index.php" class="btn-admin btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        
        <div class="order-details">
            <div class="order-header">
                <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                <div class="order-status status-<?php echo $order['order_status']; ?>">
                    <?php echo ucfirst($order['order_status']); ?>
                </div>
            </div>
            
            <div class="order-info">
                <div class="info-item">
                    <div class="info-label">Order Date</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Customer</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Cashier</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['user_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Order Type</div>
                    <div class="info-value"><?php echo ucfirst(str_replace('_', ' ', $order['order_type'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Payment Method</div>
                    <div class="info-value"><?php echo ucfirst($order['payment_method']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Payment Status</div>
                    <div class="info-value"><?php echo ucfirst($order['payment_status']); ?></div>
                </div>
            </div>
            
            <?php if ($order['notes']): ?>
            <div class="info-item">
                <div class="info-label">Notes</div>
                <div class="info-value"><?php echo htmlspecialchars($order['notes']); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="order-items">
                <h3>Order Items</h3>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>PKR <?php echo number_format($item['unit_price'], 2); ?></td>
                            <td>PKR <?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="order-total">
                Total Amount: PKR <?php echo number_format($order['total_amount'], 2); ?>
            </div>
            
            <div class="status-form">
                <h3>Update Order Status</h3>
                <form method="POST">
                    <input type="hidden" name="update_status" value="1">
                    <select name="order_status" required>
                        <option value="pending" <?php echo $order['order_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="preparing" <?php echo $order['order_status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                        <option value="ready" <?php echo $order['order_status'] === 'ready' ? 'selected' : ''; ?>>Ready</option>
                        <option value="completed" <?php echo $order['order_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $order['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="btn-admin btn-primary">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </form>
            </div>
        </div>
<?php include 'includes/footer.php'; ?> 