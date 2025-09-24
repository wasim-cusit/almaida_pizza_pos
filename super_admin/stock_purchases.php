<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

// Get statistics
$query = "SELECT COUNT(*) as total_purchases FROM stock_purchases";
$stmt = $db->prepare($query);
$stmt->execute();
$total_purchases = $stmt->fetch()['total_purchases'];

$query = "SELECT COUNT(*) as pending_purchases FROM stock_purchases WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_purchases = $stmt->fetch()['pending_purchases'];

$query = "SELECT COUNT(*) as approved_purchases FROM stock_purchases WHERE status = 'approved'";
$stmt = $db->prepare($query);
$stmt->execute();
$approved_purchases = $stmt->fetch()['approved_purchases'];

$query = "SELECT SUM(total_amount) as total_value FROM stock_purchases WHERE status = 'approved'";
$stmt = $db->prepare($query);
$stmt->execute();
$total_value = $stmt->fetch()['total_value'] ?? 0;

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
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
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

<script>
    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadPurchases();
        loadSuppliers();
        loadItems();
        
        // Set today's date as default
        document.getElementById('purchase-date').value = new Date().toISOString().split('T')[0];
    });

    // Load purchases
    function loadPurchases() {
        const statusFilter = document.getElementById('status-filter').value;
        const dateFilter = document.getElementById('date-filter').value;
        
        // Simulate loading purchases (replace with actual AJAX call)
        setTimeout(() => {
            const container = document.getElementById('purchases-container');
            container.innerHTML = `
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
                        <tr>
                            <td><strong>PO-2024-001</strong></td>
                            <td>ABC Suppliers</td>
                            <td>2024-01-15</td>
                            <td>$1,250.00</td>
                            <td><span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #fef3c7; color: #92400e;">Pending</span></td>
                            <td>
                                <button class="btn btn-info" onclick="viewPurchase('PO-2024-001')">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-success" onclick="approvePurchase('PO-2024-001')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>PO-2024-002</strong></td>
                            <td>XYZ Distributors</td>
                            <td>2024-01-14</td>
                            <td>$2,100.00</td>
                            <td><span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #d1fae5; color: #065f46;">Approved</span></td>
                            <td>
                                <button class="btn btn-info" onclick="viewPurchase('PO-2024-002')">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            `;
        }, 1000);
    }

    // Load suppliers
    function loadSuppliers() {
        // Simulate loading suppliers
        const select = document.getElementById('supplier-select');
        select.innerHTML = `
            <option value="">Select supplier...</option>
            <option value="1">ABC Suppliers</option>
            <option value="2">XYZ Distributors</option>
            <option value="3">Global Foods Ltd</option>
        `;
    }

    // Load items
    function loadItems() {
        // Simulate loading items
        const selects = document.querySelectorAll('.item-select');
        selects.forEach(select => {
            select.innerHTML = `
                <option value="">Select item...</option>
                <option value="1">Pizza Dough</option>
                <option value="2">Tomato Sauce</option>
                <option value="3">Mozzarella Cheese</option>
                <option value="4">Pepperoni</option>
            `;
        });
    }

    // Show create purchase modal
    function showCreatePurchaseModal() {
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

    // Placeholder functions
    function viewPurchase(purchaseId) {
        alert('View purchase details for: ' + purchaseId);
    }

    function approvePurchase(purchaseId) {
        if (confirm('Are you sure you want to approve this purchase order?')) {
            alert('Purchase order approved: ' + purchaseId);
            loadPurchases();
        }
    }

    function exportPurchases() {
        alert('Export functionality - Coming soon!');
    }

    function showReports() {
        alert('Reports functionality - Coming soon!');
    }
</script>

<?php include 'includes/footer.php'; ?>