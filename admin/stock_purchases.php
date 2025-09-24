<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create_purchase':
            $supplier_id = (int)$_POST['supplier_id'];
            $purchase_date = $_POST['purchase_date'];
            $items = json_decode($_POST['items'], true);
            $notes = sanitize($_POST['notes']);
            
            try {
                $db->beginTransaction();
                
                // Generate purchase number
                $purchase_number = 'PUR' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Calculate total amount
                $total_amount = 0;
                foreach ($items as $item) {
                    $total_amount += $item['quantity'] * $item['unit_cost'];
                }
                
                // Create purchase record
                $query = "INSERT INTO stock_purchases (purchase_number, supplier_id, purchase_date, total_amount, notes, created_by) 
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$purchase_number, $supplier_id, $purchase_date, $total_amount, $notes, $_SESSION['user_id']]);
                
                $purchase_id = $db->lastInsertId();
                
                // Insert purchase items
                $query = "INSERT INTO stock_purchase_items (purchase_id, item_id, quantity, unit_cost, total_cost) 
                         VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                foreach ($items as $item) {
                    $total_cost = $item['quantity'] * $item['unit_cost'];
                    $stmt->execute([$purchase_id, $item['item_id'], $item['quantity'], $item['unit_cost'], $total_cost]);
                }
                
                // Create notification for approval
                createNotification(
                    null, // All super admins
                    'purchase_approval',
                    'New Stock Purchase Created',
                    "Purchase #{$purchase_number} for PKR " . number_format($total_amount, 2) . " requires approval.",
                    'high',
                    $purchase_id,
                    'purchase'
                );
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Purchase created successfully', 'purchase_id' => $purchase_id]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error creating purchase: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_suppliers':
            $query = "SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $suppliers = $stmt->fetchAll();
            echo json_encode(['success' => true, 'suppliers' => $suppliers]);
            exit();
            
        case 'get_items':
            $query = "SELECT i.*, c.name as category_name FROM items i 
                     JOIN categories c ON i.category_id = c.id 
                     WHERE i.is_active = 1 AND i.is_deleted = 0 
                     ORDER BY c.name, i.name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $items = $stmt->fetchAll();
            echo json_encode(['success' => true, 'items' => $items]);
            exit();
            
        case 'get_purchases':
            $query = "SELECT sp.*, s.name as supplier_name, u.name as created_by_name,
                     COUNT(spi.id) as item_count
                     FROM stock_purchases sp
                     JOIN suppliers s ON sp.supplier_id = s.id
                     JOIN users u ON sp.created_by = u.id
                     LEFT JOIN stock_purchase_items spi ON sp.id = spi.purchase_id
                     GROUP BY sp.id
                     ORDER BY sp.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $purchases = $stmt->fetchAll();
            echo json_encode(['success' => true, 'purchases' => $purchases]);
            exit();
            
        case 'approve_purchase':
            $purchase_id = (int)$_POST['purchase_id'];
            
            try {
                $db->beginTransaction();
                
                // Update purchase status
                $query = "UPDATE stock_purchases SET status = 'received' WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$purchase_id]);
                
                // Create stock entry
                $entry_number = 'ENT' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $query = "INSERT INTO stock_entries (entry_number, purchase_id, entry_date, entry_type, created_by) 
                         VALUES (?, ?, CURDATE(), 'purchase', ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$entry_number, $purchase_id, $_SESSION['user_id']]);
                
                $entry_id = $db->lastInsertId();
                
                // Get purchase items and create stock entries
                $query = "SELECT * FROM stock_purchase_items WHERE purchase_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$purchase_id]);
                $purchase_items = $stmt->fetchAll();
                
                $query = "INSERT INTO stock_entry_items (entry_id, item_id, quantity, unit_cost, total_cost) 
                         VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                foreach ($purchase_items as $item) {
                    $stmt->execute([$entry_id, $item['item_id'], $item['quantity'], $item['unit_cost'], $item['total_cost']]);
                    
                    // Update main warehouse stock
                    updateMainWarehouseStock($item['item_id'], $item['quantity'], $item['unit_cost']);
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Purchase approved and stock added to warehouse']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error approving purchase: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Function to update main warehouse stock
function updateMainWarehouseStock($item_id, $quantity, $unit_cost) {
    global $db;
    
    // Check if item exists in warehouse
    $query = "SELECT id, current_stock FROM main_warehouse_stock WHERE item_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$item_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing stock
        $new_stock = $existing['current_stock'] + $quantity;
        $query = "UPDATE main_warehouse_stock SET current_stock = ? WHERE item_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$new_stock, $item_id]);
    } else {
        // Create new stock record
        $query = "INSERT INTO main_warehouse_stock (item_id, current_stock) VALUES (?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$item_id, $quantity]);
    }
    
    // Record stock movement
    $query = "INSERT INTO stock_movements (branch_id, item_id, movement_type, quantity, previous_stock, new_stock, reference_type, notes, user_id) 
             VALUES (0, ?, 'in', ?, 0, ?, 'purchase', 'Stock purchase entry', ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$item_id, $quantity, $quantity, $_SESSION['user_id']]);
}

// Function to create notifications
function createNotification($user_id, $type, $title, $message, $priority, $related_id, $related_type) {
    global $db;
    
    $query = "INSERT INTO notifications (user_id, branch_id, notification_type, title, message, priority, related_id, related_type) 
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $type, $title, $message, $priority, $related_id, $related_type]);
}

// Get dashboard statistics
$query = "SELECT COUNT(*) as total_purchases FROM stock_purchases";
$stmt = $db->prepare($query);
$stmt->execute();
$total_purchases = $stmt->fetch()['total_purchases'];

$query = "SELECT COUNT(*) as pending_purchases FROM stock_purchases WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_purchases = $stmt->fetch()['pending_purchases'];

$query = "SELECT SUM(total_amount) as total_value FROM stock_purchases WHERE status = 'received'";
$stmt = $db->prepare($query);
$stmt->execute();
$total_value = $stmt->fetch()['total_value'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Purchases - Super Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            overflow: auto !important;
            height: auto !important;
            min-height: 100vh;
            background: #f8fafc;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .admin-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .btn-super {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #20bf55; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-info { background: #3b82f6; color: white; }
        
        .btn-super:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .purchase-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .purchase-table th,
        .purchase-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .purchase-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .purchase-table tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-received { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 30px;
            border-radius: 15px;
            width: 95%;
            max-width: 1000px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: center;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .remove-item {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
        }
        
        .total-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: right;
        }
        
        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-shopping-cart"></i> Stock Purchases</h1>
            <p>Purchase stock from suppliers and manage inventory</p>
            <div style="margin-top: 20px;">
                <a href="super_admin.php" class="btn-super btn-info">
                    <i class="fas fa-arrow-left"></i> Back to Super Admin
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #667eea;"><?php echo $total_purchases; ?></div>
                <div class="stat-label">Total Purchases</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #f59e0b;"><?php echo $pending_purchases; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #20bf55;">PKR <?php echo number_format($total_value, 2); ?></div>
                <div class="stat-label">Total Value</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #3b82f6;">0</div>
                <div class="stat-label">This Month</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> Purchase Orders</h2>
                <button class="btn-super btn-primary" onclick="showCreatePurchaseModal()">
                    <i class="fas fa-plus"></i> Create Purchase Order
                </button>
            </div>

            <div id="purchases-container">
                <p style="text-align: center; color: #666; padding: 40px;">
                    Loading purchases...
                </p>
            </div>
        </div>
    </div>

    <!-- Create Purchase Modal -->
    <div id="create-purchase-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('create-purchase-modal')">&times;</span>
            <h3>Create Purchase Order</h3>
            <form id="purchase-form">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Supplier</label>
                        <select id="supplier-select" required>
                            <option value="">Select supplier...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Purchase Date</label>
                        <input type="date" id="purchase-date" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="purchase-notes" rows="3"></textarea>
                </div>
                
                <h4>Purchase Items</h4>
                <div id="items-container">
                    <div class="item-row">
                        <select class="item-select" required>
                            <option value="">Select item...</option>
                        </select>
                        <input type="number" class="quantity-input" placeholder="Qty" min="1" required>
                        <input type="number" class="unit-cost-input" placeholder="Unit Cost" step="0.01" min="0" required>
                        <input type="number" class="total-cost-input" placeholder="Total" readonly>
                        <button type="button" class="remove-item" onclick="removeItem(this)" style="display: none;">Remove</button>
                    </div>
                </div>
                
                <button type="button" class="btn-super btn-info" onclick="addItem()" style="margin-top: 10px;">
                    <i class="fas fa-plus"></i> Add Item
                </button>
                
                <div class="total-section">
                    <div>Total Amount: <span class="total-amount" id="total-amount">PKR 0.00</span></div>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn-super btn-secondary" onclick="closeModal('create-purchase-modal')">Cancel</button>
                    <button type="submit" class="btn-super btn-primary">Create Purchase Order</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Global variables
        let suppliers = [];
        let items = [];
        let itemCounter = 0;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadSuppliers();
            loadItems();
            loadPurchases();
            
            // Set today's date as default
            document.getElementById('purchase-date').value = new Date().toISOString().split('T')[0];
        });

        // Load suppliers
        function loadSuppliers() {
            fetch('stock_purchases.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_suppliers'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    suppliers = data.suppliers;
                    const select = document.getElementById('supplier-select');
                    select.innerHTML = '<option value="">Select supplier...</option>';
                    data.suppliers.forEach(supplier => {
                        const option = document.createElement('option');
                        option.value = supplier.id;
                        option.textContent = supplier.name;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading suppliers:', error);
            });
        }

        // Load items
        function loadItems() {
            fetch('stock_purchases.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_items'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    items = data.items;
                    updateItemSelects();
                }
            })
            .catch(error => {
                console.error('Error loading items:', error);
            });
        }

        // Update all item selects
        function updateItemSelects() {
            document.querySelectorAll('.item-select').forEach(select => {
                const currentValue = select.value;
                select.innerHTML = '<option value="">Select item...</option>';
                items.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = `${item.name} (${item.category_name})`;
                    select.appendChild(option);
                });
                select.value = currentValue;
            });
        }

        // Load purchases
        function loadPurchases() {
            fetch('stock_purchases.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_purchases'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayPurchases(data.purchases);
                }
            })
            .catch(error => {
                console.error('Error loading purchases:', error);
            });
        }

        // Display purchases
        function displayPurchases(purchases) {
            const container = document.getElementById('purchases-container');
            
            if (purchases.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No purchases found</p>';
                return;
            }

            let html = `
                <table class="purchase-table">
                    <thead>
                        <tr>
                            <th>Purchase #</th>
                            <th>Supplier</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            purchases.forEach(purchase => {
                const statusClass = purchase.status === 'pending' ? 'status-pending' : 
                                   purchase.status === 'received' ? 'status-received' : 'status-cancelled';
                const statusText = purchase.status.charAt(0).toUpperCase() + purchase.status.slice(1);
                
                html += `
                    <tr>
                        <td><strong>${purchase.purchase_number}</strong></td>
                        <td>${purchase.supplier_name}</td>
                        <td>${new Date(purchase.purchase_date).toLocaleDateString()}</td>
                        <td>PKR ${parseFloat(purchase.total_amount).toFixed(2)}</td>
                        <td>${purchase.item_count}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${purchase.created_by_name}</td>
                        <td>
                            <button class="btn-super btn-info" onclick="viewPurchase(${purchase.id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                            ${purchase.status === 'pending' ? 
                                `<button class="btn-super btn-success" onclick="approvePurchase(${purchase.id})">
                                    <i class="fas fa-check"></i> Approve
                                </button>` : ''
                            }
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Show create purchase modal
        function showCreatePurchaseModal() {
            document.getElementById('create-purchase-modal').style.display = 'block';
        }

        // Add item row
        function addItem() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.innerHTML = `
                <select class="item-select" required>
                    <option value="">Select item...</option>
                </select>
                <input type="number" class="quantity-input" placeholder="Qty" min="1" required>
                <input type="number" class="unit-cost-input" placeholder="Unit Cost" step="0.01" min="0" required>
                <input type="number" class="total-cost-input" placeholder="Total" readonly>
                <button type="button" class="remove-item" onclick="removeItem(this)">Remove</button>
            `;
            container.appendChild(newRow);
            updateItemSelects();
            setupItemRowEvents(newRow);
        }

        // Remove item row
        function removeItem(button) {
            button.parentElement.remove();
            calculateTotal();
        }

        // Setup events for item row
        function setupItemRowEvents(row) {
            const quantityInput = row.querySelector('.quantity-input');
            const unitCostInput = row.querySelector('.unit-cost-input');
            const totalCostInput = row.querySelector('.total-cost-input');
            
            quantityInput.addEventListener('input', calculateRowTotal);
            unitCostInput.addEventListener('input', calculateRowTotal);
            
            function calculateRowTotal() {
                const quantity = parseFloat(quantityInput.value) || 0;
                const unitCost = parseFloat(unitCostInput.value) || 0;
                const total = quantity * unitCost;
                totalCostInput.value = total.toFixed(2);
                calculateTotal();
            }
        }

        // Calculate total amount
        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.total-cost-input').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('total-amount').textContent = `PKR ${total.toFixed(2)}`;
        }

        // Setup events for existing rows
        document.addEventListener('DOMContentLoaded', function() {
            setupItemRowEvents(document.querySelector('.item-row'));
        });

        // Handle purchase form submission
        document.getElementById('purchase-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const supplierId = document.getElementById('supplier-select').value;
            const purchaseDate = document.getElementById('purchase-date').value;
            const notes = document.getElementById('purchase-notes').value;
            
            // Collect items
            const items = [];
            document.querySelectorAll('.item-row').forEach(row => {
                const itemSelect = row.querySelector('.item-select');
                const quantity = row.querySelector('.quantity-input').value;
                const unitCost = row.querySelector('.unit-cost-input').value;
                
                if (itemSelect.value && quantity && unitCost) {
                    items.push({
                        item_id: itemSelect.value,
                        quantity: parseInt(quantity),
                        unit_cost: parseFloat(unitCost)
                    });
                }
            });
            
            if (items.length === 0) {
                alert('Please add at least one item');
                return;
            }
            
            // Submit purchase
            fetch('stock_purchases.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=create_purchase&supplier_id=${supplierId}&purchase_date=${purchaseDate}&items=${encodeURIComponent(JSON.stringify(items))}&notes=${encodeURIComponent(notes)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Purchase order created successfully');
                    closeModal('create-purchase-modal');
                    loadPurchases();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error creating purchase:', error);
                alert('Error creating purchase');
            });
        });

        // Approve purchase
        function approvePurchase(purchaseId) {
            if (confirm('Are you sure you want to approve this purchase? This will add stock to the warehouse.')) {
                fetch('stock_purchases.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=approve_purchase&purchase_id=${purchaseId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Purchase approved successfully');
                        loadPurchases();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error approving purchase:', error);
                    alert('Error approving purchase');
                });
            }
        }

        // View purchase details
        function viewPurchase(purchaseId) {
            alert('View purchase details - Coming soon!');
        }

        // Modal functions
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
