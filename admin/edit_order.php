<?php
/**
 * Edit Order Page
 * Fast Food POS System - Admin Panel
 */

require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get order ID from URL
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$orderId) {
    header('Location: view_orders.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Update order details
        $customerName = sanitize($_POST['customer_name'] ?? '');
        $customerPhone = sanitize($_POST['customer_phone'] ?? '');
        $customerEmail = sanitize($_POST['customer_email'] ?? '');
        $tableNumber = sanitize($_POST['table_number'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'cash');
        
        // Update or create customer
        $customerId = null;
        if (!empty($customerName)) {
            $query = "INSERT INTO customers (name, contact, email, created_at) 
                      VALUES (?, ?, ?, NOW()) 
                      ON DUPLICATE KEY UPDATE 
                      name = VALUES(name), 
                      contact = VALUES(contact), 
                      email = VALUES(email)";
            $stmt = $db->prepare($query);
            $stmt->execute([$customerName, $customerPhone, $customerEmail]);
            $customerId = $db->lastInsertId();
            
            if (!$customerId) {
                // If no new ID, get existing customer ID
                $query = "SELECT id FROM customers WHERE name = ? AND contact = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$customerName, $customerPhone]);
                $customer = $stmt->fetch();
                $customerId = $customer['id'] ?? null;
            }
        }
        
        // Update order
        $query = "UPDATE orders SET 
                  customer_id = ?, 
                  table_number = ?, 
                  notes = ?, 
                  payment_method = ?,
                  updated_at = NOW()
                  WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$customerId, $tableNumber, $notes, $paymentMethod, $orderId]);
        
        // Update order items
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            // Delete existing items
            $query = "DELETE FROM order_items WHERE order_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$orderId]);
            
            // Insert updated items
            $query = "INSERT INTO order_items (
                order_id, item_id, item_name, quantity, 
                unit_price, total_price, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($query);
            
            $subtotal = 0;
            foreach ($_POST['items'] as $item) {
                if (!empty($item['name']) && $item['quantity'] > 0) {
                    $quantity = intval($item['quantity']);
                    $unitPrice = floatval($item['price']);
                    $totalPrice = $quantity * $unitPrice;
                    $subtotal += $totalPrice;
                    
                                         $stmt->execute([
                         $orderId,
                         intval($item['id']),
                         sanitize($item['name']),
                         $quantity,
                         $unitPrice,
                         $totalPrice,
                         sanitize($item['notes'] ?? '')
                     ]);
                }
            }
            
            // Update order totals
            $taxAmount = $subtotal * 0.15; // 15% tax
            $totalAmount = $subtotal + $taxAmount;
            
            $query = "UPDATE orders SET 
                      subtotal = ?, 
                      tax_amount = ?, 
                      total_amount = ?,
                      updated_at = NOW()
                      WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$subtotal, $taxAmount, $totalAmount, $orderId]);
        }
        
        $db->commit();
        $successMessage = "Order updated successfully!";
        
    } catch (Exception $e) {
        $db->rollBack();
        $errorMessage = "Error updating order: " . $e->getMessage();
    }
}

// Get order details
$query = "SELECT o.*, u.name as user_name, c.name as customer_name, c.contact as customer_phone, c.email as customer_email
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          LEFT JOIN customers c ON o.customer_id = c.id 
          WHERE o.id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: view_orders.php');
    exit();
}

// Get order items
$query = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
$stmt = $db->prepare($query);
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all available items for dropdown
$query = "SELECT i.*, c.name as category_name 
          FROM items i 
          LEFT JOIN categories c ON i.category_id = c.id 
          WHERE i.is_available = 1 
          ORDER BY c.name, i.name";
$stmt = $db->prepare($query);
$stmt->execute();
$availableItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function - sanitize is already defined in database.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Order - Fast Food POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #20bf55;
            margin-bottom: 10px;
        }
        
        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #20bf55;
        }
        
        .info-label {
            font-weight: bold;
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            color: #1e293b;
        }
        
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #20bf55;
        }
        
        .items-section {
            margin-top: 20px;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: center;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
        }
        
        .item-row.header {
            background: #20bf55;
            color: white;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .item-row.header .item-cell {
            color: white;
        }
        
        .item-cell {
            font-size: 14px;
        }
        
        .item-cell input,
        .item-cell select {
            width: 100%;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #20bf55;
            color: white;
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .totals-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .total-row.final {
            font-weight: bold;
            font-size: 18px;
            color: #20bf55;
            border-top: 2px solid #e2e8f0;
            padding-top: 10px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-edit"></i> Edit Order</h1>
            <p>Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
        </div>
        
        <?php if (isset($successMessage)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <!-- Order Information -->
            <div class="form-section">
                <h2 class="section-title"><i class="fas fa-info-circle"></i> Order Information</h2>
                <div class="order-info">
                    <div class="info-card">
                        <div class="info-label">Order Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['order_number']); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Date Created</div>
                        <div class="info-value"><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Cashier</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['user_name'] ?? 'Admin'); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span style="color: <?php echo $order['order_status'] === 'completed' ? '#10b981' : '#f59e0b'; ?>">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Information -->
            <div class="form-section">
                <h2 class="section-title"><i class="fas fa-user"></i> Customer Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="customer_name">Customer Name</label>
                        <input type="text" id="customer_name" name="customer_name" 
                               value="<?php echo htmlspecialchars($order['customer_name'] ?? ''); ?>"
                               placeholder="Walk-in Customer">
                    </div>
                    <div class="form-group">
                        <label for="customer_phone">Phone Number</label>
                        <input type="text" id="customer_phone" name="customer_phone" 
                               value="<?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?>"
                               placeholder="Phone number">
                    </div>
                    <div class="form-group">
                        <label for="customer_email">Email</label>
                        <input type="email" id="customer_email" name="customer_email" 
                               value="<?php echo htmlspecialchars($order['customer_email'] ?? ''); ?>"
                               placeholder="Email address">
                    </div>
                    <div class="form-group">
                        <label for="table_number">Table Number</label>
                        <input type="text" id="table_number" name="table_number" 
                               value="<?php echo htmlspecialchars($order['table_number'] ?? ''); ?>"
                               placeholder="Table number">
                    </div>
                </div>
            </div>
            
            <!-- Payment Information -->
            <div class="form-section">
                <h2 class="section-title"><i class="fas fa-credit-card"></i> Payment Information</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method">
                            <option value="cash" <?php echo $order['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="card" <?php echo $order['payment_method'] === 'card' ? 'selected' : ''; ?>>Card</option>
                            <option value="mobile_payment" <?php echo $order['payment_method'] === 'mobile_payment' ? 'selected' : ''; ?>>Mobile Payment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">Order Notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Special instructions or notes"><?php echo htmlspecialchars($order['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="form-section">
                <h2 class="section-title"><i class="fas fa-utensils"></i> Order Items</h2>
                <div class="items-section">
                    <div class="item-row header">
                        <div class="item-cell">Item</div>
                        <div class="item-cell">Quantity</div>
                        <div class="item-cell">Unit Price</div>
                        <div class="item-cell">Total Price</div>
                        <div class="item-cell">Special Instructions</div>
                        <div class="item-cell">Actions</div>
                    </div>
                    
                    <div id="items-container">
                        <?php foreach ($orderItems as $index => $item): ?>
                        <div class="item-row" data-index="<?php echo $index; ?>">
                            <div class="item-cell">
                                <select name="items[<?php echo $index; ?>][id]" class="item-select" onchange="updateItemDetails(this, <?php echo $index; ?>)">
                                    <option value="">Select Item</option>
                                    <?php foreach ($availableItems as $availableItem): ?>
                                    <option value="<?php echo $availableItem['id']; ?>" 
                                            data-price="<?php echo $availableItem['price']; ?>"
                                            data-name="<?php echo htmlspecialchars($availableItem['name']); ?>"
                                            <?php echo $item['item_id'] == $availableItem['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($availableItem['category_name'] . ' - ' . $availableItem['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="items[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($item['item_name']); ?>">
                            </div>
                            <div class="item-cell">
                                <input type="number" name="items[<?php echo $index; ?>][quantity]" 
                                       value="<?php echo $item['quantity']; ?>" min="1" 
                                       onchange="updateItemTotal(<?php echo $index; ?>)" class="quantity-input">
                            </div>
                            <div class="item-cell">
                                <input type="number" name="items[<?php echo $index; ?>][price]" 
                                       value="<?php echo $item['unit_price']; ?>" step="0.01" 
                                       onchange="updateItemTotal(<?php echo $index; ?>)" class="price-input">
                            </div>
                            <div class="item-cell">
                                <input type="number" name="items[<?php echo $index; ?>][total]" 
                                       value="<?php echo $item['total_price']; ?>" step="0.01" readonly class="total-input">
                            </div>
                            <div class="item-cell">
                                                                 <input type="text" name="items[<?php echo $index; ?>][notes]" 
                                        value="<?php echo htmlspecialchars($item['notes'] ?? ''); ?>"
                                        placeholder="Special instructions">
                            </div>
                            <div class="item-cell">
                                <button type="button" class="btn btn-danger btn-small" onclick="removeItem(<?php echo $index; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="btn btn-secondary" onclick="addItem()">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
                
                <!-- Totals -->
                <div class="totals-section">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span id="subtotal">PKR <?php echo number_format($order['subtotal'], 2); ?></span>
                    </div>
                    <div class="total-row">
                        <span>Tax (15%):</span>
                        <span id="tax">PKR <?php echo number_format($order['tax_amount'], 2); ?></span>
                    </div>
                    <div class="total-row final">
                        <span>Total:</span>
                        <span id="total">PKR <?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="actions">
                <a href="view_order_details.php?id=<?php echo $orderId; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Order
                </button>
            </div>
        </form>
    </div>
    
    <script>
        let itemIndex = <?php echo count($orderItems); ?>;
        const availableItems = <?php echo json_encode($availableItems); ?>;
        
        function addItem() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.setAttribute('data-index', itemIndex);
            
            newRow.innerHTML = `
                <div class="item-cell">
                    <select name="items[${itemIndex}][id]" class="item-select" onchange="updateItemDetails(this, ${itemIndex})">
                        <option value="">Select Item</option>
                        ${availableItems.map(item => `
                            <option value="${item.id}" data-price="${item.price}" data-name="${item.name}">
                                ${item.category_name} - ${item.name}
                            </option>
                        `).join('')}
                    </select>
                    <input type="hidden" name="items[${itemIndex}][name]" value="">
                </div>
                <div class="item-cell">
                    <input type="number" name="items[${itemIndex}][quantity]" value="1" min="1" 
                           onchange="updateItemTotal(${itemIndex})" class="quantity-input">
                </div>
                <div class="item-cell">
                    <input type="number" name="items[${itemIndex}][price]" value="0" step="0.01" 
                           onchange="updateItemTotal(${itemIndex})" class="price-input">
                </div>
                <div class="item-cell">
                    <input type="number" name="items[${itemIndex}][total]" value="0" step="0.01" 
                           readonly class="total-input">
                </div>
                                 <div class="item-cell">
                     <input type="text" name="items[${itemIndex}][notes]" 
                            placeholder="Special instructions">
                 </div>
                <div class="item-cell">
                    <button type="button" class="btn btn-danger btn-small" onclick="removeItem(${itemIndex})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(newRow);
            itemIndex++;
        }
        
        function removeItem(index) {
            const row = document.querySelector(`[data-index="${index}"]`);
            if (row) {
                row.remove();
                updateTotals();
            }
        }
        
        function updateItemDetails(select, index) {
            const option = select.options[select.selectedIndex];
            const row = select.closest('.item-row');
            const nameInput = row.querySelector('input[name$="[name]"]');
            const priceInput = row.querySelector('input[name$="[price]"]');
            
            if (option.value) {
                nameInput.value = option.getAttribute('data-name');
                priceInput.value = option.getAttribute('data-price');
                updateItemTotal(index);
            }
        }
        
        function updateItemTotal(index) {
            const row = document.querySelector(`[data-index="${index}"]`);
            if (!row) return;
            
            const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const total = quantity * price;
            
            row.querySelector('.total-input').value = total.toFixed(2);
            updateTotals();
        }
        
        function updateTotals() {
            let subtotal = 0;
            const totalInputs = document.querySelectorAll('.total-input');
            
            totalInputs.forEach(input => {
                subtotal += parseFloat(input.value) || 0;
            });
            
            const tax = subtotal * 0.15;
            const total = subtotal + tax;
            
            document.getElementById('subtotal').textContent = `PKR ${subtotal.toFixed(2)}`;
            document.getElementById('tax').textContent = `PKR ${tax.toFixed(2)}`;
            document.getElementById('total').textContent = `PKR ${total.toFixed(2)}`;
        }
        
        // Initialize totals on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateTotals();
        });
    </script>
</body>
</html> 