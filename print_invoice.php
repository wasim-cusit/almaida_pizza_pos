<?php
    /**
     * Print Invoice
     * Fast Food POS System
     */

    require_once 'config/database.php';

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    // Get order ID from URL
    $orderId = $_GET['order_id'] ?? null;

    if (!$orderId) {
        die('Order ID is required');
    }

    try {
        // Get order details with branch information
        $orderQuery = "SELECT o.*, u.name as cashier_name, c.name as customer_name, c.contact as customer_contact, c.address as customer_address, c.postcode as customer_postcode,
                             b.name as branch_name, b.address as branch_address, b.phone as branch_phone, b.email as branch_email
                    FROM orders o
                    LEFT JOIN users u ON o.user_id = u.id
                    LEFT JOIN customers c ON o.customer_id = c.id
                    LEFT JOIN branches b ON o.branch_id = b.id
                    WHERE o.id = ?";
        
        $orderStmt = $db->prepare($orderQuery);
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            die('Order not found');
        }
        
        // Get order items
        $itemsQuery = "SELECT oi.* FROM order_items oi WHERE oi.order_id = ?";
        $itemsStmt = $db->prepare($itemsQuery);
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        die('Error loading order: ' . $e->getMessage());
    }

    // Generate QR code data
    $qrData = json_encode([
        'order_number' => $order['order_number'],
        'total_amount' => $order['total_amount'],
        'date' => formatDate($order['created_at'], 'Y-m-d g:i A', false)
    ]);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($order['branch_name'] ?: 'Fast Food POS'); ?> - Invoice <?php echo htmlspecialchars($order['order_number']); ?></title>
        <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
        <link rel="stylesheet" href="assets/css/print-optimized.css">
        <style>
            /* Additional custom styles for invoice */
            @media print {
                body { 
                    margin: 0; 
                    padding: 0;
                    font-size: 14px;
                    width: 100mm;
                    min-height: 100vh;
                }
                .no-print { display: none !important; }
                .invoice-container { 
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
                .header, .order-info, .customer-info, .items-section, .totals-section, .footer {
                    padding: 8px 10px !important;
                    margin: 0 !important;
                }
                .item-row, .order-row, .total-row {
                    margin-bottom: 6px !important;
                    font-size: 13px !important;
                    line-height: 1.4 !important;
                    display: flex !important;
                    align-items: center !important;
                }
                                 .restaurant-name {
                     font-size: 20px !important;
                     margin-bottom: 8px !important;
                     font-weight: 900 !important;
                     text-shadow: none !important;
                     color: #000000 !important;
                 }
                                 .restaurant-info {
                     font-size: 20px !important;
                     line-height: 1.5 !important;
                     margin-bottom: 4px !important;
                     font-weight: normal !important;
                     color: #000000 !important;
                     font-family: 'Times New Roman', Times, serif !important;
                 }
                                 .grand-total {
                     font-size: 30px !important;
                     margin-top: 10px !important;
                     padding-top: 10px !important;
                     font-weight: 900 !important;
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
                .item-price {
                    flex: 1 !important;
                    text-align: right !important;
                    padding-left: 8px !important;
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
                     font-weight: 900 !important;
                     font-size: 11px !important;
                 }
                .order-label {
                    font-size: 11px !important;
                }
                .qr-section {
                    padding: 10px !important;
                    margin: 8px 0 !important;
                }
                .thank-you {
                    font-size: 12px !important;
                    margin-top: 8px !important;
                }
                .footer {
                    padding: 10px 12px !important;
                    font-size: 10px !important;
                    margin-top: 12px !important;
                }
                
                .footer div {
                    font-size: 10px !important;
                    font-weight: normal !important;
                    margin-bottom: 5px !important;
                    line-height: 1.4 !important;
                }
                
                .footer div strong {
                    font-size: 12px !important;
                    font-weight: bold !important;
                }
                
                .footer div div {
                    font-size: 9px !important;
                    margin-top: 8px !important;
                }
                .header {
                    padding-bottom: 10px !important;
                    margin-bottom: 10px !important;
                }
                .order-info {
                    padding-bottom: 10px !important;
                    margin-bottom: 10px !important;
                }
                .customer-info {
                    padding-bottom: 10px !important;
                    margin-bottom: 10px !important;
                }
                .items-section {
                    padding-bottom: 10px !important;
                    margin-bottom: 10px !important;
                }
                .totals-section {
                    padding-bottom: 10px !important;
                    margin-bottom: 10px !important;
                }
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Arial', 'Helvetica', sans-serif;
                background: #f5f5f5;
                padding: 10px;
                font-size: 14px;
                line-height: 1.4;
            }
            
            .invoice-container {
                width: 100mm;
                max-width: 100mm;
                margin: 0 auto;
                background: white;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                border-radius: 8px;
                overflow: hidden;
                min-height: 500px;
                font-family: 'Arial', 'Helvetica', sans-serif;
                line-height: 1.4;
            }
            
            .header {
                text-align: center;
                padding: 4px;
                border-bottom: 2px solid #000;
            }
            
                         .restaurant-name {
                 font-size: 32px;
                 font-weight: bold;
                 margin-bottom: 8px;
             }
            
                                         .restaurant-info {
                 font-size: 20px;
                 font-family: 'Times New Roman', Times, serif;
                 color: #000000;
                 line-height: 1.5;
                 font-weight: normal;
             }
            
            .order-info {
                padding: 5px;
                border-bottom: 2px solid #000;
            }
            
            .order-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 6px;
                font-size: 12px;
            }
            
            .order-label {
                font-weight: bold;
            }
            
            .customer-info {
                padding: 12px;
                border-bottom: 2px solid #000;
            }
            
            .items-section {
                padding: 3px;
            }
            
            .item-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 6px;
                padding: 4px 0;
                font-size: 12px;
                line-height: 1.4;
            }
            
            .item-name {
                flex: 3;
                word-wrap: break-word;
                text-align: left;
                padding-right: 6px;
            }
            
            .item-qty {
                text-align: center;
                flex: 1;
                padding: 0 3px;
            }
            
            .item-price {
                text-align: right;
                flex: 1;
                padding-left: 6px;
            }
            
            .item-total {
                text-align: right;
                flex: 1;
                font-weight: bold;
                padding-left: 6px;
            }
            
            .totals-section {
                padding: 2px;
                border-top: 2px solid #000;
            }
            
            .total-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 6px;
                font-size: 12px;
                line-height: 1.4;
            }
            
            .total-label {
                font-weight: bold;
                flex: 2;
                text-align: left;
            }
            
            .total-value {
                flex: 1;
                text-align: right;
                font-weight: bold;
            }
            
                         .grand-total {
                 font-size: 16px;
                 font-weight: 900;
                 padding-top: 8px;
                 margin-top: 8px;
             }
            
            .footer {
                padding: 12px;
                text-align: center;
                border-top: 2px solid #000;
                font-size: 12px;
            }
            
            .qr-section {
                text-align: center;
                padding: 10px;
            }
            
            .qr-code {
                margin: 0 auto;
            }
            
            .thank-you {
                font-size: 12px;
                font-weight: bold;
                margin-top: 6px;
            }
            
            .print-btn {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #007bff;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }
            
            .print-btn:hover {
                background: #0056b3;
            }
            
            .order-type-badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 9px;
                font-weight: bold;
                text-transform: uppercase;
            }
            
            .badge-dine-in {
                background: #000;
                color: white;
            }
            
            .badge-takeaway {
                background: #000;
                color: white;
            }
            
            .badge-delivery {
                background: #000;
                color: white;
            }
            
            /* POS-specific adjustments */
            @media screen and (max-width: 120mm) {
                .invoice-container {
                    width: 100%;
                    max-width: 100%;
                    border-radius: 0;
                }
            }
        </style>
    </head>
    <body>
        <button class="print-btn no-print" onclick="window.print()">
            🖨️ Print Invoice
        </button>
        
        <div class="invoice-container">
            <!-- Header -->
            <div class="header">
                <div class="restaurant-name">
                    <?php echo htmlspecialchars($order['branch_name'] ?: 'Fast Food POS'); ?>
                </div>
                <div class="restaurant-info">
                    <?php echo htmlspecialchars($order['branch_address'] ?: '123 Restaurant Street, City, Country'); ?><br>
                    <?php if ($order['branch_phone']): ?>
                    Phone: <?php echo htmlspecialchars($order['branch_phone']); ?><br>
                    <?php endif; ?>
                    <?php if ($order['branch_email']): ?>
                    Email: <?php echo htmlspecialchars($order['branch_email']); ?><br>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Information -->
            <div class="order-info">
                <div class="order-row">
                    <span class="order-label">Order #:</span>
                    <span><?php echo $order['order_number']; ?></span>
                </div>
                <div class="order-row">
                    <span class="order-label">Date:</span>
                    <span><?php echo formatDate($order['created_at']); ?></span>
                </div>
                <div class="order-row">
                    <span class="order-label">Cashier:</span>
                    <span><?php echo htmlspecialchars($order['cashier_name']); ?></span>
                </div>
                <div class="order-row">
                    <span class="order-label">Branch:</span>
                    <span><?php echo htmlspecialchars($order['branch_name'] ?: 'Main Branch'); ?></span>
                </div>
                <div class="order-row">
                    <span class="order-label">Type:</span>
                    <span>
                        <span class="order-type-badge badge-<?php echo $order['order_type']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $order['order_type'])); ?>
                        </span>
                        <?php if ($order['table_number']): ?>
                            (Table <?php echo $order['table_number']; ?>)
                        <?php endif; ?>
                    </span>
                </div>
                <div class="order-row">
                    <span class="order-label">Payment:</span>
                    <span><?php echo ucfirst($order['payment_method']); ?></span>
                </div>
            </div>
            
            <!-- Customer Information -->
            <?php if ($order['customer_name'] || $order['customer_contact']): ?>
            <div class="customer-info">
                <div class="order-row">
                    <span class="order-label">Customer:</span>
                    <span><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></span>
                </div>
                <?php if ($order['customer_contact']): ?>
                <div class="order-row">
                    <span class="order-label">Contact:</span>
                    <span><?php echo htmlspecialchars($order['customer_contact']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['customer_address']): ?>
                <div class="order-row">
                    <span class="order-label">Address:</span>
                    <span><?php echo htmlspecialchars($order['customer_address']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['customer_postcode']): ?>
                <div class="order-row">
                    <span class="order-label">Postcode:</span>
                    <span><?php echo htmlspecialchars($order['customer_postcode']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Items -->
            <div class="items-section">
                <div class="order-row" style="border-bottom: 1px solid #000; padding-bottom: 5px; margin-bottom: 10px;">
                    <span class="item-name"><strong>Item</strong></span>
                    <span class="item-qty"><strong>Qty</strong></span>
                    <span class="item-price"><strong>Price</strong></span>
                    <span class="item-total"><strong>Total</strong></span>
                </div>
                
                <?php foreach ($items as $item): ?>
                <div class="item-row">
                    <span class="item-name">
                        <?php echo htmlspecialchars($item['item_name']); ?>
                    </span>
                    <span class="item-qty"><?php echo $item['quantity']; ?></span>
                    <span class="item-price"><?php echo htmlspecialchars(getSetting('currency') ?: 'PKR'); ?> <?php echo number_format($item['unit_price'], 2); ?></span>
                    <span class="item-total"><?php echo htmlspecialchars(getSetting('currency') ?: 'PKR'); ?> <?php echo number_format($item['total_price'], 2); ?></span>
                </div>
                <?php if ($item['notes']): ?>
                <div class="item-row" style="font-size: 10px; color: #666; padding-left: 10px;">
                    <span class="item-name">- <?php echo htmlspecialchars($item['notes']); ?></span>
                    <span class="item-qty"></span>
                    <span class="item-price"></span>
                    <span class="item-total"></span>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
                         <!-- Totals -->
             <div class="totals-section">
                 <div class="total-row grand-total">
                     <span class="total-label">Total:</span>
                     <span class="total-value"><?php echo htmlspecialchars(getSetting('currency') ?: 'PKR'); ?> <?php echo number_format($order['subtotal'], 2); ?></span>
                 </div>
             </div>
            
            
            <!-- QR Code -->
            <!-- <div class="qr-section"> -->
                <!-- <div id="qr-code" class="qr-code"></div> -->
                <!-- <div class="thank-you"><?php echo htmlspecialchars(getSetting('receipt_footer') ?: 'Thank you for your order!'); ?></div> -->
            <!-- </div> -->
            
            <!-- Footer -->
            <div class="footer">
                <div style="font-size: 12px; color: #666; text-align: center; margin-top: 20px; padding: 10px; border-top: 1px solid #ddd;">
                    <strong><?php echo htmlspecialchars($order['branch_name'] ?: 'Fast Food POS'); ?></strong><br>
                    <?php echo htmlspecialchars($order['branch_address'] ?: ''); ?><br>
                    <?php if ($order['branch_phone']): ?>
                    Phone: <?php echo htmlspecialchars($order['branch_phone']); ?><br>
                    <?php endif; ?>
                    <?php if ($order['branch_email']): ?>
                    Email: <?php echo htmlspecialchars($order['branch_email']); ?><br>
                    <?php endif; ?>
                    <div style="margin-top: 10px; font-size: 10px;">
                        Thank you for your order! Please visit us again.
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            // Generate QR Code
            QRCode.toCanvas(document.getElementById('qr-code'), '<?php echo $qrData; ?>', {
                width: 60,
                margin: 1,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF'
                }
            }, function (error) {
                if (error) console.error(error);
            });
            
            // Auto print after 1 second
            setTimeout(() => {
                window.print();
            }, 1000);
        </script>
    </body>
    </html> 