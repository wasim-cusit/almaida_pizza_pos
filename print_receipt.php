<?php
/**
 * Print Receipt Page
 * Fast Food POS System - Enhanced with QR Codes
 */

require_once 'config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get order data from POST or GET
$orderData = null;
$qrCodeURL = '';
$qrData = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderData = json_decode($_POST['order_data'], true);
    $qrCodeURL = $_POST['qr_code_url'] ?? '';
    $qrData = $_POST['qr_data'] ?? '';
} elseif (isset($_GET['order_id'])) {
    // Fetch order from database
    $orderId = $_GET['order_id'];
    
    $query = "SELECT o.*, u.name as user_name, c.name as customer_name, c.contact as customer_phone
              FROM orders o 
              LEFT JOIN users u ON o.user_id = u.id 
              LEFT JOIN customers c ON o.customer_id = c.id 
              WHERE o.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        // Get order items
        $query = "SELECT * FROM order_items WHERE order_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $orderData = [
            'order_number' => $order['order_number'],
            'date_time' => $order['created_at'],
            'cashier' => $order['user_name'] ?? 'Admin',
            'customer' => $order['customer_name'] ?? 'Walk-in Customer',
            'table_number' => $order['table_number'] ?? '',
            'items' => $orderItems,
            'subtotal' => $order['subtotal'],
            'tax_amount' => $order['tax_amount'],
            'total_amount' => $order['total_amount'],
            'payment_method' => $order['payment_method']
        ];
        
        // Calculate tax if not stored in database
        if (empty($orderData['tax_amount']) || $orderData['tax_amount'] == 0) {
            // Get tax rate from settings
            $tax_rate = getSetting('tax_rate') ?: 15; // Default to 15% if not set
            $orderData['tax_amount'] = ($orderData['subtotal'] * $tax_rate) / 100;
        } else {
            // If tax_amount exists, get the tax rate from settings for display
            $tax_rate = getSetting('tax_rate') ?: 15;
        }
        
        // Generate QR code data
        $qrData = json_encode([
            'order_number' => $order['order_number'],
            'total_amount' => $order['total_amount'],
            'items_count' => count($orderItems),
            'timestamp' => formatDate($order['created_at'], 'Y-m-d g:i A', false),
            'pos_system' => 'Fast Food POS'
        ]);
        
        $qrCodeURL = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);
    }
}

// If no order data, show error
if (!$orderData) {
    echo '<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
            <h2>Error: No order data found</h2>
            <p>Please provide valid order information.</p>
          </div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo htmlspecialchars($orderData['order_number']); ?></title>
    <link rel="stylesheet" href="assets/css/print-optimized.css">
    <style>
        /* Additional custom styles for receipt */
        @media print {
            body { 
                margin: 0; 
                padding: 0;
                font-size: 16px;
                width: 100mm;
                min-height: 100vh;
            }
            .no-print { display: none !important; }
            .receipt { 
                box-shadow: none !important;
                margin: 0 !important;
                border-radius: 0 !important;
                width: 100mm !important;
                max-width: 100mm !important;
                min-height: 500px !important;
                padding: 0 !important;
            }
            @page {
                margin: 0;
                size: 100mm auto;
                padding: 0;
            }
            .header, .order-info, .items, .totals, .qr-section, .footer {
                padding: 8px 10px !important;
                margin: 0 !important;
            }
            .item, .order-info, .total-row {
                margin-bottom: 8px !important;
                font-size: 16px !important;
                line-height: 1.5 !important;
                display: flex !important;
                align-items: center !important;
            }
            .header h1 {
                font-size: 24px !important;
                margin-bottom: 12px !important;
            }
            .header p {
                font-size: 10px !important;
                line-height: 1.5 !important;
                margin-bottom: 3px !important;
            }
            .total-row.final {
                font-size: 18px !important;
                margin-top: 10px !important;
                padding-top: 10px !important;
            }
            .item-name {
                flex: 3 !important;
                text-align: left !important;
                padding-right: 8px !important;
                font-size: 13px !important;
            }
            .item-qty {
                flex: 1 !important;
                text-align: center !important;
                padding: 0 6px !important;
                font-size: 13px !important;
            }
            .item-total {
                flex: 1 !important;
                text-align: right !important;
                font-weight: bold !important;
                padding-left: 8px !important;
                font-size: 13px !important;
            }
            .total-label {
                flex: 2 !important;
                text-align: left !important;
                font-size: 13px !important;
            }
            .total-value {
                flex: 1 !important;
                text-align: right !important;
                font-weight: bold !important;
                font-size: 13px !important;
            }
            .qr-section {
                padding: 10px !important;
                margin: 8px 0 !important;
            }
            .footer {
                padding: 10px !important;
                font-size: 12px !important;
                margin-top: 8px !important;
            }
            .header {
                padding-bottom: 10px !important;
                margin-bottom: 10px !important;
            }
            .items {
                padding-bottom: 10px !important;
                margin-bottom: 10px !important;
            }
            .totals {
                padding-bottom: 10px !important;
                margin-bottom: 10px !important;
            }
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            margin: 0;
            padding: 10px;
            background: #f5f5f5;
            color: #000;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .receipt {
            width: 100mm;
            max-width: 100mm;
            margin: 0 auto;
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            font-size: 13px;
            line-height: 1.4;
            min-height: 500px;
            font-family: 'Arial', 'Helvetica', sans-serif;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }
        
        .header h1 {
            margin: 0 0 8px 0;
            color: #000;
            font-size: 18px;
            font-weight: bold;
        }
        
        .header p {
            margin: 5px 0;
            color: #000;
            font-size: 11px;
        }
        
        .order-info {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 12px;
            color: #000;
        }
        
        .items {
            margin: 12px 0;
        }
        
        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 6px 0;
            padding: 4px 0;
            border-bottom: 1px solid #000;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .item-name {
            flex: 3;
            font-weight: 500;
            word-wrap: break-word;
            text-align: left;
            padding-right: 6px;
        }
        
        .item-qty {
            flex: 1;
            text-align: center;
            padding: 0 3px;
        }
        
        .item-total {
            flex: 1;
            text-align: right;
            font-weight: bold;
            padding-left: 6px;
        }
        
        .item-quantity {
            margin: 0 5px;
            color: #000;
            text-align: center;
            flex: 1;
        }
        
        .item-price {
            font-weight: 600;
            color: #000;
            text-align: right;
            flex: 1;
        }
        
        .totals {
            border-top: 2px solid #000;
            margin-top: 12px;
            padding-top: 12px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 6px 0;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .total-row.final {
            font-weight: bold;
            font-size: 16px;
            color: #000;
            border-top: 2px solid #000;
            padding-top: 8px;
            margin-top: 8px;
        }
        
        .total-label {
            flex: 2;
            text-align: left;
        }
        
        .total-value {
            flex: 1;
            text-align: right;
            font-weight: bold;
        }
        
        .qr-section {
            text-align: center;
            margin: 12px 0;
            padding: 10px;
            border: 2px solid #000;
            border-radius: 4px;
            background: #fff;
        }
        
        .qr-code img {
            max-width: 70px;
            height: auto;
            border-radius: 4px;
            margin: 6px 0;
        }
        
        .qr-text {
            font-size: 10px;
            color: #000;
            margin-top: 6px;
        }
        
        .footer {
            text-align: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px solid #000;
            color: #000;
            font-size: 10px;
        }
        
        .payment-info {
            background: #f0f0f0;
            border: 2px solid #000;
            border-radius: 6px;
            padding: 10px;
            margin: 10px 0;
            text-align: center;
        }
        
        .payment-method {
            font-weight: bold;
            color: #000;
            font-size: 11px;
        }
        
        .customer-info {
            background: #f0f0f0;
            border: 2px solid #000;
            border-radius: 6px;
            padding: 10px;
            margin: 10px 0;
        }
        
        .customer-name {
            font-weight: bold;
            color: #000;
            margin-bottom: 4px;
        }
        
        .table-info {
            background: #f0f0f0;
            border: 2px solid #000;
            border-radius: 6px;
            padding: 10px;
            margin: 10px 0;
            text-align: center;
        }
        
        .table-number {
            font-weight: bold;
            color: #000;
        }
        
        .print-buttons {
            text-align: center;
            margin: 20px 0;
        }
        
        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #20bf55;
            color: white;
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .special-instructions {
            background: #f0f0f0;
            border: 1px solid #000;
            border-radius: 6px;
            padding: 8px;
            margin: 4px 0;
            font-size: 10px;
            color: #000;
        }
        
        /* POS-specific adjustments */
        @media screen and (max-width: 100mm) {
            .receipt {
                width: 100%;
                max-width: 100%;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <!-- Header -->
        <div class="header">
            <h1>🍕 <?php echo htmlspecialchars(getSetting('company_name') ?: 'Fast Food POS'); ?></h1>
            <p>Delicious Food, Great Service</p>
            <p><?php echo htmlspecialchars(getSetting('company_website') ?: 'www.fastfoodpos.com'); ?></p>
        </div>
        
        <!-- Order Information -->
        <div class="order-info">
            <span>Order: <?php echo htmlspecialchars($orderData['order_number']); ?></span>
            <span>Date: <?php echo formatDate($orderData['date_time'], 'Y-m-d g:i A'); ?></span>
        </div>
        
        <div class="order-info">
            <span>Cashier: <?php echo htmlspecialchars($orderData['cashier']); ?></span>
            <span>Type: <?php echo ucfirst(str_replace('_', ' ', $orderData['payment_method'])); ?></span>
        </div>
        
        <!-- Customer Information -->
        <?php if (!empty($orderData['customer']) && $orderData['customer'] !== 'Walk-in Customer'): ?>
        <div class="customer-info">
            <div class="customer-name">Customer: <?php echo htmlspecialchars($orderData['customer']); ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Table Information -->
        <?php if (!empty($orderData['table_number'])): ?>
        <div class="table-info">
            <div class="table-number">Table: <?php echo htmlspecialchars($orderData['table_number']); ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Items -->
        <div class="items">
            <?php foreach ($orderData['items'] as $item): ?>
            <div class="item">
                <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                <div class="item-qty">x<?php echo $item['quantity']; ?></div>
                <div class="item-total">PKR <?php echo number_format($item['total_price'], 2); ?></div>
            </div>
            <?php if (!empty($item['special_instructions'])): ?>
            <div class="special-instructions">
                📝 <?php echo htmlspecialchars($item['special_instructions']); ?>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span class="total-label">Subtotal:</span>
                <span class="total-value">PKR <?php echo number_format($orderData['subtotal'], 2); ?></span>
            </div>
            <div class="total-row">
                <span class="total-label">Tax (<?php echo $tax_rate; ?>%):</span>
                <span class="total-value">PKR <?php echo number_format($orderData['tax_amount'], 2); ?></span>
            </div>
            <div class="total-row final">
                <span class="total-label">Total:</span>
                <span class="total-value">PKR <?php echo number_format($orderData['total_amount'], 2); ?></span>
            </div>
        </div>
        
        <!-- Payment Information -->
        <div class="payment-info">
            <div class="payment-method">
                💳 Payment Method: <?php echo ucfirst(str_replace('_', ' ', $orderData['payment_method'])); ?>
            </div>
        </div>
        
        <!-- QR Code Section -->
        <?php if (!empty($qrCodeURL)): ?>
        <div class="qr-section">
            <h4 style="margin: 0 0 10px 0; color: #1e293b;">📱 Scan for Order Details</h4>
            <div class="qr-code">
                <img src="<?php echo htmlspecialchars($qrCodeURL); ?>" alt="QR Code" />
            </div>
            <div class="qr-text">
                Scan to view order details online<br>
                Order: <?php echo htmlspecialchars($orderData['order_number']); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <p><?php echo htmlspecialchars(getSetting('receipt_footer') ?: 'Thank you for your order!'); ?></p>
            <p>Visit us again soon</p>
            <p>🍕 <?php echo htmlspecialchars(getSetting('company_name') ?: 'Fast Food POS System'); ?></p>
            <p><?php echo htmlspecialchars(getSetting('company_website') ?: 'www.fastfoodpos.com'); ?></p>
            <?php if (getSetting('company_phone')): ?>
            <p>📞 <?php echo htmlspecialchars(getSetting('company_phone')); ?></p>
            <?php endif; ?>
            <?php if (getSetting('company_address')): ?>
            <p>📍 <?php echo htmlspecialchars(getSetting('company_address')); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Print Buttons -->
    <div class="print-buttons no-print">
        <button class="btn btn-primary" onclick="window.print()">
            🖨️ Print Receipt
        </button>
        <a href="index.php" class="btn btn-secondary">
            🏠 Back to POS
        </a>
        <button class="btn btn-secondary" onclick="window.close()">
            ❌ Close
        </button>
    </div>
    
    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // };
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            if (e.key === 'Escape') {
                window.close();
            }
        });
    </script>
</body>
</html> 