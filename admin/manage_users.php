<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = "Manage Users";

// Add cache control headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Get current user's branch
$branch_id = $_SESSION['branch_id'] ?? null;
if (!$branch_id) {
    die('No branch assigned to your account. Please contact super admin.');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize($_POST['name']);
                $username = sanitize($_POST['username']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role = $_POST['role'];
                
                // Check if username already exists
                $query = "SELECT id FROM users WHERE username = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    header('Location: manage_users.php?error=Username already exists');
                    exit();
                }
                
                $query = "INSERT INTO users (name, username, password, role, branch_id) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $username, $password, $role, $branch_id]);
                
                header('Location: manage_users.php?success=User added successfully');
                exit();
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $name = sanitize($_POST['name']);
                $username = sanitize($_POST['username']);
                $role = $_POST['role'];
                
                // Validate that user belongs to this branch
                $query = "SELECT id FROM users WHERE id = ? AND branch_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id, $branch_id]);
                if (!$stmt->fetch()) {
                    header('Location: manage_users.php?error=User not found or not in your branch');
                    exit();
                }
                
                // Check if username already exists (excluding current user)
                $query = "SELECT id FROM users WHERE username = ? AND id != ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$username, $id]);
                if ($stmt->fetch()) {
                    header('Location: manage_users.php?error=Username already exists');
                    exit();
                }
                
                $query = "UPDATE users SET name = ?, username = ?, role = ? WHERE id = ? AND branch_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $username, $role, $id, $branch_id]);
                
                // Update password if provided
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $query = "UPDATE users SET password = ? WHERE id = ? AND branch_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$password, $id, $branch_id]);
                }
                
                header('Location: manage_users.php?success=User updated successfully');
                exit();
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Don't allow deleting self
                if ($id == $_SESSION['user_id']) {
                    header('Location: manage_users.php?error=Cannot delete your own account');
                    exit();
                }
                
                // Validate that user belongs to this branch
                $query = "SELECT id FROM users WHERE id = ? AND branch_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id, $branch_id]);
                if (!$stmt->fetch()) {
                    header('Location: manage_users.php?error=User not found or not in your branch');
                    exit();
                }
                
                $query = "DELETE FROM users WHERE id = ? AND branch_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id, $branch_id]);
                
                header('Location: manage_users.php?success=User deleted successfully');
                exit();
                break;
        }
    }
}

// Get users from this branch only (excluding super admin)
$query = "SELECT u.*, b.name as branch_name FROM users u 
          LEFT JOIN branches b ON u.branch_id = b.id 
          WHERE u.branch_id = ? AND u.role != 'super_admin'
          ORDER BY u.name";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$users = $stmt->fetchAll();

// Get branch name for display
$query = "SELECT name FROM branches WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$branch_name = $stmt->fetch()['name'] ?? 'Unknown Branch';

include 'includes/header.php';
?>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .users-table tr:hover {
            background: #f8f9fa;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .action-buttons .btn {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.9em;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
        }
        
        .action-buttons .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .action-buttons .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #212529;
        }
        
        .action-buttons .btn-warning:hover {
            background: linear-gradient(135deg, #e0a800 0%, #d39e00 100%);
        }
        
        .action-buttons .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        
        .action-buttons .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
        }
        
        .add-user-btn {
            background: #20bf55;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .add-user-btn:hover {
            background: #1a9f47;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow: auto;
        }
        
        .modal.show {
            display: block !important;
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
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
        
        .close:hover {
            color: #000;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #20bf55;
        }
        
        /* Success/Error messages are now handled by JavaScript popups */
        
        .warning-message {
            background: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
        
        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .role-admin {
            background: #dc3545;
            color: white;
        }
        
        .role-cashier {
            background: #20bf55;
            color: white;
        }
        
        .status-active {
            color: #20bf55;
            font-weight: 500;
        }
        
        .status-inactive {
            color: #dc3545;
            font-weight: 500;
        }
        
        /* Page Header Styles */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .page-header h2,
        .page-header p {
            margin: 0;
        }
        
        .page-header > div:first-child {
            flex: 1;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-left: auto;
        }
        
        .header-actions .btn {
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95em;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        
        .header-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .header-actions .btn-primary {
            background: linear-gradient(135deg, #20bf55 0%, #01baef 100%);
            color: white;
        }
        
        .header-actions .btn-primary:hover {
            background: linear-gradient(135deg, #1aa049 0%, #0193d1 100%);
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .form-actions .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1em;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .form-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Notification Popup Styles */
        .notification-popup {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 10000;
            max-width: 400px;
            min-width: 300px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-left: 4px solid #28a745;
            transform: translateX(-100%);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .notification-popup.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .notification-popup.success {
            border-left-color: #28a745;
        }
        
        .notification-popup.error {
            border-left-color: #dc3545;
        }
        
        .notification-content {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .notification-content i {
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .notification-popup.success .notification-content i {
            color: #28a745;
        }
        
        .notification-popup.error .notification-content i {
            color: #dc3545;
        }
        
        .notification-content span {
            flex: 1;
            font-weight: 500;
            color: #333;
        }
        
        .notification-close {
            background: none;
            border: none;
            font-size: 18px;
            color: #666;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .notification-close:hover {
            background: #f8f9fa;
            color: #333;
        }
        
        /* Dark mode support for notifications */
        body.dark-mode .notification-popup {
            background: #2d3748;
            color: #e2e8f0;
        }
        
        body.dark-mode .notification-content span {
            color: #e2e8f0;
        }
        
        body.dark-mode .notification-close {
            color: #a0aec0;
        }
        
        body.dark-mode .notification-close:hover {
            background: #4a5568;
            color: #e2e8f0;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
                gap: 20px;
            }
            
            .header-actions {
                margin-left: 0;
                justify-content: center;
            }
            
            .header-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }
            
            .action-buttons .btn {
                width: 100%;
                min-width: auto;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <!-- Page Header -->
    <div class="admin-section">
        <div class="page-header">
            <div>
                <h2>👥 Manage Users</h2>
                <p>Add, edit, and manage users for <?php echo htmlspecialchars($branch_name); ?></p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>
        </div>
        
        <!-- Success/Error messages are now handled by JavaScript popups -->
        
        <!-- Users Table -->
        <table class="users-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td>
                        <span class="role-badge role-<?php echo $user['role']; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="btn-admin <?php echo $user['is_active'] ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 4px 8px; font-size: 12px;">
                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-warning" 
                                    onclick="showEditModal(<?php echo $user['id']; ?>, <?php echo json_encode($user['name']); ?>, <?php echo json_encode($user['username']); ?>, <?php echo json_encode($user['role']); ?>)"
                                    data-user-id="<?php echo $user['id']; ?>"
                                    data-user-name="<?php echo htmlspecialchars($user['name']); ?>"
                                    data-user-username="<?php echo htmlspecialchars($user['username']); ?>"
                                    data-user-role="<?php echo htmlspecialchars($user['role']); ?>"
                                    title="Edit User">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, <?php echo json_encode($user['name']); ?>)" title="Delete User">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Add User Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" autocomplete="name" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" autocomplete="username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="cashier">Cashier</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add User</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" id="edit_name" autocomplete="name" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="edit_username" autocomplete="username" required>
                </div>
                <div class="form-group">
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" name="password" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit_role" required>
                        <option value="cashier">Cashier</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update User</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Cache busting handled by script ID -->
    
    <script id="manage-users-<?php echo time(); ?>">
        // Version: 2.0 - Cache busting enabled
        // Script ID: <?php echo time(); ?>
        
        // Clear any cached data and force fresh load
        if (typeof(Storage) !== "undefined") {
            try {
                localStorage.removeItem('manage_users_cache');
                sessionStorage.removeItem('manage_users_cache');
            } catch (e) {
                console.log('Cache clear completed');
            }
        }
        
        // Test if JavaScript is working properly
        try {
            console.log('JavaScript loaded successfully at:', new Date().toISOString());
        } catch (e) {
            console.error('JavaScript error on load:', e);
        }
        
        // Clear URL parameters and show popup notifications
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
            if (success) {
                showNotificationPopup(success, 'success');
                // Clear URL parameters
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            if (error) {
                showNotificationPopup(error, 'error');
                // Clear URL parameters
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
        
        // Notification popup function
        function showNotificationPopup(message, type = 'success') {
            // Remove any existing notifications
            const existingNotifications = document.querySelectorAll('.notification-popup');
            existingNotifications.forEach(notification => notification.remove());
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'notification-popup ' + type;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                    <span>${message}</span>
                    <button class="notification-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
                </div>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
            
            // Animate in
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
        }
        
        // Add event listeners for edit buttons as fallback
        document.addEventListener('DOMContentLoaded', function() {
            const editButtons = document.querySelectorAll('button[title="Edit User"]');
            editButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const userId = this.getAttribute('data-user-id');
                    const userName = this.getAttribute('data-user-name');
                    const userUsername = this.getAttribute('data-user-username');
                    const userRole = this.getAttribute('data-user-role');
                    
                    if (userId && userName && userUsername && userRole) {
                        showEditModal(userId, userName, userUsername, userRole);
                    }
                });
            });
        });
        
        function showAddModal() {
            try {
            document.getElementById('addModal').style.display = 'block';
            } catch (e) {
                console.error('Error showing add modal:', e);
            }
        }
        
        function showEditModal(id, name, username, role) {
            try {
                const editId = document.getElementById('edit_id');
                const editName = document.getElementById('edit_name');
                const editUsername = document.getElementById('edit_username');
                const editRole = document.getElementById('edit_role');
                const editModal = document.getElementById('editModal');
                
                if (!editId || !editName || !editUsername || !editRole || !editModal) {
                    console.error('Missing modal elements');
                    return;
                }
                
                editId.value = id || '';
                editName.value = name || '';
                editUsername.value = username || '';
                editRole.value = role || '';
                editModal.style.display = 'block';
                editModal.classList.add('show');
            } catch (e) {
                console.error('Error showing edit modal:', e);
                alert('Error opening edit modal: ' + e.message);
            }
        }
        
        function closeModal(modalId) {
            try {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'none';
                    modal.classList.remove('show');
                }
            } catch (e) {
                console.error('Error closing modal:', e);
            }
        }
        
        function deleteUser(id, name) {
            try {
            if (confirm('Are you sure you want to delete user "' + name + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
                }
            } catch (e) {
                console.error('Error deleting user:', e);
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            try {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                        modal.classList.remove('show');
                }
            });
            } catch (e) {
                console.error('Error handling modal click:', e);
            }
        }
    </script>

<?php include 'includes/footer.php'; ?> 