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
        case 'get_warehouse_stock':
            $query = "SELECT mws.*, i.name as item_name, c.name as category_name 
                     FROM main_warehouse_stock mws
                     JOIN items i ON mws.item_id = i.id
                     JOIN categories c ON i.category_id = c.id
                     ORDER BY i.name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stock = $stmt->fetchAll();
            echo json_encode(['success' => true, 'stock' => $stock]);
            exit();
            
        case 'update_warehouse_stock':
            $item_id = (int)$_POST['item_id'];
            $new_stock = (int)$_POST['new_stock'];
            $adjustment_type = $_POST['adjustment_type'];
            $notes = sanitize($_POST['notes']);
            
            try {
                $db->beginTransaction();
                
                // Get current stock
                $query = "SELECT current_stock FROM main_warehouse_stock WHERE item_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$item_id]);
                $current = $stmt->fetch();
                
                if (!$current) {
                    // Create new warehouse stock record
                    $query = "INSERT INTO main_warehouse_stock (item_id, current_stock) VALUES (?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$item_id, $new_stock]);
                    $previous_stock = 0;
                } else {
                    $previous_stock = $current['current_stock'];
                    
                    // Update stock
                    $query = "UPDATE main_warehouse_stock SET current_stock = ? WHERE item_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$new_stock, $item_id]);
                }
                
                // Record warehouse movement
                $movement_type = $adjustment_type === 'add' ? 'in' : 'out';
                $quantity = abs($new_stock - $previous_stock);
                
                $query = "INSERT INTO warehouse_movements (item_id, movement_type, quantity, previous_stock, new_stock, reference_type, notes, user_id) 
                         VALUES (?, ?, ?, ?, ?, 'adjustment', ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$item_id, $movement_type, $quantity, $previous_stock, $new_stock, $notes, $_SESSION['user_id']]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Warehouse stock updated successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error updating warehouse stock: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_categories':
            $query = "SELECT id, name FROM categories ORDER BY name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $categories = $stmt->fetchAll();
            echo json_encode(['success' => true, 'categories' => $categories]);
            exit();
            
        case 'get_warehouse_movements':
            $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : null;
            
            $query = "SELECT wm.*, i.name as item_name, u.name as user_name 
                     FROM warehouse_movements wm
                     JOIN items i ON wm.item_id = i.id
                     LEFT JOIN users u ON wm.user_id = u.id";
            
            $params = [];
            if ($item_id) {
                $query .= " WHERE wm.item_id = ?";
                $params[] = $item_id;
            }
            
            $query .= " ORDER BY wm.created_at DESC LIMIT 50";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $movements = $stmt->fetchAll();
            echo json_encode(['success' => true, 'movements' => $movements]);
            exit();
    }
}

// Get statistics
$query = "SELECT COUNT(*) as total_items FROM main_warehouse_stock";
$stmt = $db->prepare($query);
$stmt->execute();
$total_items = $stmt->fetch()['total_items'];

$query = "SELECT COUNT(*) as low_stock_items FROM main_warehouse_stock WHERE current_stock <= minimum_stock AND current_stock > 0";
$stmt = $db->prepare($query);
$stmt->execute();
$low_stock_items = $stmt->fetch()['low_stock_items'];

$query = "SELECT COUNT(*) as out_of_stock_items FROM main_warehouse_stock WHERE current_stock = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$out_of_stock_items = $stmt->fetch()['out_of_stock_items'];

$query = "SELECT SUM(current_stock) as total_value FROM main_warehouse_stock";
$stmt = $db->prepare($query);
$stmt->execute();
$total_value = $stmt->fetch()['total_value'] ?? 0;

$page_title = "Warehouse Stock";
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-warehouse"></i> Warehouse Stock
    </h1>
    <p class="page-subtitle">Main warehouse inventory management</p>
</div>

<!-- Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_items; ?></div>
        <div>Total Items</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f59e0b, #f59e0b); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $low_stock_items; ?></div>
        <div>Low Stock Items</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $out_of_stock_items; ?></div>
        <div>Out of Stock</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #20bf55, #16a34a); color: white; border-radius: 12px;">
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
        <button class="btn btn-primary" onclick="showAdjustStockModal()">
            <i class="fas fa-plus"></i> Adjust Stock
        </button>
        <button class="btn btn-info" onclick="loadWarehouseStock()">
            <i class="fas fa-refresh"></i> Refresh List
        </button>
        <button class="btn btn-success" onclick="exportStock()">
            <i class="fas fa-download"></i> Export Data
        </button>
        <button class="btn btn-warning" onclick="showReports()">
            <i class="fas fa-chart-bar"></i> View Reports
        </button>
    </div>
</div>

<!-- Warehouse Stock List -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Warehouse Stock
        </h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <select class="form-control" style="width: auto;" id="category-filter">
                <option value="">All Categories</option>
            </select>
            <select class="form-control" style="width: auto;" id="status-filter">
                <option value="">All Status</option>
                <option value="good">Good Stock</option>
                <option value="low">Low Stock</option>
                <option value="out">Out of Stock</option>
            </select>
            <button class="btn btn-secondary" onclick="clearFilters()" style="padding: 8px 12px; font-size: 12px;">
                <i class="fas fa-times"></i> Clear Filters
            </button>
        </div>
    </div>
    <div id="warehouse-stock-container">
        <div style="text-align: center; color: #666; padding: 40px;">
            <i class="fas fa-spinner fa-spin"></i> Loading warehouse stock...
        </div>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div id="adjust-stock-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="background: white; margin: 2% auto; padding: 30px; border-radius: 15px; width: 95%; max-width: 600px; position: relative; max-height: 90vh; overflow-y: auto;">
        <span onclick="closeModal('adjust-stock-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
        <h3>Adjust Warehouse Stock</h3>
        
        <form id="adjust-stock-form">
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
                <button type="button" class="btn btn-secondary" onclick="closeModal('adjust-stock-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Stock</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Global variables
    let warehouseStock = [];

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadWarehouseStock();
        loadCategories();
        
        // Add event listeners for filters
        document.getElementById('category-filter').addEventListener('change', filterWarehouseStock);
        document.getElementById('status-filter').addEventListener('change', filterWarehouseStock);
    });

    // Load warehouse stock
    function loadWarehouseStock() {
        fetch('warehouse_stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_warehouse_stock'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                warehouseStock = data.stock;
                filterWarehouseStock();
            }
        })
        .catch(error => {
            console.error('Error loading warehouse stock:', error);
            const container = document.getElementById('warehouse-stock-container');
            container.innerHTML = `
                <div style="text-align: center; color: #dc3545; padding: 40px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3em; margin-bottom: 20px; opacity: 0.7;"></i>
                    <h3 style="margin-bottom: 10px;">Error Loading Warehouse Stock</h3>
                    <p style="margin-bottom: 20px; color: #666;">Failed to load warehouse stock. Please try again.</p>
                    <button class="btn btn-primary" onclick="loadWarehouseStock()">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                </div>
            `;
            showNotification('Failed to load warehouse stock', 'error');
        });
    }

    // Filter warehouse stock
    function filterWarehouseStock() {
        const categoryFilter = document.getElementById('category-filter').value;
        const statusFilter = document.getElementById('status-filter').value;
        
        let filteredStock = warehouseStock.filter(item => {
            const matchesCategory = categoryFilter === '' || item.category_id == categoryFilter;
            const matchesStatus = statusFilter === '' || getStockLevelFilter(item.current_stock, item.minimum_stock) === statusFilter;
            return matchesCategory && matchesStatus;
        });
        
        displayWarehouseStock(filteredStock);
    }

    // Get stock level for filtering
    function getStockLevelFilter(current, minimum) {
        if (current <= 0) {
            return 'out';
        } else if (current <= minimum) {
            return 'low';
        } else {
            return 'good';
        }
    }

    // Clear all filters
    function clearFilters() {
        document.getElementById('category-filter').value = '';
        document.getElementById('status-filter').value = '';
        filterWarehouseStock();
    }

    // Display warehouse stock
    function displayWarehouseStock(stock) {
        const container = document.getElementById('warehouse-stock-container');
        
        if (stock.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No stock found in warehouse</p>';
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
                        <th>Unit Cost</th>
                        <th>Total Value</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        stock.forEach(item => {
            const stockLevel = getStockLevel(item.current_stock, item.minimum_stock);
            const totalValue = item.current_stock * (item.unit_cost || 0);
            
            html += `
                <tr>
                    <td><strong>${item.item_name}</strong></td>
                    <td>${item.category_name}</td>
                    <td>${item.current_stock}</td>
                    <td>${item.minimum_stock || 0}</td>
                    <td>$${(item.unit_cost || 0).toFixed(2)}</td>
                    <td>$${totalValue.toFixed(2)}</td>
                    <td><span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: ${stockLevel.bg}; color: ${stockLevel.color};">${stockLevel.text}</span></td>
                    <td>
                        <button class="btn btn-warning" onclick="adjustStock(${item.item_id}, '${item.item_name}', ${item.current_stock})">
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
            return { text: 'Out of Stock', bg: '#fee2e2', color: '#991b1b' };
        } else if (current <= minimum) {
            return { text: 'Low Stock', bg: '#fef3c7', color: '#92400e' };
        } else {
            return { text: 'Good', bg: '#d1fae5', color: '#065f46' };
        }
    }

    // Load categories
    function loadCategories() {
        fetch('warehouse_stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_categories'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('category-filter');
                let html = '<option value="">All Categories</option>';
                data.categories.forEach(category => {
                    html += `<option value="${category.id}">${category.name}</option>`;
                });
                select.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error loading categories:', error);
            showNotification('Failed to load categories', 'error');
        });
    }

    // Show adjust stock modal
    function showAdjustStockModal() {
        document.getElementById('adjust-stock-modal').style.display = 'block';
    }

    // Adjust stock
    function adjustStock(itemId, itemName, currentStock) {
        document.getElementById('adjust-item-id').value = itemId;
        document.getElementById('adjust-item-name').value = itemName;
        document.getElementById('adjust-current-stock').value = currentStock;
        document.getElementById('adjust-quantity').value = '';
        document.getElementById('adjust-notes').value = '';
        
        document.getElementById('adjust-stock-modal').style.display = 'block';
    }

    // Handle form submission
    document.getElementById('adjust-stock-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
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
            showNotification('Stock cannot be negative', 'error');
            return;
        }
        
        fetch('warehouse_stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_warehouse_stock&item_id=${itemId}&new_stock=${newStock}&adjustment_type=${adjustmentType}&notes=${encodeURIComponent(notes)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Warehouse stock updated successfully', 'success');
                closeModal('adjust-stock-modal');
                loadWarehouseStock();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error updating warehouse stock:', error);
            showNotification('Error updating warehouse stock', 'error');
        });
    });

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

    // Export warehouse stock to CSV
    function exportStock() {
        if (warehouseStock.length === 0) {
            showNotification('No stock data to export', 'warning');
            return;
        }
        
        const headers = ['Item Name', 'Category', 'Current Stock', 'Minimum Stock', 'Unit Cost', 'Total Value', 'Status'];
        let csvContent = headers.join(',') + '\n';
        
        warehouseStock.forEach(item => {
            const stockLevel = getStockLevel(item.current_stock, item.minimum_stock);
            const totalValue = item.current_stock * (item.unit_cost || 0);
            
            const row = [
                `"${item.item_name}"`,
                `"${item.category_name}"`,
                item.current_stock,
                item.minimum_stock || 0,
                (item.unit_cost || 0).toFixed(2),
                totalValue.toFixed(2),
                `"${stockLevel.text}"`
            ];
            csvContent += row.join(',') + '\n';
        });
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `warehouse_stock_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showNotification('Warehouse stock exported successfully', 'success');
    }

    // Show warehouse stock reports
    function showReports() {
        // Create a simple report modal
        const reportModal = document.createElement('div');
        reportModal.id = 'report-modal';
        reportModal.style.cssText = 'display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;';
        reportModal.innerHTML = `
            <div style="background: white; margin: 5% auto; padding: 30px; border-radius: 15px; width: 90%; max-width: 800px; position: relative; max-height: 80vh; overflow-y: auto;">
                <span onclick="closeModal('report-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
                <h3>Warehouse Stock Reports</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <button class="btn btn-primary" onclick="generateStockReport('summary')" style="padding: 20px; text-align: center;">
                        <i class="fas fa-chart-pie" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                        <div>Summary Report</div>
                    </button>
                    <button class="btn btn-warning" onclick="generateStockReport('low')" style="padding: 20px; text-align: center;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                        <div>Low Stock Report</div>
                    </button>
                    <button class="btn btn-danger" onclick="generateStockReport('out')" style="padding: 20px; text-align: center;">
                        <i class="fas fa-times-circle" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                        <div>Out of Stock</div>
                    </button>
                    <button class="btn btn-info" onclick="generateStockReport('movements')" style="padding: 20px; text-align: center;">
                        <i class="fas fa-history" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                        <div>Recent Movements</div>
                    </button>
                </div>
                <div id="stock-report-content" style="margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 8px;">
                    <p style="text-align: center; color: #666;">Select a report type above</p>
                </div>
            </div>
        `;
        
        document.body.appendChild(reportModal);
        document.getElementById('report-modal').style.display = 'block';
    }

    // Generate stock report
    function generateStockReport(type) {
        const content = document.getElementById('stock-report-content');
        content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Generating report...</div>';
        
        setTimeout(() => {
            let reportHTML = '';
            
            switch(type) {
                case 'summary':
                    const totalItems = warehouseStock.length;
                    const lowStockCount = warehouseStock.filter(item => item.current_stock <= item.minimum_stock && item.current_stock > 0).length;
                    const outOfStockCount = warehouseStock.filter(item => item.current_stock <= 0).length;
                    const totalValue = warehouseStock.reduce((sum, item) => sum + (item.current_stock * (item.unit_cost || 0)), 0);
                    
                    reportHTML = `
                        <h4>Warehouse Stock Summary</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
                            <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2em; font-weight: bold; color: #1976d2;">${totalItems}</div>
                                <div>Total Items</div>
                            </div>
                            <div style="background: #fff3e0; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2em; font-weight: bold; color: #f57c00;">${lowStockCount}</div>
                                <div>Low Stock Items</div>
                            </div>
                            <div style="background: #ffebee; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2em; font-weight: bold; color: #d32f2f;">${outOfStockCount}</div>
                                <div>Out of Stock</div>
                            </div>
                            <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2em; font-weight: bold; color: #388e3c;">$${totalValue.toFixed(2)}</div>
                                <div>Total Value</div>
                            </div>
                        </div>
                    `;
                    break;
                case 'low':
                    const lowStockItems = warehouseStock.filter(item => item.current_stock <= item.minimum_stock && item.current_stock > 0);
                    reportHTML = `
                        <h4>Low Stock Items Report</h4>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f5f5f5;">
                                        <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Item Name</th>
                                        <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Category</th>
                                        <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Current Stock</th>
                                        <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Minimum Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${lowStockItems.map(item => `
                                        <tr>
                                            <td style="padding: 10px; border-bottom: 1px solid #eee;">${item.item_name}</td>
                                            <td style="padding: 10px; border-bottom: 1px solid #eee;">${item.category_name}</td>
                                            <td style="padding: 10px; border-bottom: 1px solid #eee; color: #f57c00; font-weight: bold;">${item.current_stock}</td>
                                            <td style="padding: 10px; border-bottom: 1px solid #eee;">${item.minimum_stock || 0}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                    break;
                case 'out':
                    const outOfStockItems = warehouseStock.filter(item => item.current_stock <= 0);
                    reportHTML = `
                        <h4>Out of Stock Items Report</h4>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f5f5f5;">
                                        <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Item Name</th>
                                        <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Category</th>
                                        <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Current Stock</th>
                                        <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Minimum Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${outOfStockItems.map(item => `
                                        <tr>
                                            <td style="padding: 10px; border-bottom: 1px solid #eee;">${item.item_name}</td>
                                            <td style="padding: 10px; border-bottom: 1px solid #eee;">${item.category_name}</td>
                                            <td style="padding: 10px; border-bottom: 1px solid #eee; color: #d32f2f; font-weight: bold;">${item.current_stock}</td>
                                            <td style="padding: 10px; border-bottom: 1px solid #eee;">${item.minimum_stock || 0}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                    break;
                case 'movements':
                    reportHTML = `
                        <h4>Recent Warehouse Movements</h4>
                        <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <p><strong>Note:</strong> Recent warehouse movements would be loaded here.</p>
                            <p>This report would show the latest stock adjustments, purchases, and distributions.</p>
                            <button class="btn btn-primary" onclick="loadRecentMovements()" style="margin-top: 10px;">
                                <i class="fas fa-refresh"></i> Load Recent Movements
                            </button>
                        </div>
                        <div id="movements-content"></div>
                    `;
                    break;
            }
            
            content.innerHTML = reportHTML;
        }, 1000);
    }

    // Load recent movements
    function loadRecentMovements() {
        fetch('warehouse_stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_warehouse_movements'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const content = document.getElementById('movements-content');
                if (data.movements.length === 0) {
                    content.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No recent movements found</p>';
                } else {
                    let html = `
                        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                            <thead>
                                <tr style="background: #f5f5f5;">
                                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Item</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Type</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Quantity</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">User</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    
                    data.movements.forEach(movement => {
                        const typeColor = movement.movement_type === 'in' ? '#10b981' : '#ef4444';
                        const typeText = movement.movement_type === 'in' ? 'In' : 'Out';
                        
                        html += `
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;">${movement.item_name}</td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                    <span style="color: ${typeColor}; font-weight: bold;">${typeText}</span>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;">${movement.quantity}</td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;">${movement.user_name || 'System'}</td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;">${new Date(movement.created_at).toLocaleDateString()}</td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table>';
                    content.innerHTML = html;
                }
            } else {
                showNotification('Failed to load movements', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading movements:', error);
            showNotification('Error loading movements', 'error');
        });
    }
</script>

<?php include 'includes/footer.php'; ?>
