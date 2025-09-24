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
            $query = "SELECT * FROM branches ORDER BY name";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $branches = $stmt->fetchAll();
            echo json_encode(['success' => true, 'branches' => $branches]);
            exit();
            
        case 'create_branch':
            $name = sanitize($_POST['name']);
            $address = sanitize($_POST['address']);
            $phone = sanitize($_POST['phone']);
            $email = sanitize($_POST['email']);
            $manager_name = sanitize($_POST['manager_name']);
            $manager_phone = sanitize($_POST['manager_phone']);
            
            try {
                $query = "INSERT INTO branches (name, address, phone, email, manager_name, manager_phone, is_active) 
                         VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $address, $phone, $email, $manager_name, $manager_phone]);
                
                echo json_encode(['success' => true, 'message' => 'Branch created successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error creating branch: ' . $e->getMessage()]);
            }
            exit();
            
        case 'update_branch':
            $id = (int)$_POST['id'];
            $name = sanitize($_POST['name']);
            $address = sanitize($_POST['address']);
            $phone = sanitize($_POST['phone']);
            $email = sanitize($_POST['email']);
            $manager_name = sanitize($_POST['manager_name']);
            $manager_phone = sanitize($_POST['manager_phone']);
            $is_active = (int)$_POST['is_active'];
            
            try {
                $query = "UPDATE branches SET name = ?, address = ?, phone = ?, email = ?, manager_name = ?, manager_phone = ?, is_active = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $address, $phone, $email, $manager_name, $manager_phone, $is_active, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Branch updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating branch: ' . $e->getMessage()]);
            }
            exit();
            
        case 'delete_branch':
            $id = (int)$_POST['id'];
            
            try {
                $query = "UPDATE branches SET is_active = 0 WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                
                echo json_encode(['success' => true, 'message' => 'Branch deactivated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error deactivating branch: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics
$query = "SELECT COUNT(*) as total_branches FROM branches";
$stmt = $db->prepare($query);
$stmt->execute();
$total_branches = $stmt->fetch()['total_branches'];

$query = "SELECT COUNT(*) as active_branches FROM branches WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$active_branches = $stmt->fetch()['active_branches'];

$query = "SELECT COUNT(*) as inactive_branches FROM branches WHERE is_active = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$inactive_branches = $stmt->fetch()['inactive_branches'];

$page_title = "Manage Branches";
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-building"></i> Manage Branches
    </h1>
    <p class="page-subtitle">Manage restaurant branches and locations</p>
</div>

<!-- Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_branches; ?></div>
        <div>Total Branches</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #20bf55, #16a34a); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $active_branches; ?></div>
        <div>Active Branches</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f59e0b, #f59e0b); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $inactive_branches; ?></div>
        <div>Inactive Branches</div>
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
        <button class="btn btn-primary" onclick="showCreateBranchModal()">
            <i class="fas fa-plus"></i> Add New Branch
        </button>
        <button class="btn btn-info" onclick="loadBranches()">
            <i class="fas fa-refresh"></i> Refresh List
        </button>
        <button class="btn btn-success" onclick="exportBranches()">
            <i class="fas fa-download"></i> Export Data
        </button>
        <button class="btn btn-warning" onclick="showReports()">
            <i class="fas fa-chart-bar"></i> View Reports
        </button>
    </div>
</div>

<!-- Branches List -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Branches
        </h2>
        <div style="display: flex; gap: 10px;">
            <select class="form-control" style="width: auto;" id="status-filter">
                <option value="">All Branches</option>
                <option value="1">Active Only</option>
                <option value="0">Inactive Only</option>
            </select>
        </div>
    </div>
    <div id="branches-container">
        <div style="text-align: center; color: #666; padding: 40px;">
            <i class="fas fa-spinner fa-spin"></i> Loading branches...
        </div>
    </div>
</div>

<!-- Create/Edit Branch Modal -->
<div id="branch-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="background: white; margin: 2% auto; padding: 30px; border-radius: 15px; width: 95%; max-width: 600px; position: relative; max-height: 90vh; overflow-y: auto;">
        <span onclick="closeModal('branch-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
        <h3 id="modal-title">Add New Branch</h3>
        
        <form id="branch-form">
            <input type="hidden" id="branch-id">
            
            <div class="form-group">
                <label>Branch Name *</label>
                <input type="text" class="form-control" id="branch-name" required>
            </div>
            
            <div class="form-group">
                <label>Address *</label>
                <textarea class="form-control" id="branch-address" rows="3" required></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Phone *</label>
                    <input type="tel" class="form-control" id="branch-phone" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" class="form-control" id="branch-email">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Manager Name *</label>
                    <input type="text" class="form-control" id="manager-name" required>
                </div>
                <div class="form-group">
                    <label>Manager Phone</label>
                    <input type="tel" class="form-control" id="manager-phone">
                </div>
            </div>
            
            <div class="form-group" id="status-group" style="display: none;">
                <label>Status</label>
                <select class="form-control" id="branch-status">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('branch-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Branch</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadBranches();
    });

    // Load branches
    function loadBranches() {
        fetch('manage_branches.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_branches'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayBranches(data.branches);
            }
        })
        .catch(error => {
            console.error('Error loading branches:', error);
        });
    }

    // Display branches
    function displayBranches(branches) {
        const container = document.getElementById('branches-container');
        
        if (branches.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No branches found</p>';
            return;
        }

        let html = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Branch Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Manager</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        branches.forEach(branch => {
            const statusClass = branch.is_active ? 'success' : 'danger';
            const statusText = branch.is_active ? 'Active' : 'Inactive';
            
            html += `
                <tr>
                    <td><strong>${branch.name}</strong></td>
                    <td>${branch.address}</td>
                    <td>${branch.phone}</td>
                    <td>${branch.manager_name}</td>
                    <td><span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: ${branch.is_active ? '#d1fae5' : '#fee2e2'}; color: ${branch.is_active ? '#065f46' : '#991b1b'};">${statusText}</span></td>
                    <td>
                        <button class="btn btn-info" onclick="editBranch(${branch.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-${branch.is_active ? 'danger' : 'success'}" onclick="toggleBranchStatus(${branch.id}, ${branch.is_active})">
                            <i class="fas fa-${branch.is_active ? 'ban' : 'check'}"></i> ${branch.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    // Show create branch modal
    function showCreateBranchModal() {
        document.getElementById('modal-title').textContent = 'Add New Branch';
        document.getElementById('branch-form').reset();
        document.getElementById('branch-id').value = '';
        document.getElementById('status-group').style.display = 'none';
        document.getElementById('branch-modal').style.display = 'block';
    }

    // Edit branch
    function editBranch(branchId) {
        // Find branch data (in real app, you'd fetch this)
        const branches = []; // This would be populated from the server
        const branch = branches.find(b => b.id === branchId);
        
        if (branch) {
            document.getElementById('modal-title').textContent = 'Edit Branch';
            document.getElementById('branch-id').value = branch.id;
            document.getElementById('branch-name').value = branch.name;
            document.getElementById('branch-address').value = branch.address;
            document.getElementById('branch-phone').value = branch.phone;
            document.getElementById('branch-email').value = branch.email;
            document.getElementById('manager-name').value = branch.manager_name;
            document.getElementById('manager-phone').value = branch.manager_phone;
            document.getElementById('branch-status').value = branch.is_active;
            document.getElementById('status-group').style.display = 'block';
            document.getElementById('branch-modal').style.display = 'block';
        }
    }

    // Toggle branch status
    function toggleBranchStatus(branchId, currentStatus) {
        const action = currentStatus ? 'deactivate' : 'activate';
        if (confirm(`Are you sure you want to ${action} this branch?`)) {
            fetch('manage_branches.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_branch&id=${branchId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Branch ${action}d successfully`);
                    loadBranches();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error(`Error ${action}ing branch:`, error);
                alert(`Error ${action}ing branch`);
            });
        }
    }

    // Handle form submission
    document.getElementById('branch-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const branchId = document.getElementById('branch-id').value;
        const name = document.getElementById('branch-name').value;
        const address = document.getElementById('branch-address').value;
        const phone = document.getElementById('branch-phone').value;
        const email = document.getElementById('branch-email').value;
        const managerName = document.getElementById('manager-name').value;
        const managerPhone = document.getElementById('manager-phone').value;
        const isActive = document.getElementById('branch-status').value;
        
        const action = branchId ? 'update_branch' : 'create_branch';
        const formData = `action=${action}&name=${encodeURIComponent(name)}&address=${encodeURIComponent(address)}&phone=${encodeURIComponent(phone)}&email=${encodeURIComponent(email)}&manager_name=${encodeURIComponent(managerName)}&manager_phone=${encodeURIComponent(managerPhone)}&is_active=${isActive}`;
        
        if (branchId) {
            formData += `&id=${branchId}`;
        }
        
        fetch('manage_branches.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Branch saved successfully');
                closeModal('branch-modal');
                loadBranches();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error saving branch:', error);
            alert('Error saving branch');
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
    function exportBranches() {
        alert('Export functionality - Coming soon!');
    }

    function showReports() {
        alert('Reports functionality - Coming soon!');
    }
</script>

<?php include 'includes/footer.php'; ?>
