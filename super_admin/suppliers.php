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
            
        case 'get_supplier':
            $id = (int)$_POST['id'];
            
            try {
                $query = "SELECT * FROM suppliers WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                $supplier = $stmt->fetch();
                
                if ($supplier) {
                    echo json_encode(['success' => true, 'supplier' => $supplier]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Supplier not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching supplier: ' . $e->getMessage()]);
            }
            exit();
            
        case 'toggle_supplier_status':
            $id = (int)$_POST['id'];
            $status = (int)$_POST['status'];
            
            try {
                $query = "UPDATE suppliers SET is_active = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$status, $id]);
                
                $status_text = $status ? 'activated' : 'deactivated';
                echo json_encode(['success' => true, 'message' => "Supplier {$status_text} successfully"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating supplier status: ' . $e->getMessage()]);
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
        <div style="display: flex; gap: 10px; align-items: center;">
            <select class="form-control" style="width: auto;" id="status-filter">
                <option value="">All Suppliers</option>
                <option value="1">Active Only</option>
                <option value="0">Inactive Only</option>
            </select>
            <button class="btn btn-secondary" onclick="clearFilters()" style="padding: 8px 12px; font-size: 12px;">
                <i class="fas fa-times"></i> Clear Filters
            </button>
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
    // Store all suppliers for filtering
    let allSuppliers = [];

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadSuppliers();
        
        // Add event listener for status filter
        document.getElementById('status-filter').addEventListener('change', filterSuppliers);
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
                allSuppliers = data.suppliers;
                filterSuppliers();
            }
        })
        .catch(error => {
            console.error('Error loading suppliers:', error);
            const container = document.getElementById('suppliers-container');
            container.innerHTML = `
                <div style="text-align: center; color: #dc3545; padding: 40px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3em; margin-bottom: 20px; opacity: 0.7;"></i>
                    <h3 style="margin-bottom: 10px;">Error Loading Suppliers</h3>
                    <p style="margin-bottom: 20px; color: #666;">Failed to load suppliers. Please try again.</p>
                    <button class="btn btn-primary" onclick="loadSuppliers()">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                </div>
            `;
            showNotification('Failed to load suppliers', 'error');
        });
    }

    // Filter suppliers
    function filterSuppliers() {
        const statusFilter = document.getElementById('status-filter').value;
        
        let filteredSuppliers = allSuppliers.filter(supplier => {
            const matchesStatus = statusFilter === '' || supplier.is_active == statusFilter;
            return matchesStatus;
        });
        
        displaySuppliers(filteredSuppliers);
    }

    // Clear all filters
    function clearFilters() {
        document.getElementById('status-filter').value = '';
        filterSuppliers();
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
        // Fetch supplier data from server
        fetch('suppliers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_supplier&id=${supplierId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const supplier = data.supplier;
                
            document.getElementById('modal-title').textContent = 'Edit Supplier';
            document.getElementById('supplier-id').value = supplier.id;
            document.getElementById('supplier-name').value = supplier.name;
            document.getElementById('contact-person').value = supplier.contact_person;
            document.getElementById('supplier-phone').value = supplier.phone;
                document.getElementById('supplier-email').value = supplier.email || '';
            document.getElementById('supplier-address').value = supplier.address;
                document.getElementById('payment-terms').value = supplier.payment_terms || 'Net 30';
            document.getElementById('supplier-status').value = supplier.is_active;
            document.getElementById('status-group').style.display = 'block';
            document.getElementById('supplier-modal').style.display = 'block';
            } else {
                showNotification(data.message, 'error');
        }
        })
        .catch(error => {
            console.error('Error fetching supplier:', error);
            showNotification('Error loading supplier data', 'error');
        });
    }

    // Toggle supplier status
    function toggleSupplierStatus(supplierId, currentStatus) {
        const action = currentStatus ? 'deactivate' : 'activate';
        const newStatus = currentStatus ? 0 : 1;
        
        if (confirm(`Are you sure you want to ${action} this supplier?`)) {
            fetch('suppliers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_supplier_status&id=${supplierId}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`Supplier ${action}d successfully`, 'success');
                    loadSuppliers();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error(`Error ${action}ing supplier:`, error);
                showNotification(`Error ${action}ing supplier`, 'error');
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
                showNotification('Supplier saved successfully', 'success');
                closeModal('supplier-modal');
                loadSuppliers();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error saving supplier:', error);
            showNotification('Error saving supplier', 'error');
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

    // Export suppliers to CSV
    function exportSuppliers() {
        if (allSuppliers.length === 0) {
            showNotification('No suppliers to export', 'warning');
            return;
        }
        
        const headers = ['Name', 'Contact Person', 'Phone', 'Email', 'Address', 'Payment Terms', 'Status', 'Created At'];
        let csvContent = headers.join(',') + '\n';
        
        allSuppliers.forEach(supplier => {
            const row = [
                `"${supplier.name}"`,
                `"${supplier.contact_person}"`,
                `"${supplier.phone}"`,
                `"${supplier.email || ''}"`,
                `"${supplier.address}"`,
                `"${supplier.payment_terms || ''}"`,
                `"${supplier.is_active ? 'Active' : 'Inactive'}"`,
                `"${supplier.created_at}"`
            ];
            csvContent += row.join(',') + '\n';
        });
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `suppliers_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showNotification('Suppliers exported successfully', 'success');
    }

    // Show supplier reports
    function showReports() {
        // Create a simple report modal
        const reportModal = document.createElement('div');
        reportModal.id = 'report-modal';
        reportModal.style.cssText = 'display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;';
        reportModal.innerHTML = `
            <div style="background: white; margin: 5% auto; padding: 30px; border-radius: 15px; width: 90%; max-width: 600px; position: relative; max-height: 80vh; overflow-y: auto;">
                <span onclick="closeModal('report-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
                <h3>Supplier Reports</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <button class="btn btn-primary" onclick="generateSupplierReport('summary')" style="padding: 20px; text-align: center;">
                        <i class="fas fa-chart-pie" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                        <div>Summary Report</div>
                    </button>
                    <button class="btn btn-info" onclick="generateSupplierReport('active')" style="padding: 20px; text-align: center;">
                        <i class="fas fa-check-circle" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                        <div>Active Suppliers</div>
                    </button>
                    <button class="btn btn-warning" onclick="generateSupplierReport('inactive')" style="padding: 20px; text-align: center;">
                        <i class="fas fa-times-circle" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                        <div>Inactive Suppliers</div>
                    </button>
                    <button class="btn btn-success" onclick="generateSupplierReport('purchases')" style="padding: 20px; text-align: center;">
                        <i class="fas fa-shopping-cart" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                        <div>Purchase History</div>
                    </button>
                </div>
                <div id="supplier-report-content" style="margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 8px;">
                    <p style="text-align: center; color: #666;">Select a report type above</p>
                </div>
            </div>
        `;
        
        document.body.appendChild(reportModal);
        document.getElementById('report-modal').style.display = 'block';
    }

    // Generate supplier report
    function generateSupplierReport(type) {
        const content = document.getElementById('supplier-report-content');
        content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Generating report...</div>';
        
        setTimeout(() => {
            let reportHTML = '';
            
            switch(type) {
                case 'summary':
                    const activeCount = allSuppliers.filter(s => s.is_active).length;
                    const inactiveCount = allSuppliers.filter(s => !s.is_active).length;
                    reportHTML = `
                        <h4>Supplier Summary Report</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
                            <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2em; font-weight: bold; color: #1976d2;">${allSuppliers.length}</div>
                                <div>Total Suppliers</div>
                            </div>
                            <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2em; font-weight: bold; color: #388e3c;">${activeCount}</div>
                                <div>Active Suppliers</div>
                            </div>
                            <div style="background: #fff3e0; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2em; font-weight: bold; color: #f57c00;">${inactiveCount}</div>
                                <div>Inactive Suppliers</div>
                            </div>
                        </div>
                    `;
                    break;
                case 'active':
                    const activeSuppliers = allSuppliers.filter(s => s.is_active);
                    reportHTML = `
                        <h4>Active Suppliers Report</h4>
                        <ul style="margin: 20px 0; padding-left: 20px;">
                            ${activeSuppliers.map(s => `<li>${s.name} - ${s.contact_person} (${s.phone})</li>`).join('')}
                        </ul>
                    `;
                    break;
                case 'inactive':
                    const inactiveSuppliers = allSuppliers.filter(s => !s.is_active);
                    reportHTML = `
                        <h4>Inactive Suppliers Report</h4>
                        <ul style="margin: 20px 0; padding-left: 20px;">
                            ${inactiveSuppliers.map(s => `<li>${s.name} - ${s.contact_person} (${s.phone})</li>`).join('')}
                        </ul>
                    `;
                    break;
                case 'purchases':
                    reportHTML = `
                        <h4>Purchase History Report</h4>
                        <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <p><strong>Note:</strong> Purchase history data would be integrated with the stock purchases system.</p>
                            <p>This report would show purchase history by supplier, including total amounts, frequency, and recent activity.</p>
                        </div>
                    `;
                    break;
            }
            
            content.innerHTML = reportHTML;
        }, 1000);
    }
</script>

<?php include 'includes/footer.php'; ?>
