<?php
/**
 * View Order Details - Admin Panel
 * Fast Food POS System
 */

require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = "Order Details";

// Get order ID
$orderId = $_GET['id'] ?? 0;

if (!$orderId) {
    header('Location: view_orders.php');
    exit;
}

// Get order details with branch information
$query = "SELECT o.*, u.name as user_name, u.username, c.name as customer_name, c.contact as customer_phone, c.email as customer_email,
                 b.name as branch_name, b.address as branch_address, b.phone as branch_phone, b.email as branch_email
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          LEFT JOIN customers c ON o.customer_id = c.id 
          LEFT JOIN branches b ON o.branch_id = b.id
          WHERE o.id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: view_orders.php');
    exit;
}

// Calculate tax if not stored in database
if (empty($order['tax_amount']) || $order['tax_amount'] == 0) {
    // Get tax rate from settings
    $tax_rate = getSetting('tax_rate') ?: 15; // Default to 15% if not set
    $order['tax_amount'] = ($order['subtotal'] * $tax_rate) / 100;
} else {
    // If tax_amount exists, get the tax rate from settings for display
    $tax_rate = getSetting('tax_rate') ?: 15;
}

// Get order items
$query = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
$stmt = $db->prepare($query);
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate QR code data
$qrData = json_encode([
    'order_number' => $order['order_number'],
    'total_amount' => $order['total_amount'],
    'items_count' => count($orderItems),
    'timestamp' => $order['created_at'],
    'pos_system' => $order['branch_name'] ?: 'Fast Food POS',
    'branch_name' => $order['branch_name'],
    'branch_phone' => $order['branch_phone']
]);

$qrCodeURL = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrData);

include 'includes/header.php';
?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.4;
            font-size: 14px;
        }
        
        .invoice-container {
            max-width: 700px;
            margin: 15px auto;
            background: white;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 6px;
            overflow: hidden;
        }
        
        /* Header Section */
        .invoice-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .invoice-header h1 {
            font-size: 1.8em;
            font-weight: 300;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }
        
        .invoice-header .invoice-number {
            font-size: 1em;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .invoice-header .invoice-date {
            font-size: 0.9em;
            opacity: 0.8;
            margin-top: 3px;
        }
        
        /* Company Info */
        .company-info {
            background: #ecf0f1;
            padding: 15px 20px;
            border-bottom: 1px solid #bdc3c7;
        }
        
        .company-details {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .company-left h2 {
            color: #2c3e50;
            font-size: 1.4em;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .company-left p {
            color: #7f8c8d;
            margin: 2px 0;
            font-size: 0.85em;
        }
        
        .company-right {
            text-align: right;
        }
        
        .invoice-meta {
            background: #34495e;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
        }
        
        .invoice-meta h3 {
            font-size: 0.9em;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .invoice-meta p {
            font-size: 0.8em;
            opacity: 0.9;
            margin: 2px 0;
        }
        
        /* Customer & Order Info */
        .info-section {
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border-left: 3px solid #3498db;
        }
        
        .info-card h3 {
            color: #2c3e50;
            font-size: 0.95em;
            margin-bottom: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .info-card h3 i {
            margin-right: 6px;
            color: #3498db;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            padding: 3px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #7f8c8d;
            font-size: 0.8em;
        }
        
        .info-value {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.8em;
        }
        
        .status-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-preparing { background: #d1ecf1; color: #0c5460; }
        .status-ready { background: #d4edda; color: #155724; }
        .status-completed { background: #c3e6cb; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        /* Items Table */
        .items-section {
            padding: 20px;
        }
        
        .items-section h2 {
            color: #2c3e50;
            font-size: 1.1em;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }
        
        .items-table th {
            background: #34495e;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .items-table td {
            padding: 8px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 0.8em;
        }
        
        .items-table tr:hover {
            background: #f8f9fa;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .item-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .item-quantity {
            text-align: center;
            font-weight: 600;
            color: #e74c3c;
        }
        
        .item-price {
            text-align: right;
            font-weight: 500;
        }
        
        .item-total {
            text-align: right;
            font-weight: 700;
            color: #27ae60;
        }
        
        /* Totals Section */
        .totals-section {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #ecf0f1;
        }
        
        .totals-card {
            background: white;
            padding: 15px;
            border-radius: 4px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }
        
        .totals-card h3 {
            color: #2c3e50;
            font-size: 1em;
            margin-bottom: 15px;
            font-weight: 600;
            text-align: center;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px solid #ecf0f1;
            font-size: 0.85em;
        }
        
        .total-row:last-child {
            border-bottom: none;
        }
        
        .total-row.final {
            font-weight: 700;
            font-size: 1em;
            color: #27ae60;
            border-top: 2px solid #ecf0f1;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .total-label {
            font-weight: 500;
            color: #7f8c8d;
        }
        
        .total-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .total-row.final .total-value {
            color: #27ae60;
            font-size: 1.1em;
        }
        
        /* QR Code Section */
        .qr-section {
            padding: 20px;
            text-align: center;
            background: white;
            border-top: 1px solid #ecf0f1;
        }
        
        .qr-card {
            display: inline-block;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #ecf0f1;
        }
        
        .qr-card h3 {
            color: #2c3e50;
            font-size: 0.9em;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .qr-code img {
            max-width: 100px;
            height: auto;
            border-radius: 4px;
            border: 2px solid white;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }
        
        .qr-info {
            margin-top: 10px;
            font-size: 0.75em;
            color: #7f8c8d;
            line-height: 1.3;
        }
        
        /* Actions Section */
        .actions-section {
            padding: 20px;
            background: #ecf0f1;
            text-align: center;
        }
        
        .actions-section h3 {
            color: #2c3e50;
            font-size: 1em;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .status-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 0.8em;
            min-width: 80px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn-print {
            background: #27ae60;
            color: white;
        }
        
        .btn-edit {
            background: #f39c12;
            color: white;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-back {
            background: #7f8c8d;
            color: white;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .invoice-container {
                margin: 10px;
                border-radius: 0;
            }
            
            .company-details {
                flex-direction: column;
                gap: 15px;
            }
            
            .company-right {
                text-align: left;
            }
            
            .info-section {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .items-table {
                font-size: 0.75em;
            }
            
            .items-table th,
            .items-table td {
                padding: 6px 4px;
            }
            
            .status-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 150px;
            }
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
            }
            
            .invoice-container {
                box-shadow: none;
                margin: 0;
            }
            
            .actions-section {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <h1><i class="fas fa-receipt"></i> INVOICE</h1>
            <div class="invoice-number"><?php echo htmlspecialchars($order['order_number']); ?></div>
            <div class="invoice-date"><?php echo date('F d, Y', strtotime($order['created_at'])); ?></div>
        </div>
        
        <!-- Company Information -->
        <div class="company-info">
            <div class="company-details">
                <div class="company-left">
                    <h2><?php echo htmlspecialchars($order['branch_name'] ?: 'Fast Food POS System'); ?></h2>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($order['branch_address'] ?: '123 Restaurant Street, City, Country'); ?></p>
                    <?php if ($order['branch_phone']): ?>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['branch_phone']); ?></p>
                    <?php endif; ?>
                    <?php if ($order['branch_email']): ?>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($order['branch_email']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="company-right">
                    <div class="invoice-meta">
                        <h3>Invoice Details</h3>
                        <p><strong>Order #:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer & Order Information -->
        <div class="info-section">
            <div class="info-card">
                <h3><i class="fas fa-user"></i> Customer Information</h3>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer'); ?></span>
                </div>
                <?php if ($order['customer_phone']): ?>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['customer_email']): ?>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-shopping-cart"></i> Order Information</h3>
                <div class="info-row">
                    <span class="info-label">Order Type:</span>
                    <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $order['order_type'])); ?></span>
                </div>
                <?php if ($order['table_number']): ?>
                <div class="info-row">
                    <span class="info-label">Table:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['table_number']); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Cashier:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['user_name'] ?? 'Admin'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Branch:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['branch_name'] ?: 'Main Branch'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment:</span>
                    <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Order Items -->
        <div class="items-section">
            <h2><i class="fas fa-list"></i> Order Items</h2>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Unit Price</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                    <tr>
                        <td class="item-name">
                            <?php echo htmlspecialchars($item['item_name']); ?>
                            <?php if (!empty($item['notes'])): ?>
                            <br><small style="color: #e74c3c; font-style: italic;">📝 <?php echo htmlspecialchars($item['notes']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="item-quantity"><?php echo $item['quantity']; ?></td>
                        <td class="item-price">PKR <?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="item-total">PKR <?php echo number_format($item['total_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Totals -->
        <div class="totals-section">
            <div class="totals-card">
                <h3><i class="fas fa-calculator"></i> Order Summary</h3>
                <div class="total-row">
                    <span class="total-label">Subtotal:</span>
                    <span class="total-value">PKR <?php echo number_format($order['subtotal'], 2); ?></span>
                </div>
                <?php if ($order['discount_amount'] > 0): ?>
                <div class="total-row">
                    <span class="total-label">Discount:</span>
                    <span class="total-value" style="color: #e74c3c;">-PKR <?php echo number_format($order['discount_amount'], 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="total-row">
                    <span class="total-label">Tax (<?php echo $tax_rate ?? 15; ?>%):</span>
                    <span class="total-value">PKR <?php echo number_format($order['tax_amount'] ?? 0, 2); ?></span>
                </div>
                <div class="total-row final">
                    <span class="total-label">Total Amount:</span>
                    <span class="total-value">PKR <?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <!-- QR Code -->
        <div class="qr-section">
            <div class="qr-card">
                <h3><i class="fas fa-qrcode"></i> Digital Receipt</h3>
                <div class="qr-code">
                    <img src="<?php echo htmlspecialchars($qrCodeURL); ?>" alt="QR Code" />
                </div>
                <div class="qr-info">
                    Scan to view order details online<br>
                    <strong>Order: <?php echo htmlspecialchars($order['order_number']); ?></strong>
                </div>
            </div>
        </div>
        
        <!-- Order Status Update -->
        <div class="actions-section">
            <h3><i class="fas fa-tasks"></i> Update Order Status</h3>
            <div class="status-buttons">
                <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'pending')" 
                        class="btn <?php echo $order['order_status'] === 'pending' ? 'btn-primary' : 'btn-secondary'; ?>">
                    <i class="fas fa-clock"></i> Pending
                </button>
                <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'preparing')" 
                        class="btn <?php echo $order['order_status'] === 'preparing' ? 'btn-primary' : 'btn-secondary'; ?>">
                    <i class="fas fa-utensils"></i> Preparing
                </button>
                <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'ready')" 
                        class="btn <?php echo $order['order_status'] === 'ready' ? 'btn-success' : 'btn-secondary'; ?>">
                    <i class="fas fa-check-circle"></i> Ready
                </button>
                <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'completed')" 
                        class="btn <?php echo $order['order_status'] === 'completed' ? 'btn-success' : 'btn-secondary'; ?>">
                    <i class="fas fa-check-double"></i> Completed
                </button>
                <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'cancelled')" 
                        class="btn <?php echo $order['order_status'] === 'cancelled' ? 'btn-danger' : 'btn-secondary'; ?>">
                    <i class="fas fa-times"></i> Cancelled
                </button>
            </div>
            
            <div class="action-buttons">
                <a href="../print_receipt.php?order_id=<?php echo $order['id']; ?>" target="_blank" class="btn btn-print">
                    <i class="fas fa-print"></i> Print Receipt
                </a>
                <a href="edit_order.php?id=<?php echo $order['id']; ?>" class="btn btn-edit">
                    <i class="fas fa-edit"></i> Edit Order
                </a>
                <button onclick="deleteOrder(<?php echo $order['id']; ?>)" class="btn btn-delete">
                    <i class="fas fa-trash"></i> Delete Order
                </button>
                <a href="view_orders.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function deleteOrder(orderId) {
            if (confirm('Are you sure you want to delete this order? This action cannot be undone.')) {
                fetch('delete_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ order_id: orderId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Order deleted successfully');
                        window.location.href = 'view_orders.php';
                    } else {
                        alert('Error deleting order: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting order');
                });
            }
        }
        
        function updateOrderStatus(orderId, status) {
            const statusNames = {
                'pending': 'Pending',
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
                        // Reload the page to show updated status
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
<?php include 'includes/footer.php'; ?> 