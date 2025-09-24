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
        case 'get_suppliers':
            $query = "SELECT * FROM suppliers ORDER BY name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $suppliers = $stmt->fetchAll();
            echo json_encode(['success' => true, 'suppliers' => $suppliers]);
            exit();
            
        case 'create_supplier':
            $name = sanitize($_POST['name']);
            $contact_person = sanitize($_POST['contact_person']);
            $phone = sanitize($_POST['phone']);
            $email = sanitize($_POST['email']);
            $address = sanitize($_POST['address']);
            $payment_terms = sanitize($_POST['payment_terms']);
            
            try {
                $query = "INSERT INTO suppliers (name, contact_person, phone, email, address, payment_terms, is_active) 
                         VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $contact_person, $phone, $email, $address, $payment_terms]);
                
                echo json_encode(['success' => true, 'message' => 'Supplier created successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error creating supplier: ' . $e->getMessage()]);
            }
            exit();
            
        case 'update_supplier':
            $id = (int)$_POST['id'];
            $name = sanitize($_POST['name']);
            $contact_person = sanitize($_POST['contact_person']);
            $phone = sanitize($_POST['phone']);
            $email = sanitize($_POST['email']);
            $address = sanitize($_POST['address']);
            $payment_terms = sanitize($_POST['payment_terms']);
            $is_active = (int)$_POST['is_active'];
            
            try {
                $query = "UPDATE suppliers SET name = ?, contact_person = ?, phone = ?, email = ?, address = ?, payment_terms = ?, is_active = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $contact_person, $phone, $email, $address, $payment_terms, $is_active, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating supplier: ' . $e->getMessage()]);
            }
            exit();
            
        case 'delete_supplier':
            $id = (int)$_POST['id'];
            
            try {
                $query = "UPDATE suppliers SET is_active = 0 WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Supplier deactivated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error deactivating supplier: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics
$query = "SELECT COUNT(*) as total_suppliers FROM suppliers";
$stmt = $db->prepare($query);
$stmt->execute();
$total_suppliers = $stmt->fetch()['total_suppliers'];

$query = "SELECT COUNT(*) as active_suppliers FROM suppliers WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$active_suppliers = $stmt->fetch()['active_suppliers'];

$query = "SELECT COUNT(*) as inactive_suppliers FROM suppliers WHERE is_active = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$inactive_suppliers = $stmt->fetch()['inactive_suppliers'];

$query = "SELECT COUNT(*) as total_purchases FROM stock_purchases sp 
          JOIN suppliers s ON sp.supplier_id = s.id 
          WHERE s.is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$total_purchases = $stmt->fetch()['total_purchases'];

$page_title = "Manage Suppliers";
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-truck-loading"></i> Manage Suppliers
    </h1>
    <p class="page-subtitle">Manage supplier information and relationships</p>
</div>

<!-- Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_suppliers; ?></div>
        <div>Total Suppliers</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #20bf55, #16a34a); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $active_suppliers; ?></div>
        <div>Active Suppliers</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f59e0b, #f59e0b); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $inactive_suppliers; ?></div>
        <div>Inactive Suppliers</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #3b82f6, #3b82f6); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_purchases; ?></div>
        <div>Total Purchases</div>
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
        <button class="btn btn-primary" onclick="showCreateSupplierModal()">
            <i class="fas fa-plus"></i> Add New Supplier
        </button>
        <button class="btn btn-info" onclick="loadSuppliers()">
            <i class="fas fa-refresh"></i> Refresh List
        </button>
        <button class="btn btn-success" onclick="exportSuppliers()">
            <i class="fas fa-download"></i> Export Data
        </button>
        <button class="btn btn-warning" onclick="showReports()">
            <i class="fas fa-chart-bar"></i> View Reports
        </button>
    </div>
</div>

<!-- Suppliers List -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Suppliers
        </h2>
        <div style="display: flex; gap: 10px;">
            <select class="form-control" style="width: auto;" id="status-filter">
                <option value="">All Suppliers</option>
                <option value="1">Active Only</option>
                <option value="0">Inactive Only</option>
            </select>
        </div>
    </div>
    <div id="suppliers-container">
        <div style="text-align: center; color: #666; padding: 40px;">
            <i class="fas fa-spinner fa-spin"></i> Loading suppliers...
        </div>
    </div>
</div>

<!-- Create/Edit Supplier Modal -->
<div id="supplier-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="background: white; margin: 2% auto; padding: 30px; border-radius: 15px; width: 95%; max-width: 600px; position: relative; max-height: 90vh; overflow-y: auto;">
        <span onclick="closeModal('supplier-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
        <h3 id="modal-title">Add New Supplier</h3>
        
        <form id="supplier-form">
            <input type="hidden" id="supplier-id">
            
            <div class="form-group">
                <label>Supplier Name *</label>
                <input type="text" class="form-control" id="supplier-name" required>
            </div>
            
            <div class="form-group">
                <label>Contact Person *</label>
                <input type="text" class="form-control" id="contact-person" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Phone *</label>
                    <input type="tel" class="form-control" id="supplier-phone" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" class="form-control" id="supplier-email">
                </div>
            </div>
            
            <div class="form-group">
                <label>Address *</label>
                <textarea class="form-control" id="supplier-address" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label>Payment Terms</label>
                <select class="form-control" id="payment-terms">
                    <option value="Net 30">Net 30</option>
                    <option value="Net 15">Net 15</option>
                    <option value="Net 7">Net 7</option>
                    <option value="COD">COD</option>
                    <option value="Prepaid">Prepaid</option>
                </select>
            </div>
            
            <div class="form-group" id="status-group" style="display: none;">
                <label>Status</label>
                <select class="form-control" id="supplier-status">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('supplier-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadSuppliers();
    });

    // Load suppliers
    function loadSuppliers() {
        fetch('suppliers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_suppliers'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySuppliers(data.suppliers);
            }
        })
        .catch(error => {
            console.error('Error loading suppliers:', error);
        });
    }

    // Display suppliers
    function displaySuppliers(suppliers) {
        const container = document.getElementById('suppliers-container');
        
        if (suppliers.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No suppliers found</p>';
            return;
        }

        let html = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Payment Terms</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        suppliers.forEach(supplier => {
            const statusClass = supplier.is_active ? 'success' : 'danger';
            const statusText = supplier.is_active ? 'Active' : 'Inactive';
            
            html += `
                <tr>
                    <td><strong>${supplier.name}</strong></td>
                    <td>${supplier.contact_person}</td>
                    <td>${supplier.phone}</td>
                    <td>${supplier.email || 'N/A'}</td>
                    <td>${supplier.payment_terms}</td>
                    <td><span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: ${supplier.is_active ? '#d1fae5' : '#fee2e2'}; color: ${supplier.is_active ? '#065f46' : '#991b1b'};">${statusText}</span></td>
                    <td>
                        <button class="btn btn-info" onclick="editSupplier(${supplier.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-${supplier.is_active ? 'danger' : 'success'}" onclick="toggleSupplierStatus(${supplier.id}, ${supplier.is_active})">
                            <i class="fas fa-${supplier.is_active ? 'ban' : 'check'}"></i> ${supplier.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    // Show create supplier modal
    function showCreateSupplierModal() {
        document.getElementById('modal-title').textContent = 'Add New Supplier';
        document.getElementById('supplier-form').reset();
        document.getElementById('supplier-id').value = '';
        document.getElementById('status-group').style.display = 'none';
        document.getElementById('supplier-modal').style.display = 'block';
    }

    // Edit supplier
    function editSupplier(supplierId) {
        // Find supplier data (in real app, you'd fetch this)
        const suppliers = []; // This would be populated from the server
        const supplier = suppliers.find(s => s.id === supplierId);
        
        if (supplier) {
            document.getElementById('modal-title').textContent = 'Edit Supplier';
            document.getElementById('supplier-id').value = supplier.id;
            document.getElementById('supplier-name').value = supplier.name;
            document.getElementById('contact-person').value = supplier.contact_person;
            document.getElementById('supplier-phone').value = supplier.phone;
            document.getElementById('supplier-email').value = supplier.email;
            document.getElementById('supplier-address').value = supplier.address;
            document.getElementById('payment-terms').value = supplier.payment_terms;
            document.getElementById('supplier-status').value = supplier.is_active;
            document.getElementById('status-group').style.display = 'block';
            document.getElementById('supplier-modal').style.display = 'block';
        }
    }

    // Toggle supplier status
    function toggleSupplierStatus(supplierId, currentStatus) {
        const action = currentStatus ? 'deactivate' : 'activate';
        if (confirm(`Are you sure you want to ${action} this supplier?`)) {
            fetch('suppliers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_supplier&id=${supplierId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Supplier ${action}d successfully`);
                    loadSuppliers();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error(`Error ${action}ing supplier:`, error);
                alert(`Error ${action}ing supplier`);
            });
        }
    }

    // Handle form submission
    document.getElementById('supplier-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const supplierId = document.getElementById('supplier-id').value;
        const name = document.getElementById('supplier-name').value;
        const contactPerson = document.getElementById('contact-person').value;
        const phone = document.getElementById('supplier-phone').value;
        const email = document.getElementById('supplier-email').value;
        const address = document.getElementById('supplier-address').value;
        const paymentTerms = document.getElementById('payment-terms').value;
        const isActive = document.getElementById('supplier-status').value;
        
        const action = supplierId ? 'update_supplier' : 'create_supplier';
        const formData = `action=${action}&name=${encodeURIComponent(name)}&contact_person=${encodeURIComponent(contactPerson)}&phone=${encodeURIComponent(phone)}&email=${encodeURIComponent(email)}&address=${encodeURIComponent(address)}&payment_terms=${encodeURIComponent(paymentTerms)}&is_active=${isActive}`;
        
        if (supplierId) {
            formData += `&id=${supplierId}`;
        }
        
        fetch('suppliers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Supplier saved successfully');
                closeModal('supplier-modal');
                loadSuppliers();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error saving supplier:', error);
            alert('Error saving supplier');
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
    function exportSuppliers() {
        alert('Export functionality - Coming soon!');
    }

    function showReports() {
        alert('Reports functionality - Coming soon!');
    }
</script>

<?php include 'includes/footer.php'; ?>
