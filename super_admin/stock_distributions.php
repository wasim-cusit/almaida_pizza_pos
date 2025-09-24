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
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Distribution created successfully', 'distribution_id' => $distribution_id]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error creating distribution: ' . $e->getMessage()]);
            }
            exit();
            
        case 'approve_distribution':
            $distribution_id = (int)$_POST['distribution_id'];
            
            try {
                $db->beginTransaction();
                
                // Update distribution status
                $query = "UPDATE stock_distributions SET status = 'approved', approved_by = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_SESSION['user_id'], $distribution_id]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Distribution approved successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error approving distribution: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics
$query = "SELECT COUNT(*) as total_distributions FROM stock_distributions";
$stmt = $db->prepare($query);
$stmt->execute();
$total_distributions = $stmt->fetch()['total_distributions'];

$query = "SELECT COUNT(*) as pending_distributions FROM stock_distributions WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_distributions = $stmt->fetch()['pending_distributions'];

$query = "SELECT COUNT(*) as approved_distributions FROM stock_distributions WHERE status = 'approved'";
$stmt = $db->prepare($query);
$stmt->execute();
$approved_distributions = $stmt->fetch()['approved_distributions'];

$query = "SELECT SUM(total_items) as total_items_distributed FROM stock_distributions WHERE status = 'approved'";
$stmt = $db->prepare($query);
$stmt->execute();
$total_items_distributed = $stmt->fetch()['total_items_distributed'] ?? 0;

$page_title = "Stock Distributions";
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-truck"></i> Stock Distributions
    </h1>
    <p class="page-subtitle">Distribute stock from warehouse to branches</p>
</div>

<!-- Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_distributions; ?></div>
        <div>Total Distributions</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f59e0b, #f59e0b); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $pending_distributions; ?></div>
        <div>Pending Approval</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #20bf55, #16a34a); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $approved_distributions; ?></div>
        <div>Approved</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #3b82f6, #3b82f6); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_items_distributed; ?></div>
        <div>Items Distributed</div>
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
        <button class="btn btn-primary" onclick="showCreateDistributionModal()">
            <i class="fas fa-plus"></i> New Distribution
        </button>
        <button class="btn btn-info" onclick="loadDistributions()">
            <i class="fas fa-refresh"></i> Refresh List
        </button>
        <button class="btn btn-success" onclick="exportDistributions()">
            <i class="fas fa-download"></i> Export Data
        </button>
        <button class="btn btn-warning" onclick="showReports()">
            <i class="fas fa-chart-bar"></i> View Reports
        </button>
    </div>
</div>

<!-- Distributions List -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Stock Distributions
        </h2>
        <div style="display: flex; gap: 10px;">
            <select class="form-control" style="width: auto;" id="status-filter">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="dispatched">Dispatched</option>
                <option value="received">Received</option>
            </select>
            <input type="date" class="form-control" style="width: auto;" id="date-filter">
        </div>
    </div>
    <div id="distributions-container">
        <div style="text-align: center; color: #666; padding: 40px;">
            <i class="fas fa-spinner fa-spin"></i> Loading distributions...
        </div>
    </div>
</div>

<!-- Create Distribution Modal -->
<div id="create-distribution-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="background: white; margin: 2% auto; padding: 30px; border-radius: 15px; width: 95%; max-width: 1000px; position: relative; max-height: 90vh; overflow-y: auto;">
        <span onclick="closeModal('create-distribution-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
        <h3>Create Stock Distribution</h3>
        
        <form id="distribution-form">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>To Branch</label>
                    <select class="form-control" id="branch-select" required>
                        <option value="">Select branch...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Distribution Date</label>
                    <input type="date" class="form-control" id="distribution-date" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea class="form-control" id="distribution-notes" rows="3"></textarea>
            </div>
            
            <h4>Available Warehouse Stock</h4>
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;" id="warehouse-stock-container">
                <p style="text-align: center; color: #666;">Loading warehouse stock...</p>
            </div>
            
            <h4>Distribution Items</h4>
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
                <button type="button" class="btn btn-secondary" onclick="closeModal('create-distribution-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Distribution</button>
            </div>
        </form>
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
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; margin-bottom: 10px;">
                    <div>
                        <strong>${item.item_name}</strong> (${item.category_name})
                        <br><small>Available: ${item.current_stock} units</small>
                    </div>
                    <button class="btn btn-info" onclick="addStockItem(${item.item_id}, '${item.item_name}', ${item.current_stock})">
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
        newRow.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 10px; align-items: center; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; margin-bottom: 10px;';
        newRow.innerHTML = `
            <select class="form-control item-select" required>
                <option value="${itemId}">${itemName}</option>
            </select>
            <input type="number" class="form-control quantity-input" placeholder="Qty" min="1" max="${availableStock}" required>
            <input type="number" class="form-control unit-cost-input" placeholder="Unit Cost" step="0.01" min="0" required>
            <input type="number" class="form-control total-cost-input" placeholder="Total" readonly>
            <button type="button" class="btn btn-danger" onclick="removeItem(this)">Remove</button>
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
            <table class="table">
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
                    <td><span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: ${getStatusColor(distribution.status)}; color: white;">${statusText}</span></td>
                    <td>${distribution.requested_by_name}</td>
                    <td>
                        <button class="btn btn-info" onclick="viewDistribution(${distribution.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                        ${distribution.status === 'pending' ? 
                            `<button class="btn btn-success" onclick="approveDistribution(${distribution.id})">
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

    // Get status color
    function getStatusColor(status) {
        switch(status) {
            case 'pending': return '#f59e0b';
            case 'approved': return '#20bf55';
            case 'dispatched': return '#3b82f6';
            case 'received': return '#20bf55';
            default: return '#6c757d';
        }
    }

    // Show create distribution modal
    function showCreateDistributionModal() {
        document.getElementById('create-distribution-modal').style.display = 'block';
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
        document.querySelectorAll('#items-container > div').forEach(row => {
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
        if (confirm('Are you sure you want to approve this distribution?')) {
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
        const modals = document.querySelectorAll('[id$="-modal"]');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

    // Placeholder functions
    function exportDistributions() {
        alert('Export functionality - Coming soon!');
    }

    function showReports() {
        alert('Reports functionality - Coming soon!');
    }

    // Handle form submission
    document.getElementById('distribution-form').addEventListener('submit', function(e) {
        e.preventDefault();
        createDistribution();
    });
</script>

<?php include 'includes/footer.php'; ?>
