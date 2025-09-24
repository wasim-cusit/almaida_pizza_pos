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
            $query = "SELECT bi.*, i.name as item_name, c.name as category_name 
                     FROM branch_items bi 
                     JOIN items i ON bi.item_id = i.id 
                     JOIN categories c ON i.category_id = c.id 
                     WHERE bi.branch_id = ? 
                     ORDER BY i.name";
            $stmt = $db->prepare($query);
            $stmt->execute([$branch_id]);
            $stock = $stmt->fetchAll();
            echo json_encode(['success' => true, 'stock' => $stock]);
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

// Get dashboard statistics
$query = "SELECT COUNT(*) as total_branches FROM branches WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$total_branches = $stmt->fetch()['total_branches'];

$query = "SELECT COUNT(*) as total_alerts FROM stock_alerts WHERE is_resolved = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$total_alerts = $stmt->fetch()['total_alerts'];

$query = "SELECT COUNT(*) as low_stock_items FROM branch_items bi 
          JOIN stock_alerts sa ON bi.branch_id = sa.branch_id AND bi.item_id = sa.item_id 
          WHERE sa.is_resolved = 0 AND sa.alert_type = 'low_stock'";
$stmt = $db->prepare($query);
$stmt->execute();
$low_stock_items = $stmt->fetch()['low_stock_items'];

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
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
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
        <h2 class="card-title">
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
                <input type="number" class="form-control" id="adjust-quantity" required min="1">
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
    // Global variables
    let currentBranchId = null;
    let stockData = [];

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadBranches();
        loadStockAlerts();
    });

    // Load branches for dropdown
    function loadBranches() {
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
                const select = document.getElementById('branch-select');
                select.innerHTML = '<option value="">Select a branch...</option>';
                data.branches.forEach(branch => {
                    const option = document.createElement('option');
                    option.value = branch.id;
                    option.textContent = branch.name;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading branches:', error);
        });
    }

    // Load stock for selected branch
    function loadBranchStock() {
        const branchId = document.getElementById('branch-select').value;
        if (!branchId) return;

        currentBranchId = branchId;

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
                document.getElementById('stock-table-card').style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading stock:', error);
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
                    <td><span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: ${stockLevel.bg}; color: ${stockLevel.color};">${stockLevel.text}</span></td>
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
            return { text: 'Out of Stock', bg: '#fef2f2', color: '#dc2626' };
        } else if (current <= minimum) {
            return { text: 'Low Stock', bg: '#fef2f2', color: '#dc2626' };
        } else {
            return { text: 'Good', bg: '#f0fdf4', color: '#16a34a' };
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
        
        document.getElementById('stock-modal').style.display = 'block';
    }

    // Handle stock form submission
    document.getElementById('stock-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const branchId = document.getElementById('adjust-branch-id').value;
        const itemId = document.getElementById('adjust-item-id').value;
        const adjustmentType = document.getElementById('adjustment-type').value;
        const quantity = parseInt(document.getElementById('adjust-quantity').value);
        const notes = document.getElementById('adjust-notes').value;
        const currentStock = parseInt(document.getElementById('adjust-current-stock').value);
        
        let newStock;
        if (adjustmentType === 'add') {
            newStock = currentStock + quantity;
        } else if (adjustmentType === 'remove') {
            newStock = currentStock - quantity;
        } else if (adjustmentType === 'set') {
            newStock = quantity;
        }
        
        if (newStock < 0) {
            alert('Stock cannot be negative');
            return;
        }
        
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
                alert('Stock updated successfully');
                closeModal('stock-modal');
                loadBranchStock();
                loadStockAlerts();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error updating stock:', error);
            alert('Error updating stock');
        });
    });

    // Load stock alerts
    function loadStockAlerts() {
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
            }
        })
        .catch(error => {
            console.error('Error loading alerts:', error);
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
                <div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin-bottom: 10px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h4 style="margin: 0 0 5px 0; color: #dc2626;">
                                <i class="fas fa-exclamation-triangle"></i> 
                                ${alert.item_name} - ${alert.branch_name}
                            </h4>
                            <p style="margin: 0; color: #666;">
                                ${alert.alert_type === 'out_of_stock' ? 'Out of Stock' : 'Low Stock'} 
                                (Current: ${alert.current_stock}, Threshold: ${alert.threshold_stock})
                            </p>
                            <small style="color: #999;">Alert created: ${new Date(alert.created_at).toLocaleString()}</small>
                        </div>
                        <button class="btn btn-${alertClass}" onclick="resolveAlert(${alert.id})">
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
                    alert('Alert resolved successfully');
                    loadStockAlerts();
                } else {
                    alert('Error resolving alert');
                }
            })
            .catch(error => {
                console.error('Error resolving alert:', error);
                alert('Error resolving alert');
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

<?php include 'includes/footer.php'; ?>