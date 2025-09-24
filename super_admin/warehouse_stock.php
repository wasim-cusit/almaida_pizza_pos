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

$query = "SELECT SUM(current_stock * unit_cost) as total_value FROM main_warehouse_stock";
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
        <div style="display: flex; gap: 10px;">
            <select class="form-control" style="width: auto;" id="category-filter">
                <option value="">All Categories</option>
            </select>
            <select class="form-control" style="width: auto;" id="status-filter">
                <option value="">All Status</option>
                <option value="good">Good Stock</option>
                <option value="low">Low Stock</option>
                <option value="out">Out of Stock</option>
            </select>
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
                displayWarehouseStock(data.stock);
            }
        })
        .catch(error => {
            console.error('Error loading warehouse stock:', error);
        });
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
        // Simulate loading categories
        const select = document.getElementById('category-filter');
        select.innerHTML = `
            <option value="">All Categories</option>
            <option value="1">Food Items</option>
            <option value="2">Beverages</option>
            <option value="3">Supplies</option>
        `;
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
            alert('Stock cannot be negative');
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
                alert('Warehouse stock updated successfully');
                closeModal('adjust-stock-modal');
                loadWarehouseStock();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error updating warehouse stock:', error);
            alert('Error updating warehouse stock');
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

    // Placeholder functions
    function exportStock() {
        alert('Export functionality - Coming soon!');
    }

    function showReports() {
        alert('Reports functionality - Coming soon!');
    }
</script>

<?php include 'includes/footer.php'; ?>
