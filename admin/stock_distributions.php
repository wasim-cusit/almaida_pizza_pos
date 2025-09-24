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
        case 'create_distribution':
            $to_branch_id = (int)$_POST['to_branch_id'];
            $distribution_date = $_POST['distribution_date'];
            $items = json_decode($_POST['items'], true);
            $notes = sanitize($_POST['notes']);
            
            try {
                $db->beginTransaction();
                
                // Generate distribution number
                $distribution_number = 'DIS' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Calculate total items
                $total_items = array_sum(array_column($items, 'quantity'));
                
                // Create distribution record
                $query = "INSERT INTO stock_distributions (distribution_number, to_branch_id, distribution_date, total_items, notes, requested_by) 
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$distribution_number, $to_branch_id, $distribution_date, $total_items, $notes, $_SESSION['user_id']]);
                
                $distribution_id = $db->lastInsertId();
                
                // Insert distribution items
                $query = "INSERT INTO stock_distribution_items (distribution_id, item_id, requested_quantity, unit_cost, total_cost) 
                         VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                foreach ($items as $item) {
                    $total_cost = $item['quantity'] * $item['unit_cost'];
                    $stmt->execute([$distribution_id, $item['item_id'], $item['quantity'], $item['unit_cost'], $total_cost]);
                }
                
                // Create notification for branch
                createNotification(
                    null, // Branch notification
                    $to_branch_id,
                    'distribution_approval',
                    'New Stock Distribution',
                    "Distribution #{$distribution_number} with {$total_items} items is pending approval.",
                    'medium',
                    $distribution_id,
                    'distribution'
                );
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Distribution created successfully', 'distribution_id' => $distribution_id]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error creating distribution: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_branches':
            $query = "SELECT * FROM branches WHERE is_active = 1 ORDER BY name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $branches = $stmt->fetchAll();
            echo json_encode(['success' => true, 'branches' => $branches]);
            exit();
            
        case 'get_warehouse_stock':
            $query = "SELECT mws.*, i.name as item_name, c.name as category_name 
                     FROM main_warehouse_stock mws
                     JOIN items i ON mws.item_id = i.id
                     JOIN categories c ON i.category_id = c.id
                     WHERE mws.current_stock > 0
                     ORDER BY i.name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stock = $stmt->fetchAll();
            echo json_encode(['success' => true, 'stock' => $stock]);
            exit();
            
        case 'get_distributions':
            $query = "SELECT sd.*, b.name as branch_name, u.name as requested_by_name,
                     COUNT(sdi.id) as item_count
                     FROM stock_distributions sd
                     JOIN branches b ON sd.to_branch_id = b.id
                     JOIN users u ON sd.requested_by = u.id
                     LEFT JOIN stock_distribution_items sdi ON sd.id = sdi.distribution_id
                     GROUP BY sd.id
                     ORDER BY sd.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $distributions = $stmt->fetchAll();
            echo json_encode(['success' => true, 'distributions' => $distributions]);
            exit();
            
        case 'approve_distribution':
            $distribution_id = (int)$_POST['distribution_id'];
            
            try {
                $db->beginTransaction();
                
                // Update distribution status
                $query = "UPDATE stock_distributions SET status = 'approved', approved_by = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_SESSION['user_id'], $distribution_id]);
                
                // Get distribution details
                $query = "SELECT * FROM stock_distributions WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$distribution_id]);
                $distribution = $stmt->fetch();
                
                // Get distribution items
                $query = "SELECT * FROM stock_distribution_items WHERE distribution_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$distribution_id]);
                $items = $stmt->fetchAll();
                
                // Update warehouse stock (reduce)
                foreach ($items as $item) {
                    updateWarehouseStock($item['item_id'], -$item['requested_quantity']);
                    
                    // Update branch stock (add)
                    updateBranchStock($distribution['to_branch_id'], $item['item_id'], $item['requested_quantity'], $item['unit_cost']);
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Distribution approved and stock transferred']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error approving distribution: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Function to update warehouse stock
function updateWarehouseStock($item_id, $quantity) {
    global $db;
    
    $query = "UPDATE main_warehouse_stock SET current_stock = current_stock + ? WHERE item_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$quantity, $item_id]);
}

// Function to update branch stock
function updateBranchStock($branch_id, $item_id, $quantity, $unit_cost) {
    global $db;
    
    // Check if branch item exists
    $query = "SELECT id, current_stock FROM branch_items WHERE branch_id = ? AND item_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$branch_id, $item_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing stock
        $new_stock = $existing['current_stock'] + $quantity;
        $query = "UPDATE branch_items SET current_stock = ? WHERE branch_id = ? AND item_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$new_stock, $branch_id, $item_id]);
    } else {
        // Create new branch item record
        $query = "INSERT INTO branch_items (branch_id, item_id, current_stock) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$branch_id, $item_id, $quantity]);
    }
    
    // Record stock movement
    $query = "INSERT INTO stock_movements (branch_id, item_id, movement_type, quantity, previous_stock, new_stock, reference_type, notes, user_id) 
             VALUES (?, ?, 'in', ?, 0, ?, 'transfer', 'Stock distribution from warehouse', ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$branch_id, $item_id, $quantity, $quantity, $_SESSION['user_id']]);
}

// Function to create notifications
function createNotification($user_id, $branch_id, $type, $title, $message, $priority, $related_id, $related_type) {
    global $db;
    
    $query = "INSERT INTO notifications (user_id, branch_id, notification_type, title, message, priority, related_id, related_type) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $branch_id, $type, $title, $message, $priority, $related_id, $related_type]);
}

// Get dashboard statistics
$query = "SELECT COUNT(*) as total_distributions FROM stock_distributions";
$stmt = $db->prepare($query);
$stmt->execute();
$total_distributions = $stmt->fetch()['total_distributions'];

$query = "SELECT COUNT(*) as pending_distributions FROM stock_distributions WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_distributions = $stmt->fetch()['pending_distributions'];

$query = "SELECT SUM(current_stock) as total_warehouse_stock FROM main_warehouse_stock";
$stmt = $db->prepare($query);
$stmt->execute();
$total_warehouse_stock = $stmt->fetch()['total_warehouse_stock'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Distributions - Super Admin</title>
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
            background: linear-gradient(135deg, #20bf55 0%, #01baef 100%);
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
        
        .btn-primary { background: #20bf55; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        
        .btn-super:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .distribution-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .distribution-table th,
        .distribution-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .distribution-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .distribution-table tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-dispatched { background: #dbeafe; color: #1e40af; }
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
            border-color: #20bf55;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: center;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .remove-item {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
        }
        
        .warehouse-stock {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .stock-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-truck"></i> Stock Distributions</h1>
            <p>Distribute stock from warehouse to branches</p>
            <div style="margin-top: 20px;">
                <a href="super_admin.php" class="btn-super btn-info">
                    <i class="fas fa-arrow-left"></i> Back to Super Admin
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #20bf55;"><?php echo $total_distributions; ?></div>
                <div class="stat-label">Total Distributions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #ffc107;"><?php echo $pending_distributions; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #17a2b8;"><?php echo $total_warehouse_stock; ?></div>
                <div class="stat-label">Warehouse Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #6c757d;">0</div>
                <div class="stat-label">This Month</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> Stock Distributions</h2>
                <button class="btn-super btn-primary" onclick="showCreateDistributionModal()">
                    <i class="fas fa-plus"></i> Create Distribution
                </button>
            </div>

            <div id="distributions-container">
                <p style="text-align: center; color: #666; padding: 40px;">
                    Loading distributions...
                </p>
            </div>
        </div>
    </div>

    <!-- Create Distribution Modal -->
    <div id="create-distribution-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('create-distribution-modal')">&times;</span>
            <h3>Create Stock Distribution</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>To Branch</label>
                    <select id="branch-select" required>
                        <option value="">Select branch...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Distribution Date</label>
                    <input type="date" id="distribution-date" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea id="distribution-notes" rows="3"></textarea>
            </div>
            
            <h4>Available Warehouse Stock</h4>
            <div class="warehouse-stock" id="warehouse-stock-container">
                <p style="text-align: center; color: #666;">Loading warehouse stock...</p>
            </div>
            
            <h4>Distribution Items</h4>
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
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn-super btn-secondary" onclick="closeModal('create-distribution-modal')">Cancel</button>
                <button type="button" class="btn-super btn-primary" onclick="createDistribution()">Create Distribution</button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let branches = [];
        let warehouseStock = [];
        let items = [];

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadBranches();
            loadWarehouseStock();
            loadDistributions();
            
            // Set today's date as default
            document.getElementById('distribution-date').value = new Date().toISOString().split('T')[0];
        });

        // Load branches
        function loadBranches() {
            fetch('stock_distributions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_branches'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    branches = data.branches;
                    const select = document.getElementById('branch-select');
                    select.innerHTML = '<option value="">Select branch...</option>';
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

        // Load warehouse stock
        function loadWarehouseStock() {
            fetch('stock_distributions.php', {
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
                container.innerHTML = '<p style="text-align: center; color: #666;">No stock available in warehouse</p>';
                return;
            }

            let html = '';
            stock.forEach(item => {
                html += `
                    <div class="stock-item">
                        <div>
                            <strong>${item.item_name}</strong> (${item.category_name})
                            <br><small>Available: ${item.current_stock} units</small>
                        </div>
                        <button class="btn-super btn-info" onclick="addStockItem(${item.item_id}, '${item.item_name}', ${item.current_stock})">
                            <i class="fas fa-plus"></i> Add to Distribution
                        </button>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Add stock item to distribution
        function addStockItem(itemId, itemName, availableStock) {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.innerHTML = `
                <select class="item-select" required>
                    <option value="${itemId}">${itemName}</option>
                </select>
                <input type="number" class="quantity-input" placeholder="Qty" min="1" max="${availableStock}" required>
                <input type="number" class="unit-cost-input" placeholder="Unit Cost" step="0.01" min="0" required>
                <input type="number" class="total-cost-input" placeholder="Total" readonly>
                <button type="button" class="remove-item" onclick="removeItem(this)">Remove</button>
            `;
            container.appendChild(newRow);
            setupItemRowEvents(newRow);
        }

        // Load distributions
        function loadDistributions() {
            fetch('stock_distributions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_distributions'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayDistributions(data.distributions);
                }
            })
            .catch(error => {
                console.error('Error loading distributions:', error);
            });
        }

        // Display distributions
        function displayDistributions(distributions) {
            const container = document.getElementById('distributions-container');
            
            if (distributions.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No distributions found</p>';
                return;
            }

            let html = `
                <table class="distribution-table">
                    <thead>
                        <tr>
                            <th>Distribution #</th>
                            <th>To Branch</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Requested By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            distributions.forEach(distribution => {
                const statusClass = `status-${distribution.status}`;
                const statusText = distribution.status.charAt(0).toUpperCase() + distribution.status.slice(1);
                
                html += `
                    <tr>
                        <td><strong>${distribution.distribution_number}</strong></td>
                        <td>${distribution.branch_name}</td>
                        <td>${new Date(distribution.distribution_date).toLocaleDateString()}</td>
                        <td>${distribution.item_count}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${distribution.requested_by_name}</td>
                        <td>
                            <button class="btn-super btn-info" onclick="viewDistribution(${distribution.id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                            ${distribution.status === 'pending' ? 
                                `<button class="btn-super btn-success" onclick="approveDistribution(${distribution.id})">
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

        // Show create distribution modal
        function showCreateDistributionModal() {
            document.getElementById('create-distribution-modal').style.display = 'block';
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

        // Create distribution
        function createDistribution() {
            const branchId = document.getElementById('branch-select').value;
            const distributionDate = document.getElementById('distribution-date').value;
            const notes = document.getElementById('distribution-notes').value;
            
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
            
            // Submit distribution
            fetch('stock_distributions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=create_distribution&to_branch_id=${branchId}&distribution_date=${distributionDate}&items=${encodeURIComponent(JSON.stringify(items))}&notes=${encodeURIComponent(notes)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Distribution created successfully');
                    closeModal('create-distribution-modal');
                    loadDistributions();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error creating distribution:', error);
                alert('Error creating distribution');
            });
        }

        // Approve distribution
        function approveDistribution(distributionId) {
            if (confirm('Are you sure you want to approve this distribution? This will transfer stock from warehouse to branch.')) {
                fetch('stock_distributions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=approve_distribution&distribution_id=${distributionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Distribution approved successfully');
                        loadDistributions();
                        loadWarehouseStock();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error approving distribution:', error);
                    alert('Error approving distribution');
                });
            }
        }

        // View distribution details
        function viewDistribution(distributionId) {
            alert('View distribution details - Coming soon!');
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
