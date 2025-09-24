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
        case 'get_branches':
            $query = "SELECT * FROM branches WHERE is_active = 1 ORDER BY name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $branches = $stmt->fetchAll();
            echo json_encode(['success' => true, 'branches' => $branches]);
            exit();
            
        case 'get_branch_stock':
            $branch_id = (int)$_POST['branch_id'];
            try {
                // Check if branch_items table exists
                $query = "SHOW TABLES LIKE 'branch_items'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $table_exists = $stmt->fetch();
                
                if (!$table_exists) {
                    echo json_encode(['success' => false, 'message' => 'Branch items table not found. Please run database migration.']);
                    exit();
                }
                
                // Check if items table exists
                $query = "SHOW TABLES LIKE 'items'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $items_table_exists = $stmt->fetch();
                
                if (!$items_table_exists) {
                    echo json_encode(['success' => false, 'message' => 'Items table not found. Please run database migration.']);
                    exit();
                }
                
                // Try to get stock data with fallback queries
                $query = "SELECT bi.*, i.name as item_name, 
                         COALESCE(c.name, 'Uncategorized') as category_name 
                         FROM branch_items bi 
                         JOIN items i ON bi.item_id = i.id 
                         LEFT JOIN categories c ON i.category_id = c.id 
                         WHERE bi.branch_id = ? 
                         ORDER BY i.name";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id]);
                $stock = $stmt->fetchAll();
                echo json_encode(['success' => true, 'stock' => $stock]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading stock: ' . $e->getMessage()]);
            }
            exit();
            
        case 'update_stock':
            $branch_id = (int)$_POST['branch_id'];
            $item_id = (int)$_POST['item_id'];
            $new_stock = (int)$_POST['new_stock'];
            $adjustment_type = $_POST['adjustment_type'];
            $notes = sanitize($_POST['notes']);
            
            try {
                $db->beginTransaction();
                
                // Get current stock
                $query = "SELECT current_stock FROM branch_items WHERE branch_id = ? AND item_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id, $item_id]);
                $current = $stmt->fetch();
                
                if (!$current) {
                    // Create new branch item record
                    $query = "INSERT INTO branch_items (branch_id, item_id, current_stock) VALUES (?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$branch_id, $item_id, $new_stock]);
                    $previous_stock = 0;
                } else {
                    $previous_stock = $current['current_stock'];
                    
                    // Update stock
                    $query = "UPDATE branch_items SET current_stock = ? WHERE branch_id = ? AND item_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$new_stock, $branch_id, $item_id]);
                }
                
                // Record stock movement
                $movement_type = $adjustment_type === 'add' ? 'in' : 'out';
                $quantity = abs($new_stock - $previous_stock);
                
                $query = "INSERT INTO stock_movements (branch_id, item_id, movement_type, quantity, previous_stock, new_stock, reference_type, notes, user_id) 
                         VALUES (?, ?, ?, ?, ?, ?, 'adjustment', ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id, $item_id, $movement_type, $quantity, $previous_stock, $new_stock, $notes, $_SESSION['user_id']]);
                
                // Check for stock alerts
                checkStockAlerts($branch_id, $item_id, $new_stock);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Stock updated successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error updating stock: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_stock_alerts':
            try {
                // Check if stock_alerts table exists
                $query = "SHOW TABLES LIKE 'stock_alerts'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $table_exists = $stmt->fetch();
                
                if (!$table_exists) {
                    echo json_encode(['success' => true, 'alerts' => []]);
                    exit();
                }
                
                $query = "SELECT sa.*, b.name as branch_name, i.name as item_name 
                         FROM stock_alerts sa 
                         JOIN branches b ON sa.branch_id = b.id 
                         JOIN items i ON sa.item_id = i.id 
                         WHERE sa.is_resolved = 0 
                         ORDER BY sa.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $alerts = $stmt->fetchAll();
                echo json_encode(['success' => true, 'alerts' => $alerts]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading alerts: ' . $e->getMessage()]);
            }
            exit();
            
        case 'resolve_alert':
            $alert_id = (int)$_POST['alert_id'];
            $query = "UPDATE stock_alerts SET is_resolved = 1, resolved_at = NOW(), resolved_by = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$_SESSION['user_id'], $alert_id]);
            echo json_encode(['success' => true, 'message' => 'Alert resolved']);
            exit();
    }
}

// Function to check stock alerts
function checkStockAlerts($branch_id, $item_id, $current_stock) {
    global $db;
    
    $query = "SELECT minimum_stock, reorder_point FROM branch_items WHERE branch_id = ? AND item_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$branch_id, $item_id]);
    $item = $stmt->fetch();
    
    if ($item) {
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
            }
        }
    }
}

// Get dashboard statistics with error handling
try {
    $query = "SELECT COUNT(*) as total_branches FROM branches WHERE is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total_branches = $stmt->fetch()['total_branches'];
} catch (Exception $e) {
    $total_branches = 0;
}

try {
    // Check if stock_alerts table exists
    $query = "SHOW TABLES LIKE 'stock_alerts'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        $query = "SELECT COUNT(*) as total_alerts FROM stock_alerts WHERE is_resolved = 0";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $total_alerts = $stmt->fetch()['total_alerts'];
    } else {
        $total_alerts = 0;
    }
} catch (Exception $e) {
    $total_alerts = 0;
}

try {
    // Check if branch_items table exists
    $query = "SHOW TABLES LIKE 'branch_items'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $branch_items_exists = $stmt->fetch();
    
    if ($branch_items_exists) {
        $query = "SELECT COUNT(*) as low_stock_items FROM branch_items bi 
                  JOIN stock_alerts sa ON bi.branch_id = sa.branch_id AND bi.item_id = sa.item_id 
                  WHERE sa.is_resolved = 0 AND sa.alert_type = 'low_stock'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $low_stock_items = $stmt->fetch()['low_stock_items'];
    } else {
        $low_stock_items = 0;
    }
} catch (Exception $e) {
    $low_stock_items = 0;
}

$page_title = "Stock Management";
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-boxes"></i> Stock Management
    </h1>
    <p class="page-subtitle">Multi-Branch Stock Control & Monitoring</p>
</div>

<!-- Statistics -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-chart-line"></i> Stock Overview
        </h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;" class="stats-grid">
        <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px;">
            <div style="font-size: 2em; font-weight: bold;"><?php echo $total_branches; ?></div>
            <div>Active Branches</div>
        </div>
        <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border-radius: 12px;">
            <div style="font-size: 2em; font-weight: bold;"><?php echo $total_alerts; ?></div>
            <div>Stock Alerts</div>
        </div>
        <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f59e0b, #f59e0b); color: white; border-radius: 12px;">
            <div style="font-size: 2em; font-weight: bold;"><?php echo $low_stock_items; ?></div>
            <div>Low Stock Items</div>
        </div>
    </div>
</div>

<!-- Branch Selection -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-building"></i> Select Branch
        </h2>
    </div>
    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <div class="form-group" style="flex: 1; min-width: 200px;">
            <label>Choose Branch:</label>
            <select class="form-control" id="branch-select">
                <option value="">Loading branches...</option>
            </select>
        </div>
        <button class="btn btn-info" onclick="loadBranchStock()">
            <i class="fas fa-refresh"></i> Refresh
        </button>
    </div>
</div>

<!-- Stock Table -->
<div class="card" id="stock-table-card" style="display: none;">
    <div class="card-header">
        <h2 class="card-title" id="stock-table-title">
            <i class="fas fa-list"></i> Branch Stock Levels
        </h2>
    </div>
    <div id="stock-table-container">
        <p style="text-align: center; color: #666; padding: 40px;">
            Select a branch to view stock levels
        </p>
    </div>
</div>

<!-- Stock Alerts -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-exclamation-triangle"></i> Stock Alerts
        </h2>
        <button class="btn btn-warning" onclick="loadStockAlerts()">
            <i class="fas fa-refresh"></i> Refresh Alerts
        </button>
    </div>
    <div id="alerts-container">
        <div style="text-align: center; color: #666; padding: 40px;">
            <i class="fas fa-spinner fa-spin"></i> Loading alerts...
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div id="stock-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="background: white; margin: 5% auto; padding: 30px; border-radius: 15px; width: 90%; max-width: 600px; position: relative;">
        <span onclick="closeModal('stock-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
        <h3>Adjust Stock</h3>
        <form id="stock-form">
            <input type="hidden" id="adjust-branch-id">
            <input type="hidden" id="adjust-item-id">
            
            <div class="form-group">
                <label>Item Name</label>
                <input type="text" class="form-control" id="adjust-item-name" readonly>
            </div>
            
            <div class="form-group">
                <label>Current Stock</label>
                <input type="number" class="form-control" id="adjust-current-stock" readonly>
            </div>
            
            <div class="form-group">
                <label>Adjustment Type</label>
                <select class="form-control" id="adjustment-type" required>
                    <option value="add">Add Stock</option>
                    <option value="remove">Remove Stock</option>
                    <option value="set">Set Stock</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" class="form-control" id="adjust-quantity" required min="1" step="1">
                <small class="form-text text-muted">Enter the quantity for stock adjustment</small>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea class="form-control" id="adjust-notes" rows="3"></textarea>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('stock-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Stock</button>
            </div>
        </form>
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

    // Global variables
    let currentBranchId = null;
    let currentBranchName = null;
    let stockData = [];

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadBranches();
        loadStockAlerts();
        
        // Add event listener to branch select dropdown
        document.getElementById('branch-select').addEventListener('change', function() {
            const branchId = this.value;
            if (branchId) {
                const selectedOption = this.options[this.selectedIndex];
                const branchName = selectedOption.textContent;
                document.getElementById('stock-table-title').innerHTML = `<i class="fas fa-list"></i> Branch Stock Levels - <span class="branch-name">${branchName}</span>`;
            } else {
                document.getElementById('stock-table-title').innerHTML = `<i class="fas fa-list"></i> Branch Stock Levels`;
            }
        });
    });

    // Load branches for dropdown
    function loadBranches() {
        const select = document.getElementById('branch-select');
        select.innerHTML = '<option value="">Loading branches...</option>';
        
        fetch('stock_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_branches'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                select.innerHTML = '<option value="">Select a branch...</option>';
                if (data.branches.length === 0) {
                    select.innerHTML = '<option value="">No active branches found</option>';
                    showNotification('No active branches found', 'warning');
                } else {
                    data.branches.forEach(branch => {
                        const option = document.createElement('option');
                        option.value = branch.id;
                        option.textContent = branch.name;
                        select.appendChild(option);
                    });
                }
            } else {
                select.innerHTML = '<option value="">Error loading branches</option>';
                showNotification('Error loading branches', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading branches:', error);
            select.innerHTML = '<option value="">Error loading branches</option>';
            showNotification('Error loading branches. Please try again.', 'error');
        });
    }

    // Load stock for selected branch
    function loadBranchStock() {
        const branchSelect = document.getElementById('branch-select');
        const branchId = branchSelect.value;
        if (!branchId) {
            showNotification('Please select a branch first', 'warning');
            return;
        }

        // Get the selected branch name
        const selectedOption = branchSelect.options[branchSelect.selectedIndex];
        currentBranchName = selectedOption.textContent;
        currentBranchId = branchId;
        
        // Update the title with branch name
        document.getElementById('stock-table-title').innerHTML = `<i class="fas fa-list"></i> Branch Stock Levels - <span class="branch-name">${currentBranchName}</span>`;
        
        // Show loading state
        const container = document.getElementById('stock-table-container');
        container.innerHTML = '<div style="text-align: center; color: #666; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading stock data...</div>';
        document.getElementById('stock-table-card').style.display = 'block';

        fetch('stock_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_branch_stock&branch_id=${branchId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                stockData = data.stock;
                displayStockTable(data.stock);
            } else {
                container.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 40px;">Error loading stock data</p>';
                showNotification('Error loading stock data', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading stock:', error);
            container.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 40px;">Error loading stock data</p>';
            showNotification('Error loading stock data. Please try again.', 'error');
        });
    }

    // Display stock table
    function displayStockTable(stock) {
        const container = document.getElementById('stock-table-container');
        
        if (stock.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No stock data found for this branch</p>';
            return;
        }

        let html = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Min Stock</th>
                        <th>Reorder Point</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        stock.forEach(item => {
            const stockLevel = getStockLevel(item.current_stock, item.minimum_stock);
            html += `
                <tr>
                    <td>${item.item_name}</td>
                    <td>${item.category_name}</td>
                    <td>${item.current_stock}</td>
                    <td>${item.minimum_stock || 0}</td>
                    <td>${item.reorder_point || 0}</td>
                    <td><span class="stock-status ${stockLevel.class}">${stockLevel.text}</span></td>
                    <td>
                        <button class="btn btn-warning" onclick="adjustStock(${item.branch_id}, ${item.item_id}, '${item.item_name}', ${item.current_stock})">
                            <i class="fas fa-edit"></i> Adjust
                        </button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    // Get stock level status
    function getStockLevel(current, minimum) {
        if (current <= 0) {
            return { text: 'Out of Stock', class: 'out' };
        } else if (current <= minimum) {
            return { text: 'Low Stock', class: 'low' };
        } else {
            return { text: 'Good', class: 'good' };
        }
    }

    // Show stock adjustment modal
    function adjustStock(branchId, itemId, itemName, currentStock) {
        document.getElementById('adjust-branch-id').value = branchId;
        document.getElementById('adjust-item-id').value = itemId;
        document.getElementById('adjust-item-name').value = itemName;
        document.getElementById('adjust-current-stock').value = currentStock;
        document.getElementById('adjust-quantity').value = '';
        document.getElementById('adjust-notes').value = '';
        
        // Update modal title with branch name if available
        const modalTitle = document.querySelector('#stock-modal h3');
        if (currentBranchName) {
            modalTitle.textContent = `Adjust Stock - ${currentBranchName}`;
        } else {
            modalTitle.textContent = 'Adjust Stock';
        }
        
        document.getElementById('stock-modal').style.display = 'block';
    }

    // Handle stock form submission
    document.getElementById('stock-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const branchId = document.getElementById('adjust-branch-id').value;
        const itemId = document.getElementById('adjust-item-id').value;
        const adjustmentType = document.getElementById('adjustment-type').value;
        const quantityInput = document.getElementById('adjust-quantity');
        const quantity = parseInt(quantityInput.value);
        const notes = document.getElementById('adjust-notes').value;
        const currentStock = parseInt(document.getElementById('adjust-current-stock').value);
        
        // Input validation
        if (!quantity || quantity <= 0) {
            showNotification('Please enter a valid quantity', 'error');
            quantityInput.focus();
            return;
        }
        
        if (adjustmentType === 'remove' && quantity > currentStock) {
            showNotification('Cannot remove more stock than available', 'error');
            quantityInput.focus();
            return;
        }
        
        let newStock;
        if (adjustmentType === 'add') {
            newStock = currentStock + quantity;
        } else if (adjustmentType === 'remove') {
            newStock = currentStock - quantity;
        } else if (adjustmentType === 'set') {
            newStock = quantity;
        }
        
        if (newStock < 0) {
            showNotification('Stock cannot be negative', 'error');
            return;
        }
        
        // Show loading state
        const submitBtn = document.querySelector('#stock-form button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Updating...';
        submitBtn.disabled = true;
        
        fetch('stock_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_stock&branch_id=${branchId}&item_id=${itemId}&new_stock=${newStock}&adjustment_type=${adjustmentType}&notes=${encodeURIComponent(notes)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Stock updated successfully', 'success');
                closeModal('stock-modal');
                loadBranchStock();
                loadStockAlerts();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error updating stock:', error);
            showNotification('Error updating stock. Please try again.', 'error');
        })
        .finally(() => {
            // Reset button state
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    });

    // Load stock alerts
    function loadStockAlerts() {
        const container = document.getElementById('alerts-container');
        container.innerHTML = '<div style="text-align: center; color: #666; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading alerts...</div>';
        
        fetch('stock_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_stock_alerts'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayStockAlerts(data.alerts);
            } else {
                container.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 40px;">Error loading alerts</p>';
                showNotification('Error loading alerts', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading alerts:', error);
            container.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 40px;">Error loading alerts</p>';
            showNotification('Error loading alerts. Please try again.', 'error');
        });
    }

    // Display stock alerts
    function displayStockAlerts(alerts) {
        const container = document.getElementById('alerts-container');
        
        if (alerts.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No active stock alerts</p>';
            return;
        }

        let html = '';
        alerts.forEach(alert => {
            const alertClass = alert.alert_type === 'out_of_stock' ? 'danger' : 'warning';
            html += `
                <div class="alert-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div style="flex: 1; min-width: 200px;">
                            <h4>
                                <i class="fas fa-exclamation-triangle"></i> 
                                ${alert.item_name} - ${alert.branch_name}
                            </h4>
                            <p>
                                ${alert.alert_type === 'out_of_stock' ? 'Out of Stock' : 'Low Stock'} 
                                (Current: ${alert.current_stock}, Threshold: ${alert.threshold_stock})
                            </p>
                            <small>Alert created: ${new Date(alert.created_at).toLocaleString()}</small>
                        </div>
                        <button class="btn btn-${alertClass}" onclick="resolveAlert(${alert.id})" style="flex-shrink: 0;">
                            <i class="fas fa-check"></i> Resolve
                        </button>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Resolve alert
    function resolveAlert(alertId) {
        if (confirm('Are you sure you want to resolve this alert?')) {
            fetch('stock_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=resolve_alert&alert_id=${alertId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Alert resolved successfully', 'success');
                    loadStockAlerts();
                } else {
                    showNotification('Error resolving alert', 'error');
                }
            })
            .catch(error => {
                console.error('Error resolving alert:', error);
                showNotification('Error resolving alert. Please try again.', 'error');
            });
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
</script>

<style>
    /* Mobile responsiveness improvements */
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
            grid-template-columns: 1fr !important;
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
        #stock-modal > div {
            margin: 2% auto;
            width: 95%;
            padding: 20px;
        }
        
        /* Alert cards */
        .alert-card {
            margin-bottom: 10px;
            padding: 12px;
        }
        
        .alert-card h4 {
            font-size: 1em;
        }
        
        .alert-card p {
            font-size: 0.9em;
        }
        
        .alert-card small {
            font-size: 0.8em;
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
        .table td:nth-child(3),
        .table th:nth-child(4),
        .table td:nth-child(4),
        .table th:nth-child(5),
        .table td:nth-child(5) {
            display: none;
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
    
    /* Alert styling improvements */
    .alert-card {
        background: #fef2f2;
        border-left: 4px solid #ef4444;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .alert-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .alert-card h4 {
        margin: 0 0 5px 0;
        color: #dc2626;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .alert-card p {
        margin: 0;
        color: #666;
        line-height: 1.4;
    }
    
    .alert-card small {
        color: #999;
        font-size: 0.85em;
    }
    
    /* Stock status badges */
    .stock-status {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stock-status.good {
        background: #f0fdf4;
        color: #16a34a;
    }
    
    .stock-status.low {
        background: #fef2f2;
        color: #dc2626;
    }
    
    .stock-status.out {
        background: #fef2f2;
        color: #dc2626;
    }
    
    /* Dynamic title styling */
    #stock-table-title {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    #stock-table-title .branch-name {
        color: var(--primary-color);
        font-weight: 600;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
</style>

<?php include 'includes/footer.php'; ?>