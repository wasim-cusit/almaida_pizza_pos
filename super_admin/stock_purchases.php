<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_purchases':
            try {
                $statusFilter = $_POST['status'] ?? '';
                $dateFilter = $_POST['date'] ?? '';
                
                $query = "SELECT sp.*, s.name as supplier_name, 
                         COALESCE(sp.status, 'pending') as status
                         FROM stock_purchases sp 
                         LEFT JOIN suppliers s ON sp.supplier_id = s.id 
                         WHERE 1=1";
                $params = [];
                
                if ($statusFilter) {
                    $query .= " AND sp.status = ?";
                    $params[] = $statusFilter;
                }
                
                if ($dateFilter) {
                    $query .= " AND DATE(sp.purchase_date) = ?";
                    $params[] = $dateFilter;
                }
                
                $query .= " ORDER BY sp.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $purchases = $stmt->fetchAll();
                
                // Debug: Log status values from database
                foreach ($purchases as $purchase) {
                    error_log("Purchase ID: " . $purchase['id'] . ", Status: '" . $purchase['status'] . "', Type: " . gettype($purchase['status']));
                }
                
                echo json_encode(['success' => true, 'purchases' => $purchases]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading purchases: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_suppliers':
            try {
                // Check if suppliers table exists
                $query = "SHOW TABLES LIKE 'suppliers'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $table_exists = $stmt->fetch();
                
                if (!$table_exists) {
                    echo json_encode(['success' => true, 'suppliers' => []]);
                    exit();
                }
                
                // Check if is_active column exists
                $query = "SHOW COLUMNS FROM suppliers LIKE 'is_active'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $is_active_exists = $stmt->fetch();
                
                if ($is_active_exists) {
                    $query = "SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name";
                } else {
                    $query = "SELECT * FROM suppliers ORDER BY name";
                }
                
            $stmt = $db->prepare($query);
            $stmt->execute();
            $suppliers = $stmt->fetchAll();
            echo json_encode(['success' => true, 'suppliers' => $suppliers]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading suppliers: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_items':
            try {
                // Check if items table exists
                $query = "SHOW TABLES LIKE 'items'";
            $stmt = $db->prepare($query);
            $stmt->execute();
                $table_exists = $stmt->fetch();
                
                if (!$table_exists) {
                    echo json_encode(['success' => true, 'items' => []]);
            exit();
                }
                
                $query = "SELECT * FROM items ORDER BY name";
            $stmt = $db->prepare($query);
            $stmt->execute();
                $items = $stmt->fetchAll();
                echo json_encode(['success' => true, 'items' => $items]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading items: ' . $e->getMessage()]);
            }
            exit();
            
        case 'create_purchase':
            try {
                $supplier_id = (int)$_POST['supplier_id'];
                $purchase_date = $_POST['purchase_date'];
                $notes = sanitize($_POST['notes']);
                $items = json_decode($_POST['items'], true);
                
                if (empty($items)) {
                    echo json_encode(['success' => false, 'message' => 'Please add at least one item']);
                    exit();
                }
                
                $db->beginTransaction();
                
                // Generate purchase number
                $purchase_number = 'PO-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Calculate total amount
                $total_amount = 0;
                foreach ($items as $item) {
                    $total_amount += $item['quantity'] * $item['unit_cost'];
                }
                
                // Check if created_by column exists
                $query = "SHOW COLUMNS FROM stock_purchases LIKE 'created_by'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $created_by_exists = $stmt->fetch();
                
                // Create purchase order with or without created_by column
                if ($created_by_exists) {
                    $query = "INSERT INTO stock_purchases (purchase_number, supplier_id, purchase_date, total_amount, notes, status, created_by) 
                             VALUES (?, ?, ?, ?, ?, 'pending', ?)";
                $stmt = $db->prepare($query);
                    $stmt->execute([$purchase_number, $supplier_id, $purchase_date, $total_amount, $notes, $_SESSION['user_id']]);
                } else {
                    $query = "INSERT INTO stock_purchases (purchase_number, supplier_id, purchase_date, total_amount, notes, status) 
                             VALUES (?, ?, ?, ?, ?, 'pending')";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$purchase_number, $supplier_id, $purchase_date, $total_amount, $notes]);
                }
                $purchase_id = $db->lastInsertId();
                
                // Check if stock_purchase_items table exists
                $query = "SHOW TABLES LIKE 'stock_purchase_items'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $items_table_exists = $stmt->fetch();
                
                if ($items_table_exists) {
                    // Add purchase items
                    foreach ($items as $item) {
                        $query = "INSERT INTO stock_purchase_items (purchase_id, item_id, quantity, unit_cost, total_cost) 
                         VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                        $total_cost = $item['quantity'] * $item['unit_cost'];
                        $stmt->execute([$purchase_id, $item['item_id'], $item['quantity'], $item['unit_cost'], $total_cost]);
                    }
                } else {
                    // If items table doesn't exist, just log a warning but don't fail
                    error_log("Warning: stock_purchase_items table not found. Purchase created without item details.");
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Purchase order created successfully', 'purchase_id' => $purchase_id]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error creating purchase: ' . $e->getMessage()]);
            }
            exit();
            
        case 'approve_purchase':
            try {
                $purchase_id = (int)$_POST['purchase_id'];
                
                // Check if approved_by column exists
                $query = "SHOW COLUMNS FROM stock_purchases LIKE 'approved_by'";
$stmt = $db->prepare($query);
$stmt->execute();
                $approved_by_exists = $stmt->fetch();

                // Check if approved_at column exists
                $query = "SHOW COLUMNS FROM stock_purchases LIKE 'approved_at'";
$stmt = $db->prepare($query);
$stmt->execute();
                $approved_at_exists = $stmt->fetch();
                
                // Build update query based on available columns
                $update_fields = ["status = 'approved'"];
                $params = [];
                
                if ($approved_by_exists) {
                    $update_fields[] = "approved_by = ?";
                    $params[] = $_SESSION['user_id'];
                }
                
                if ($approved_at_exists) {
                    $update_fields[] = "approved_at = NOW()";
                }
                
                $params[] = $purchase_id;
                
                $query = "UPDATE stock_purchases SET " . implode(', ', $update_fields) . " WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                
                echo json_encode(['success' => true, 'message' => 'Purchase order approved successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error approving purchase: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_purchase_details':
            try {
                $purchase_id = (int)$_POST['purchase_id'];
                
                // Get purchase details
                $query = "SELECT sp.*, s.name as supplier_name, s.contact_person, s.phone, s.email,
                         COALESCE(sp.status, 'pending') as status
                         FROM stock_purchases sp 
                         LEFT JOIN suppliers s ON sp.supplier_id = s.id 
                         WHERE sp.id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$purchase_id]);
                $purchase = $stmt->fetch();
                
                if (!$purchase) {
                    echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
                    exit();
                }
                
                // Get purchase items
                $items = [];
                $query = "SHOW TABLES LIKE 'stock_purchase_items'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $items_table_exists = $stmt->fetch();
                
                if ($items_table_exists) {
                    $query = "SELECT spi.*, i.name as item_name, i.description, c.name as category_name 
                             FROM stock_purchase_items spi 
                             LEFT JOIN items i ON spi.item_id = i.id 
                             LEFT JOIN categories c ON i.category_id = c.id 
                             WHERE spi.purchase_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$purchase_id]);
                    $items = $stmt->fetchAll();
                }
                
                echo json_encode(['success' => true, 'purchase' => $purchase, 'items' => $items]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading purchase details: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics with error handling
try {
    $query = "SELECT COUNT(*) as total_purchases FROM stock_purchases";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_purchases = $stmt->fetch()['total_purchases'];
} catch (Exception $e) {
    $total_purchases = 0;
}

try {
    $query = "SELECT COUNT(*) as pending_purchases FROM stock_purchases WHERE status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pending_purchases = $stmt->fetch()['pending_purchases'];
} catch (Exception $e) {
    $pending_purchases = 0;
}

try {
    $query = "SELECT COUNT(*) as approved_purchases FROM stock_purchases WHERE status = 'approved'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $approved_purchases = $stmt->fetch()['approved_purchases'];
} catch (Exception $e) {
    $approved_purchases = 0;
}

try {
    $query = "SELECT SUM(total_amount) as total_value FROM stock_purchases WHERE status = 'approved'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_value = $stmt->fetch()['total_value'] ?? 0;
} catch (Exception $e) {
    $total_value = 0;
}

$page_title = "Stock Purchases";
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-shopping-cart"></i> Stock Purchases
    </h1>
    <p class="page-subtitle">Manage inventory purchases and supplier orders</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_purchases; ?></div>
        <div>Total Purchases</div>
            </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f59e0b, #f59e0b); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $pending_purchases; ?></div>
        <div>Pending Approval</div>
            </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #20bf55, #16a34a); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $approved_purchases; ?></div>
        <div>Approved Orders</div>
            </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #3b82f6, #3b82f6); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;">$<?php echo number_format($total_value, 2); ?></div>
        <div>Total Value</div>
            </div>
        </div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-bolt"></i> Quick Actions
        </h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <button class="btn btn-primary" onclick="showCreatePurchaseModal()">
            <i class="fas fa-plus"></i> New Purchase Order
        </button>
        <button class="btn btn-info" onclick="loadPurchases()">
            <i class="fas fa-refresh"></i> Refresh List
        </button>
        <button class="btn btn-success" onclick="exportPurchases()">
            <i class="fas fa-download"></i> Export Data
        </button>
        <button class="btn btn-warning" onclick="showReports()">
            <i class="fas fa-chart-bar"></i> View Reports
                </button>
    </div>
            </div>

<!-- Purchase Orders List -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Purchase Orders
        </h2>
        <div style="display: flex; gap: 10px;">
            <select class="form-control" style="width: auto;" id="status-filter">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="received">Received</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <input type="date" class="form-control" style="width: auto;" id="date-filter">
        </div>
    </div>
            <div id="purchases-container">
        <div style="text-align: center; color: #666; padding: 40px;">
            <i class="fas fa-spinner fa-spin"></i> Loading purchase orders...
            </div>
        </div>
    </div>

    <!-- Create Purchase Modal -->
<div id="create-purchase-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="background: white; margin: 2% auto; padding: 30px; border-radius: 15px; width: 95%; max-width: 800px; position: relative; max-height: 90vh; overflow-y: auto;">
        <span onclick="closeModal('create-purchase-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
            <h3>Create Purchase Order</h3>
        
            <form id="purchase-form">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Supplier</label>
                    <select class="form-control" id="supplier-select" required>
                            <option value="">Select supplier...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Purchase Date</label>
                    <input type="date" class="form-control" id="purchase-date" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                <textarea class="form-control" id="purchase-notes" rows="3"></textarea>
                </div>
                
            <h4>Items</h4>
                <div id="items-container">
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 10px; align-items: center; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; margin-bottom: 10px;">
                    <select class="form-control item-select" required>
                            <option value="">Select item...</option>
                        </select>
                    <input type="number" class="form-control quantity-input" placeholder="Qty" min="1" required>
                    <input type="number" class="form-control unit-cost-input" placeholder="Unit Cost" step="0.01" min="0" required>
                    <input type="number" class="form-control total-cost-input" placeholder="Total" readonly>
                    <button type="button" class="btn btn-danger" onclick="removeItem(this)" style="display: none;">Remove</button>
                    </div>
                </div>
                
            <button type="button" class="btn btn-info" onclick="addItem()" style="margin-top: 10px;">
                    <i class="fas fa-plus"></i> Add Item
                </button>
                
                <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('create-purchase-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Purchase Order</button>
                </div>
            </form>
        </div>
    </div>

<!-- Purchase Details Modal -->
<div id="purchase-details-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="background: white; margin: 2% auto; padding: 30px; border-radius: 15px; width: 95%; max-width: 1000px; position: relative; max-height: 90vh; overflow-y: auto;">
        <span onclick="closeModal('purchase-details-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
        
        <div id="purchase-details-content">
            <div style="text-align: center; color: #666; padding: 40px;">
                <i class="fas fa-spinner fa-spin"></i> Loading purchase details...
            </div>
        </div>
    </div>
</div>

    <script>
    // Professional notification system
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 10000;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        `;
        
        // Set colors based on type
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };
        
        notification.style.backgroundColor = colors[type] || colors.info;
        
        // Add icon
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        
        notification.innerHTML = `
            <i class="${icons[type] || icons.info}" style="font-size: 18px;"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.remove()" style="
                background: none;
                border: none;
                color: white;
                font-size: 18px;
                cursor: pointer;
                margin-left: auto;
                padding: 0;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
            ">&times;</button>
        `;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 5000);
    }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
        loadPurchases();
            loadSuppliers();
            loadItems();
            
            // Set today's date as default
            document.getElementById('purchase-date').value = new Date().toISOString().split('T')[0];
        
        // Setup form submission
        document.getElementById('purchase-form').addEventListener('submit', handlePurchaseSubmit);
        
        // Setup filter events
        document.getElementById('status-filter').addEventListener('change', loadPurchases);
        document.getElementById('date-filter').addEventListener('change', loadPurchases);
    });

    // Load purchases
    function loadPurchases() {
        const statusFilter = document.getElementById('status-filter').value;
        const dateFilter = document.getElementById('date-filter').value;
        
        const container = document.getElementById('purchases-container');
        container.innerHTML = '<div style="text-align: center; color: #666; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading purchases...</div>';
        
        fetch('stock_purchases.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_purchases&status=${statusFilter}&date=${dateFilter}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPurchases(data.purchases);
            } else {
                container.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 40px;">Error loading purchases</p>';
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error loading purchases:', error);
            container.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 40px;">Error loading purchases</p>';
            showNotification('Error loading purchases. Please try again.', 'error');
        });
    }
    
    // Display purchases
    function displayPurchases(purchases) {
        const container = document.getElementById('purchases-container');
        
        if (purchases.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No purchase orders found</p>';
            return;
        }
        
        let html = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Purchase #</th>
                        <th>Supplier</th>
                        <th>Date</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        purchases.forEach(purchase => {
            // Debug: Log the actual status value
            console.log('Purchase status:', purchase.status, 'Type:', typeof purchase.status);
            
            const statusClass = getStatusClass(purchase.status);
            const statusText = getStatusText(purchase.status);
            
            html += `
                <tr>
                    <td><strong>${purchase.purchase_number}</strong></td>
                    <td>${purchase.supplier_name || 'Unknown Supplier'}</td>
                    <td>${purchase.purchase_date}</td>
                    <td>$${parseFloat(purchase.total_amount).toFixed(2)}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>
                        <button class="btn btn-info" onclick="viewPurchase(${purchase.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                        ${purchase.status === 'pending' ? `
                            <button class="btn btn-success" onclick="approvePurchase(${purchase.id})">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
    }
    
    // Get status class for styling
    function getStatusClass(status) {
        const classes = {
            'pending': 'status-pending',
            'approved': 'status-approved',
            'received': 'status-received',
            'cancelled': 'status-cancelled',
            'completed': 'status-received',
            'active': 'status-approved',
            'inactive': 'status-cancelled'
        };
        return classes[status] || 'status-pending';
    }
    
    // Get status text
    function getStatusText(status) {
        const texts = {
            'pending': 'Pending',
            'approved': 'Approved',
            'received': 'Received',
            'cancelled': 'Cancelled',
            'completed': 'Completed',
            'active': 'Active',
            'inactive': 'Inactive'
        };
        
        // Handle null, undefined, or empty status
        if (!status || status === '' || status === null) {
            return 'Pending';
        }
        
        // Handle unknown status by capitalizing first letter
        if (texts[status]) {
            return texts[status];
        } else {
            return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
        }
    }

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
                    const select = document.getElementById('supplier-select');
                    select.innerHTML = '<option value="">Select supplier...</option>';
                    data.suppliers.forEach(supplier => {
                        const option = document.createElement('option');
                        option.value = supplier.id;
                        option.textContent = supplier.name;
                        select.appendChild(option);
                    });
            } else {
                showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error loading suppliers:', error);
            showNotification('Error loading suppliers. Please try again.', 'error');
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
                const selects = document.querySelectorAll('.item-select');
                selects.forEach(select => {
                    select.innerHTML = '<option value="">Select item...</option>';
                    data.items.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name;
                        select.appendChild(option);
                    });
                });
            } else {
                showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error loading items:', error);
            showNotification('Error loading items. Please try again.', 'error');
        });
    }

    // Handle purchase form submission
    function handlePurchaseSubmit(e) {
        e.preventDefault();
        
        const supplierId = document.getElementById('supplier-select').value;
        const purchaseDate = document.getElementById('purchase-date').value;
        const notes = document.getElementById('purchase-notes').value;
        
        if (!supplierId) {
            showNotification('Please select a supplier', 'error');
            return;
        }
        
        // Collect items
        const items = [];
        const itemRows = document.querySelectorAll('#items-container > div');
        
        for (let row of itemRows) {
            const itemSelect = row.querySelector('.item-select');
            const quantityInput = row.querySelector('.quantity-input');
            const unitCostInput = row.querySelector('.unit-cost-input');
            
            if (itemSelect.value && quantityInput.value && unitCostInput.value) {
                items.push({
                    item_id: itemSelect.value,
                    quantity: parseFloat(quantityInput.value),
                    unit_cost: parseFloat(unitCostInput.value)
                });
            }
        }
        
        if (items.length === 0) {
            showNotification('Please add at least one item', 'error');
            return;
        }
        
        // Show loading state
        const submitBtn = document.querySelector('#purchase-form button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Creating...';
        submitBtn.disabled = true;
        
            fetch('stock_purchases.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
            body: `action=create_purchase&supplier_id=${supplierId}&purchase_date=${purchaseDate}&notes=${encodeURIComponent(notes)}&items=${encodeURIComponent(JSON.stringify(items))}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                showNotification('Purchase order created successfully', 'success');
                closeModal('create-purchase-modal');
                loadPurchases();
                resetPurchaseForm();
            } else {
                showNotification(data.message, 'error');
                }
            })
            .catch(error => {
            console.error('Error creating purchase:', error);
            showNotification('Error creating purchase. Please try again.', 'error');
        })
        .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    }
    
    // Reset purchase form
    function resetPurchaseForm() {
        document.getElementById('purchase-form').reset();
        document.getElementById('purchase-date').value = new Date().toISOString().split('T')[0];
        document.getElementById('items-container').innerHTML = `
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 10px; align-items: center; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; margin-bottom: 10px;">
                <select class="form-control item-select" required>
                    <option value="">Select item...</option>
                </select>
                <input type="number" class="form-control quantity-input" placeholder="Qty" min="1" required>
                <input type="number" class="form-control unit-cost-input" placeholder="Unit Cost" step="0.01" min="0" required>
                <input type="number" class="form-control total-cost-input" placeholder="Total" readonly>
                <button type="button" class="btn btn-danger" onclick="removeItem(this)" style="display: none;">Remove</button>
            </div>
        `;
        loadItems();
        }

        // Show create purchase modal
        function showCreatePurchaseModal() {
        resetPurchaseForm();
            document.getElementById('create-purchase-modal').style.display = 'block';
        }

        // Add item row
        function addItem() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
        newRow.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 10px; align-items: center; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; margin-bottom: 10px;';
            newRow.innerHTML = `
            <select class="form-control item-select" required>
                    <option value="">Select item...</option>
                </select>
            <input type="number" class="form-control quantity-input" placeholder="Qty" min="1" required>
            <input type="number" class="form-control unit-cost-input" placeholder="Unit Cost" step="0.01" min="0" required>
            <input type="number" class="form-control total-cost-input" placeholder="Total" readonly>
            <button type="button" class="btn btn-danger" onclick="removeItem(this)">Remove</button>
            `;
            container.appendChild(newRow);
        loadItems(); // Reload items for new row
            setupItemRowEvents(newRow);
        }

        // Remove item row
        function removeItem(button) {
            button.parentElement.remove();
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
        }
    }

    // Modal functions
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('[id$="-modal"]');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

    // View purchase details
    function viewPurchase(purchaseId) {
        document.getElementById('purchase-details-modal').style.display = 'block';
        
        const content = document.getElementById('purchase-details-content');
        content.innerHTML = '<div style="text-align: center; color: #666; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading purchase details...</div>';
        
        fetch('stock_purchases.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_purchase_details&purchase_id=${purchaseId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPurchaseDetails(data.purchase, data.items);
            } else {
                content.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading purchase details</div>';
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error loading purchase details:', error);
            content.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading purchase details</div>';
            showNotification('Error loading purchase details. Please try again.', 'error');
        });
    }
    
    // Display purchase details
    function displayPurchaseDetails(purchase, items) {
        const content = document.getElementById('purchase-details-content');
        
        const statusClass = getStatusClass(purchase.status);
        const statusText = getStatusText(purchase.status);
        
        let html = `
            <div style="margin-bottom: 30px;">
                <h3 style="margin: 0 0 20px 0; color: #333; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-file-invoice"></i> Purchase Order Details
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div class="detail-card">
                        <h4 style="margin: 0 0 15px 0; color: #555; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                            <i class="fas fa-info-circle"></i> Order Information
                        </h4>
                        <div style="display: grid; gap: 10px;">
                            <div><strong>Purchase Number:</strong> ${purchase.purchase_number}</div>
                            <div><strong>Date:</strong> ${purchase.purchase_date}</div>
                            <div><strong>Status:</strong> <span class="status-badge ${statusClass}">${statusText}</span></div>
                            <div><strong>Total Amount:</strong> <span style="font-size: 1.2em; color: #10b981; font-weight: bold;">$${parseFloat(purchase.total_amount).toFixed(2)}</span></div>
                        </div>
                    </div>
                    
                    <div class="detail-card">
                        <h4 style="margin: 0 0 15px 0; color: #555; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                            <i class="fas fa-truck"></i> Supplier Information
                        </h4>
                        <div style="display: grid; gap: 10px;">
                            <div><strong>Supplier:</strong> ${purchase.supplier_name || 'Unknown Supplier'}</div>
                            ${purchase.contact_person ? `<div><strong>Contact:</strong> ${purchase.contact_person}</div>` : ''}
                            ${purchase.phone ? `<div><strong>Phone:</strong> ${purchase.phone}</div>` : ''}
                            ${purchase.email ? `<div><strong>Email:</strong> ${purchase.email}</div>` : ''}
                        </div>
                    </div>
                </div>
                
                ${purchase.notes ? `
                    <div class="detail-card" style="margin-bottom: 20px;">
                        <h4 style="margin: 0 0 15px 0; color: #555; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                            <i class="fas fa-sticky-note"></i> Notes
                        </h4>
                        <p style="margin: 0; color: #666; line-height: 1.6;">${purchase.notes}</p>
                    </div>
                ` : ''}
                
                <div class="detail-card">
                    <h4 style="margin: 0 0 15px 0; color: #555; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                        <i class="fas fa-list"></i> Items (${items.length})
                    </h4>
        `;
        
        if (items.length > 0) {
            html += `
                <div style="overflow-x: auto;">
                    <table class="table" style="margin: 0;">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            let grandTotal = 0;
            items.forEach(item => {
                const totalCost = parseFloat(item.quantity) * parseFloat(item.unit_cost);
                grandTotal += totalCost;
                
                html += `
                    <tr>
                        <td>
                            <div><strong>${item.item_name || 'Unknown Item'}</strong></div>
                            ${item.description ? `<div style="font-size: 0.9em; color: #666;">${item.description}</div>` : ''}
                        </td>
                        <td>${item.category_name || 'Uncategorized'}</td>
                        <td>${item.quantity}</td>
                        <td>$${parseFloat(item.unit_cost).toFixed(2)}</td>
                        <td><strong>$${totalCost.toFixed(2)}</strong></td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <td colspan="4" style="text-align: right;">Grand Total:</td>
                                <td style="font-size: 1.2em; color: #10b981;">$${grandTotal.toFixed(2)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            `;
        } else {
            html += '<p style="color: #666; text-align: center; padding: 20px;">No items found for this purchase order.</p>';
        }
        
        html += `
                </div>
            </div>
        `;
        
        content.innerHTML = html;
    }

    // Approve purchase
    function approvePurchase(purchaseId) {
        if (confirm('Are you sure you want to approve this purchase order?')) {
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
                    showNotification('Purchase order approved successfully', 'success');
                    loadPurchases();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error approving purchase:', error);
                showNotification('Error approving purchase. Please try again.', 'error');
            });
        }
    }

    // Export purchases
    function exportPurchases() {
        const statusFilter = document.getElementById('status-filter').value;
        const dateFilter = document.getElementById('date-filter').value;
        
                fetch('stock_purchases.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
            body: `action=get_purchases&status=${statusFilter}&date=${dateFilter}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                exportToCSV(data.purchases, 'purchases');
                showNotification('Purchase data exported successfully', 'success');
                    } else {
                showNotification('Error exporting data', 'error');
                    }
                })
                .catch(error => {
            console.error('Error exporting purchases:', error);
            showNotification('Error exporting purchases. Please try again.', 'error');
        });
    }
    
    // Export to CSV
    function exportToCSV(data, filename) {
        if (data.length === 0) {
            showNotification('No data to export', 'warning');
            return;
        }
        
        const headers = ['Purchase Number', 'Supplier', 'Date', 'Total Amount', 'Status'];
        const csvContent = [
            headers.join(','),
            ...data.map(purchase => [
                purchase.purchase_number,
                purchase.supplier_name || 'Unknown',
                purchase.purchase_date,
                purchase.total_amount,
                purchase.status
            ].join(','))
        ].join('\n');
        
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${filename}_${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
    }

    // Show reports
    function showReports() {
        showNotification('Reports functionality - Coming soon!', 'info');
    }
</script>

<style>
    /* Status badges */
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-approved {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-received {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }
    
    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .card {
            margin-bottom: 15px;
        }
        
        .card-header {
            padding: 15px;
        }
        
        .card-title {
            font-size: 1.1em;
        }
        
        .btn {
            padding: 8px 12px;
            font-size: 0.9em;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-control {
            padding: 8px 12px;
            font-size: 0.9em;
        }
        
        .table {
            font-size: 0.85em;
        }
        
        .table th,
        .table td {
            padding: 8px 6px;
        }
        
        /* Statistics cards */
        .stats-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 10px !important;
        }
        
        .stats-grid > div {
            padding: 15px !important;
            text-align: center;
        }
        
        .stats-grid > div > div:first-child {
            font-size: 1.5em !important;
        }
        
        /* Modal improvements */
        #create-purchase-modal > div {
            margin: 2% auto;
            width: 95%;
            padding: 20px;
        }
        
        /* Item rows */
        #items-container > div {
            grid-template-columns: 1fr !important;
            gap: 10px !important;
        }
        
        #items-container > div > * {
            width: 100% !important;
        }
    }
    
    @media (max-width: 480px) {
        .page-header {
            padding: 15px;
        }
        
        .page-title {
            font-size: 1.5em;
        }
        
        .page-subtitle {
            font-size: 0.9em;
        }
        
        .btn {
            padding: 6px 10px;
            font-size: 0.8em;
        }
        
        .table {
            font-size: 0.8em;
        }
        
        .table th,
        .table td {
            padding: 6px 4px;
        }
        
        /* Hide less important columns on very small screens */
        .table th:nth-child(3),
        .table td:nth-child(3) {
            display: none;
        }
        
        .stats-grid {
            grid-template-columns: 1fr !important;
        }
    }
    
    /* Loading states */
    .loading {
        opacity: 0.6;
        pointer-events: none;
    }
    
    .loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin: -10px 0 0 -10px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Form improvements */
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .btn:focus {
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    /* Item row styling */
    #items-container > div {
        transition: all 0.3s ease;
    }
    
    #items-container > div:hover {
        background: #f8f9fa;
        border-color: var(--primary-color);
    }
    
    /* Statistics grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    /* Detail card styling */
    .detail-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 15px;
    }
    
    .detail-card h4 {
        color: #495057;
        font-size: 1.1em;
        margin: 0 0 15px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .detail-card h4 i {
        color: #6c757d;
    }
</style>

<?php include 'includes/footer.php'; ?>