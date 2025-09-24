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
            try {
                // Optimized query with only necessary fields and better indexing
                $query = "SELECT id, name, address, phone, email, manager_name, manager_phone, is_active, created_at FROM branches ORDER BY name ASC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $branches = $stmt->fetchAll();
                
                // Add execution time for debugging
                $execution_time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
                
                echo json_encode([
                    'success' => true, 
                    'branches' => $branches,
                    'count' => count($branches),
                    'execution_time' => round($execution_time * 1000, 2) . 'ms'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Database error: ' . $e->getMessage()
                ]);
            }
            exit();

        case 'create_branch':
            $name = sanitize($_POST['name']);
            $address = sanitize($_POST['address']);
            $phone = sanitize($_POST['phone']);
            $email = sanitize($_POST['email']);
            $manager_name = sanitize($_POST['manager_name']);
            $manager_phone = sanitize($_POST['manager_phone']);
            $admin_username = sanitize($_POST['admin_username']);
            $admin_email = sanitize($_POST['admin_email']);
            $admin_password = $_POST['admin_password'];

            try {
                $db->beginTransaction();

                // Create branch
                $query = "INSERT INTO branches (name, address, phone, email, manager_name, manager_phone, is_active) 
                         VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $address, $phone, $email, $manager_name, $manager_phone]);
                $branch_id = $db->lastInsertId();

                // Create branch admin user
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, password, name, email, role, branch_id, is_active, created_at) 
                         VALUES (?, ?, ?, ?, 'admin', ?, 1, NOW())";
                $stmt = $db->prepare($query);
                $stmt->execute([$admin_username, $hashed_password, $manager_name, $admin_email, $branch_id]);

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Branch and admin user created successfully']);
            } catch (Exception $e) {
                $db->rollBack();
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

        case 'toggle_branch_status':
            $id = (int)$_POST['id'];
            $status = (int)$_POST['status'];

            try {
                $query = "UPDATE branches SET is_active = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$status, $id]);

                $status_text = $status ? 'activated' : 'deactivated';
                echo json_encode(['success' => true, 'message' => "Branch {$status_text} successfully"]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating branch status: ' . $e->getMessage()]);
            }
            exit();

        case 'get_branch_users':
            $branch_id = (int)$_POST['branch_id'];
            $query = "SELECT u.*, b.name as branch_name FROM users u 
                     LEFT JOIN branches b ON u.branch_id = b.id 
                     WHERE u.branch_id = ? AND u.role IN ('admin', 'cashier') 
                     ORDER BY u.role, u.name";
            $stmt = $db->prepare($query);
            $stmt->execute([$branch_id]);
            $users = $stmt->fetchAll();
            echo json_encode(['success' => true, 'users' => $users]);
            exit();

        case 'create_branch_user':
            $branch_id = (int)$_POST['branch_id'];
            $username = sanitize($_POST['username']);
            $password = $_POST['password'];
            $name = sanitize($_POST['name']);
            $email = sanitize($_POST['email']);
            $role = sanitize($_POST['role']);
            
            try {
                // Check if username already exists
                $query = "SELECT COUNT(*) as count FROM users WHERE username = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$username]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Username already exists. Please choose a different username.']);
                    exit();
                }
                
                // Check if email already exists
                $query = "SELECT COUNT(*) as count FROM users WHERE email = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$email]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Email address already exists. Please use a different email.']);
                    exit();
                }
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, password, name, email, role, branch_id, is_active, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, 1, NOW())";
                $stmt = $db->prepare($query);
                $stmt->execute([$username, $hashed_password, $name, $email, $role, $branch_id]);
                
                echo json_encode(['success' => true, 'message' => 'User created successfully']);
            } catch (Exception $e) {
                // Handle specific database errors
                $errorMessage = 'Error creating user';
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    if (strpos($e->getMessage(), 'username') !== false) {
                        $errorMessage = 'Username already exists. Please choose a different username.';
                    } elseif (strpos($e->getMessage(), 'email') !== false) {
                        $errorMessage = 'Email address already exists. Please use a different email.';
                    }
                } elseif (strpos($e->getMessage(), 'Data too long') !== false) {
                    $errorMessage = 'One or more fields are too long. Please check your input.';
                } elseif (strpos($e->getMessage(), 'Invalid email') !== false) {
                    $errorMessage = 'Please enter a valid email address.';
                }
                
                echo json_encode(['success' => false, 'message' => $errorMessage]);
            }
            exit();
            
        case 'toggle_user_status':
            $user_id = (int)$_POST['user_id'];
            $status = (int)$_POST['status'];
            
            try {
                $query = "UPDATE users SET is_active = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$status, $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating user status: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_user':
            $user_id = (int)$_POST['user_id'];
            $query = "SELECT * FROM users WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Remove password from response for security
                unset($user['password']);
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
            exit();
            
        case 'update_user':
            $user_id = (int)$_POST['user_id'];
            $username = sanitize($_POST['username']);
            $name = sanitize($_POST['name']);
            $email = sanitize($_POST['email']);
            $role = sanitize($_POST['role']);
            $password = $_POST['password'];
            
            try {
                if (!empty($password)) {
                    // Update with new password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $query = "UPDATE users SET username = ?, password = ?, name = ?, email = ?, role = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$username, $hashed_password, $name, $email, $role, $user_id]);
                } else {
                    // Update without changing password
                    $query = "UPDATE users SET username = ?, name = ?, email = ?, role = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$username, $name, $email, $role, $user_id]);
                }
                
                echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()]);
            }
            exit();
            
        case 'delete_user':
            $user_id = (int)$_POST['user_id'];
            
            try {
                $query = "DELETE FROM users WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics with optimized single query
try {
    $query = "SELECT 
        COUNT(*) as total_branches,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_branches,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_branches
        FROM branches";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetch();
    
    $total_branches = $stats['total_branches'];
    $active_branches = $stats['active_branches'];
    $inactive_branches = $stats['inactive_branches'];
} catch (Exception $e) {
    // Fallback values if query fails
    $total_branches = 0;
    $active_branches = 0;
    $inactive_branches = 0;
}

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
        <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center; justify-content: space-between; width: 100%;">
            <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <div style="position: relative;">
                    <input type="text" id="branch-search" placeholder="Search branches by name, address, or manager..." 
                           style="padding: 8px 35px 8px 12px; border: 1px solid #ddd; border-radius: 6px; width: 300px; font-size: 14px;"
                           onkeyup="filterBranches()">
                    <i class="fas fa-search" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #666;"></i>
                </div>
                
                <select class="form-control" style="width: auto; min-width: 150px;" id="status-filter" onchange="filterBranches()">
                    <option value="">All Branches</option>
                    <option value="1">Active Only</option>
                    <option value="0">Inactive Only</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button class="btn btn-secondary" onclick="clearFilters()" style="padding: 8px 12px; font-size: 12px;">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>
        
        <div id="search-results-info" style="margin-top: 10px; padding: 8px 12px; background: #f8fafc; border-radius: 4px; display: none; font-size: 14px;">
            <span id="search-results-text" style="color: #374151;"></span>
        </div>
    </div>
    <div id="branches-container">
        <div style="text-align: center; color: #666; padding: 40px;">
            <i class="fas fa-spinner fa-spin"></i> Loading branches...
        </div>
    </div>
</div>

<!-- Branch Users Management -->
<div class="card" id="branch-users-card" style="display: none;">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-users"></i> Branch Users
        </h2>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-primary" onclick="showCreateUserModal()">
                <i class="fas fa-plus"></i> Add User
            </button>
            <button class="btn btn-info" onclick="loadBranchUsers()">
                <i class="fas fa-refresh"></i> Refresh
            </button>
        </div>
    </div>
    <div id="branch-users-container">
        <div style="text-align: center; color: #666; padding: 40px;">
            Select a branch to view users
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

            <h4 style="margin: 20px 0 15px 0; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); padding-bottom: 10px;">
                <i class="fas fa-user-shield"></i> Branch Admin Account
            </h4>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Admin Username *</label>
                    <input type="text" class="form-control" id="admin-username" required>
                </div>
                <div class="form-group">
                    <label>Admin Email *</label>
                    <input type="email" class="form-control" id="admin-email" required autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label>Admin Password *</label>
                <input type="password" class="form-control" id="admin-password" required minlength="6" autocomplete="new-password">
                <small style="color: #666;">Minimum 6 characters</small>
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

<!-- Create/Edit User Modal -->
<div id="user-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="background: white; margin: 2% auto; padding: 30px; border-radius: 15px; width: 95%; max-width: 500px; position: relative; max-height: 90vh; overflow-y: auto;">
        <span onclick="closeModal('user-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
        <h3 id="user-modal-title">Add User to Branch</h3>
        
        <form id="user-form">
            <input type="hidden" id="user-branch-id">
            <input type="hidden" id="user-id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" class="form-control" id="user-username" required>
                </div>
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" class="form-control" id="user-name" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" class="form-control" id="user-email" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select class="form-control" id="user-role" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="cashier">Cashier</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Password <span id="password-required">*</span></label>
                <input type="password" class="form-control" id="user-password" minlength="6" autocomplete="new-password">
                <small style="color: #666;" id="password-help">Minimum 6 characters</small>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('user-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="user-submit-btn">Create User</button>
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

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadBranches();
    });

    // Store all branches for filtering
    let allBranches = [];

    // Load branches with improved error handling and timeout
    function loadBranches() {
        const container = document.getElementById('branches-container');
        
        // Show loading indicator
        container.innerHTML = `
            <div style="text-align: center; color: #666; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2em; margin-bottom: 10px;"></i>
                <div>Loading branches...</div>
                <div style="font-size: 12px; margin-top: 10px; color: #999;">Please wait while we fetch the latest data</div>
            </div>
        `;
        
        // Create AbortController for timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
        
        fetch('manage_branches.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_branches',
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    allBranches = data.branches;
                    filterBranches();
                    // Only show notification on manual refresh, not initial load
                    if (window.branchLoadCount && window.branchLoadCount > 0) {
                        showNotification(`Loaded ${data.branches.length} branches successfully`, 'success');
                    }
                    window.branchLoadCount = (window.branchLoadCount || 0) + 1;
                } else {
                    throw new Error(data.message || 'Failed to load branches');
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error('Error loading branches:', error);
                
                let errorMessage = 'Failed to load branches';
                if (error.name === 'AbortError') {
                    errorMessage = 'Request timed out. Please check your connection and try again.';
                } else if (error.message) {
                    errorMessage = error.message;
                }
                
                container.innerHTML = `
                    <div style="text-align: center; color: #dc3545; padding: 40px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3em; margin-bottom: 20px; opacity: 0.7;"></i>
                        <h3 style="margin-bottom: 10px;">Error Loading Branches</h3>
                        <p style="margin-bottom: 20px; color: #666;">${errorMessage}</p>
                        <button class="btn btn-primary" onclick="loadBranches()">
                            <i class="fas fa-redo"></i> Try Again
                        </button>
                    </div>
                `;
                
                showNotification(errorMessage, 'error');
            });
    }

    // Filter branches based on search and status
    function filterBranches() {
        const searchTerm = document.getElementById('branch-search').value.toLowerCase();
        const statusFilter = document.getElementById('status-filter').value;
        const resultsInfo = document.getElementById('search-results-info');
        const resultsText = document.getElementById('search-results-text');
        
        let filteredBranches = allBranches.filter(branch => {
            // Search filter
            const matchesSearch = !searchTerm || 
                branch.name.toLowerCase().includes(searchTerm) ||
                branch.address.toLowerCase().includes(searchTerm) ||
                branch.manager_name.toLowerCase().includes(searchTerm) ||
                branch.email.toLowerCase().includes(searchTerm);
            
            // Status filter
            const matchesStatus = statusFilter === '' || 
                (statusFilter === '1' && branch.is_active == 1) ||
                (statusFilter === '0' && branch.is_active == 0);
            
            return matchesSearch && matchesStatus;
        });
        
        // Update results info
        if (searchTerm || statusFilter !== '') {
            resultsInfo.style.display = 'block';
            const totalBranches = allBranches.length;
            const filteredCount = filteredBranches.length;
            resultsText.textContent = `Showing ${filteredCount} of ${totalBranches} branches`;
        } else {
            resultsInfo.style.display = 'none';
        }
        
        displayBranches(filteredBranches);
    }

    // Clear all filters
    function clearFilters() {
        document.getElementById('branch-search').value = '';
        document.getElementById('status-filter').value = '';
        document.getElementById('search-results-info').style.display = 'none';
        filterBranches();
    }

    // Display branches
    function displayBranches(branches) {
        const container = document.getElementById('branches-container');

        if (branches.length === 0) {
            const searchTerm = document.getElementById('branch-search').value;
            const statusFilter = document.getElementById('status-filter').value;
            
            if (searchTerm || statusFilter !== '') {
                container.innerHTML = `
                    <div style="text-align: center; color: #666; padding: 40px;">
                        <i class="fas fa-search" style="font-size: 3em; margin-bottom: 20px; opacity: 0.5;"></i>
                        <h3 style="margin-bottom: 10px;">No branches found</h3>
                        <p style="margin-bottom: 20px;">Try adjusting your search criteria or filters</p>
                        <button class="btn btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-times"></i> Clear Filters
                        </button>
                    </div>
                `;
            } else {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No branches found</p>';
            }
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
                        <button class="btn btn-warning" onclick="manageBranchUsers(${branch.id}, '${branch.name}')">
                            <i class="fas fa-users"></i> Users
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
        const newStatus = currentStatus ? 0 : 1;
        
        if (confirm(`Are you sure you want to ${action} this branch?`)) {
            fetch('manage_branches.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=toggle_branch_status&id=${branchId}&status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(`Branch ${action}d successfully`, 'success');
                        loadBranches();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error(`Error ${action}ing branch:`, error);
                    showNotification(`Error ${action}ing branch. Please try again.`, 'error');
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
        const adminUsername = document.getElementById('admin-username').value;
        const adminEmail = document.getElementById('admin-email').value;
        const adminPassword = document.getElementById('admin-password').value;

        const action = branchId ? 'update_branch' : 'create_branch';
        let formData = `action=${action}&name=${encodeURIComponent(name)}&address=${encodeURIComponent(address)}&phone=${encodeURIComponent(phone)}&email=${encodeURIComponent(email)}&manager_name=${encodeURIComponent(managerName)}&manager_phone=${encodeURIComponent(managerPhone)}&is_active=${isActive}`;

        if (branchId) {
            formData += `&id=${branchId}`;
        } else {
            // Add admin user fields for new branches
            formData += `&admin_username=${encodeURIComponent(adminUsername)}&admin_email=${encodeURIComponent(adminEmail)}&admin_password=${encodeURIComponent(adminPassword)}`;
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
                    showNotification('Branch saved successfully', 'success');
                    closeModal('branch-modal');
                    loadBranches();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error saving branch:', error);
                showNotification('Error saving branch. Please try again.', 'error');
            });
    });

    // Manage branch users
    function manageBranchUsers(branchId, branchName) {
        document.getElementById('user-branch-id').value = branchId;
        document.getElementById('branch-users-card').style.display = 'block';
        document.querySelector('#branch-users-card .card-title').innerHTML = `<i class="fas fa-users"></i> Users - ${branchName}`;
        loadBranchUsers();
    }

    // Load branch users
    function loadBranchUsers() {
        const branchId = document.getElementById('user-branch-id').value;
        if (!branchId) return;

        fetch('manage_branches.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_branch_users&branch_id=${branchId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayBranchUsers(data.users);
                }
            })
            .catch(error => {
                console.error('Error loading branch users:', error);
            });
    }

    // Display branch users
    function displayBranchUsers(users) {
        const container = document.getElementById('branch-users-container');

        if (users.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No users found for this branch</p>';
            return;
        }

        let html = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        users.forEach(user => {
            const statusText = user.is_active ? 'Active' : 'Inactive';
            const roleText = user.role.charAt(0).toUpperCase() + user.role.slice(1);

            html += `
                <tr>
                    <td><strong>${user.username}</strong></td>
                    <td>${user.name}</td>
                    <td>${user.email}</td>
                    <td><span style="padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; background: ${user.role === 'admin' ? '#dbeafe' : '#f3f4f6'}; color: ${user.role === 'admin' ? '#1e40af' : '#374151'};">${roleText}</span></td>
                    <td><span style="padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; background: ${user.is_active ? '#d1fae5' : '#fee2e2'}; color: ${user.is_active ? '#065f46' : '#991b1b'};">${statusText}</span></td>
                    <td>
                        <button class="btn btn-info" onclick="editUser(${user.id})" style="margin-right: 5px;">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-${user.is_active ? 'danger' : 'success'}" onclick="toggleUserStatus(${user.id}, ${user.is_active})" style="margin-right: 5px;">
                            <i class="fas fa-${user.is_active ? 'ban' : 'check'}"></i> ${user.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                        <button class="btn btn-danger" onclick="deleteUser(${user.id})" style="background: #dc3545; border-color: #dc3545;">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    // Show create user modal
    function showCreateUserModal() {
        const branchId = document.getElementById('user-branch-id').value;
        if (!branchId) {
            alert('Please select a branch first');
            return;
        }

        document.getElementById('user-modal-title').textContent = 'Add User to Branch';
        document.getElementById('user-submit-btn').textContent = 'Create User';
        document.getElementById('user-id').value = '';
        document.getElementById('user-form').reset();
        document.getElementById('user-branch-id').value = branchId;
        document.getElementById('user-password').required = true;
        document.getElementById('password-required').textContent = '*';
        document.getElementById('password-help').textContent = 'Minimum 6 characters';
        document.getElementById('user-modal').style.display = 'block';
    }

    // Handle user form submission
    document.getElementById('user-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const userId = document.getElementById('user-id').value;
        const branchId = document.getElementById('user-branch-id').value;
        const username = document.getElementById('user-username').value;
        const password = document.getElementById('user-password').value;
        const name = document.getElementById('user-name').value;
        const email = document.getElementById('user-email').value;
        const role = document.getElementById('user-role').value;

        // Determine if this is create or update
        const isUpdate = userId && userId !== '';
        const action = isUpdate ? 'update_user' : 'create_branch_user';
        
        let body = `action=${action}&username=${encodeURIComponent(username)}&name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&role=${encodeURIComponent(role)}`;
        
        if (isUpdate) {
            body += `&user_id=${userId}`;
            if (password) {
                body += `&password=${encodeURIComponent(password)}`;
            }
        } else {
            body += `&branch_id=${branchId}&password=${encodeURIComponent(password)}`;
        }

        fetch('manage_branches.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: body
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(isUpdate ? 'User updated successfully' : 'User created successfully', 'success');
                    closeModal('user-modal');
                    loadBranchUsers();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error(`Error ${isUpdate ? 'updating' : 'creating'} user:`, error);
                showNotification(`Error ${isUpdate ? 'updating' : 'creating'} user. Please try again.`, 'error');
            });
    });

    // Edit user function
    function editUser(userId) {
        // Fetch user data
        fetch('manage_branches.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_user&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                
                // Populate form with user data
                document.getElementById('user-modal-title').textContent = 'Edit User';
                document.getElementById('user-submit-btn').textContent = 'Update User';
                document.getElementById('user-id').value = user.id;
                document.getElementById('user-username').value = user.username;
                document.getElementById('user-name').value = user.name;
                document.getElementById('user-email').value = user.email;
                document.getElementById('user-role').value = user.role;
                document.getElementById('user-password').value = '';
                document.getElementById('user-password').required = false;
                document.getElementById('password-required').textContent = '';
                document.getElementById('password-help').textContent = 'Leave blank to keep current password';
                
                // Show modal
                document.getElementById('user-modal').style.display = 'block';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching user:', error);
            alert('Error loading user data');
        });
    }

    // Toggle user status
    function toggleUserStatus(userId, currentStatus) {
        const action = currentStatus ? 'deactivate' : 'activate';
        if (confirm(`Are you sure you want to ${action} this user?`)) {
            // Make AJAX call to update user status
            fetch('manage_branches.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_user_status&user_id=${userId}&status=${currentStatus ? 0 : 1}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`User ${action}d successfully`, 'success');
                    loadBranchUsers();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error(`Error ${action}ing user:`, error);
                showNotification(`Error ${action}ing user. Please try again.`, 'error');
            });
        }
    }

    // Delete user function
    function deleteUser(userId) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            fetch('manage_branches.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_user&user_id=${userId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('User deleted successfully', 'success');
                    loadBranchUsers();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting user:', error);
                showNotification('Error deleting user. Please try again.', 'error');
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

    // Export branches to CSV
    function exportBranches() {
        const searchTerm = document.getElementById('branch-search').value;
        const statusFilter = document.getElementById('status-filter').value;
        
        // Get filtered branches
        let branchesToExport = allBranches.filter(branch => {
            const matchesSearch = !searchTerm || 
                branch.name.toLowerCase().includes(searchTerm) ||
                branch.address.toLowerCase().includes(searchTerm) ||
                branch.manager_name.toLowerCase().includes(searchTerm) ||
                branch.email.toLowerCase().includes(searchTerm);
            
            const matchesStatus = statusFilter === '' || 
                (statusFilter === '1' && branch.is_active == 1) ||
                (statusFilter === '0' && branch.is_active == 0);
            
            return matchesSearch && matchesStatus;
        });
        
        if (branchesToExport.length === 0) {
            showNotification('No branches to export with current filters', 'warning');
            return;
        }
        
        const filterSuffix = searchTerm || statusFilter !== '' ? '_filtered' : '';
        exportToCSV(branchesToExport, `branches${filterSuffix}`);
    }

    // Export to CSV function
    function exportToCSV(data, filename) {
        if (data.length === 0) {
            showNotification('No data to export', 'warning');
            return;
        }

        // Get headers from first object
        const headers = Object.keys(data[0]);
        
        // Create CSV content
        let csvContent = headers.join(',') + '\n';
        
        data.forEach(row => {
            const values = headers.map(header => {
                const value = row[header];
                // Escape commas and quotes
                return typeof value === 'string' && (value.includes(',') || value.includes('"')) 
                    ? `"${value.replace(/"/g, '""')}"` 
                    : value;
            });
            csvContent += values.join(',') + '\n';
        });

        // Create and download file
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `${filename}_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Show reports
    function showReports() {
        // Create a simple report modal
        const reportModal = document.createElement('div');
        reportModal.id = 'report-modal';
        reportModal.style.cssText = 'display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;';
        reportModal.innerHTML = `
            <div style="background: white; margin: 5% auto; padding: 30px; border-radius: 15px; width: 90%; max-width: 800px; position: relative; max-height: 80vh; overflow-y: auto;">
                <span onclick="closeModal('report-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
                <h3>Branch Reports</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <button class="btn btn-primary" onclick="generateReport('summary')" style="padding: 20px; text-align: center;">
                        <i class="fas fa-chart-pie" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                        <div>Summary Report</div>
                    </button>
                    <button class="btn btn-info" onclick="generateReport('users')" style="padding: 20px; text-align: center;">
                        <i class="fas fa-users" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                        <div>User Report</div>
                    </button>
                    <button class="btn btn-success" onclick="generateReport('active')" style="padding: 20px; text-align: center;">
                        <i class="fas fa-check-circle" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                        <div>Active Branches</div>
                    </button>
                    <button class="btn btn-warning" onclick="generateReport('inactive')" style="padding: 20px; text-align: center;">
                        <i class="fas fa-times-circle" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                        <div>Inactive Branches</div>
                    </button>
                </div>
                <div id="report-content" style="margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 8px;">
                    <p style="text-align: center; color: #666;">Select a report type above</p>
                </div>
            </div>
        `;
        
        document.body.appendChild(reportModal);
        document.getElementById('report-modal').style.display = 'block';
    }

    // Generate report
    function generateReport(type) {
        const content = document.getElementById('report-content');
        content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Generating report...</div>';
        
        // Use requestAnimationFrame to avoid forced reflow
        requestAnimationFrame(() => {
            // Simulate report generation
            setTimeout(() => {
                let reportHTML = '';
                
                // Cache DOM queries to avoid multiple reflows
                const tableRows = Array.from(document.querySelectorAll('.table tbody tr'));
                const activeRows = tableRows.filter(row => {
                    const statusSpan = row.querySelector('span');
                    return statusSpan && statusSpan.textContent === 'Active';
                });
                const inactiveRows = tableRows.filter(row => {
                    const statusSpan = row.querySelector('span');
                    return statusSpan && statusSpan.textContent === 'Inactive';
                });
                
                switch(type) {
                    case 'summary':
                        reportHTML = `
                            <h4>Branch Summary Report</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
                                <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2em; font-weight: bold; color: #1976d2;">${tableRows.length}</div>
                                    <div>Total Branches</div>
                                </div>
                                <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2em; font-weight: bold; color: #388e3c;">${activeRows.length}</div>
                                    <div>Active Branches</div>
                                </div>
                                <div style="background: #fff3e0; padding: 15px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2em; font-weight: bold; color: #f57c00;">${inactiveRows.length}</div>
                                    <div>Inactive Branches</div>
                                </div>
                            </div>
                        `;
                        break;
                    case 'users':
                        reportHTML = `
                            <h4>User Distribution Report</h4>
                            <p>This report shows the distribution of users across all branches.</p>
                            <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <p><strong>Note:</strong> User data is managed per branch. Each branch has its own admin and cashier users.</p>
                                <p>To view detailed user information, click the "Users" button for each branch in the main table.</p>
                            </div>
                        `;
                        break;
                    case 'active':
                        reportHTML = `
                            <h4>Active Branches Report</h4>
                            <p>List of all currently active branches:</p>
                            <ul style="margin: 20px 0; padding-left: 20px;">
                                ${activeRows.map(row => `<li>${row.cells[0].textContent}</li>`).join('')}
                            </ul>
                        `;
                        break;
                    case 'inactive':
                        reportHTML = `
                            <h4>Inactive Branches Report</h4>
                            <p>List of all currently inactive branches:</p>
                            <ul style="margin: 20px 0; padding-left: 20px;">
                                ${inactiveRows.map(row => `<li>${row.cells[0].textContent}</li>`).join('')}
                            </ul>
                        `;
                        break;
                }
                
                content.innerHTML = reportHTML;
            }, 1000);
        });
    }
</script>

<?php include 'includes/footer.php'; ?>