<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize($_POST['name']);
                $address = sanitize($_POST['address']);
                $phone = sanitize($_POST['phone']);
                $email = sanitize($_POST['email']);
                $manager_name = sanitize($_POST['manager_name']);
                
                try {
                    $query = "INSERT INTO branches (name, address, phone, email, manager_name) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$name, $address, $phone, $email, $manager_name]);
                    
                    header('Location: manage_branches.php?success=Branch added successfully');
                    exit();
                } catch (Exception $e) {
                    header('Location: manage_branches.php?error=Error adding branch: ' . $e->getMessage());
                    exit();
                }
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $name = sanitize($_POST['name']);
                $address = sanitize($_POST['address']);
                $phone = sanitize($_POST['phone']);
                $email = sanitize($_POST['email']);
                $manager_name = sanitize($_POST['manager_name']);
                
                try {
                    $query = "UPDATE branches SET name = ?, address = ?, phone = ?, email = ?, manager_name = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$name, $address, $phone, $email, $manager_name, $id]);
                    
                    header('Location: manage_branches.php?success=Branch updated successfully');
                    exit();
                } catch (Exception $e) {
                    header('Location: manage_branches.php?error=Error updating branch: ' . $e->getMessage());
                    exit();
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                try {
                    // Check if branch has users or orders
                    $query = "SELECT COUNT(*) as count FROM users WHERE branch_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    $userCount = $stmt->fetch()['count'];
                    
                    $query = "SELECT COUNT(*) as count FROM orders WHERE branch_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    $orderCount = $stmt->fetch()['count'];
                    
                    if ($userCount > 0 || $orderCount > 0) {
                        // Soft delete
                        $query = "UPDATE branches SET is_active = 0 WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$id]);
                        
                        header('Location: manage_branches.php?success=Branch deactivated (has users/orders)');
                    } else {
                        // Hard delete
                        $query = "DELETE FROM branches WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$id]);
                        
                        header('Location: manage_branches.php?success=Branch deleted successfully');
                    }
                    exit();
                } catch (Exception $e) {
                    header('Location: manage_branches.php?error=Error deleting branch: ' . $e->getMessage());
                    exit();
                }
                break;
                
            case 'activate':
                $id = (int)$_POST['id'];
                
                try {
                    $query = "UPDATE branches SET is_active = 1 WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    
                    header('Location: manage_branches.php?success=Branch activated successfully');
                    exit();
                } catch (Exception $e) {
                    header('Location: manage_branches.php?error=Error activating branch: ' . $e->getMessage());
                    exit();
                }
                break;
        }
    }
}

// Get branches
$query = "SELECT b.*, 
          COUNT(DISTINCT u.id) as user_count,
          COUNT(DISTINCT o.id) as order_count
          FROM branches b 
          LEFT JOIN users u ON b.id = u.branch_id 
          LEFT JOIN orders o ON b.id = o.branch_id 
          GROUP BY b.id 
          ORDER BY b.name";
$stmt = $db->prepare($query);
$stmt->execute();
$branches = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Branches - Super Admin</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .branches-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .branches-table th,
        .branches-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .branches-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .branches-table tr:hover {
            background: #f8f9fa;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-admin {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 14px;
        }
        
        .btn-primary { background: #20bf55; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-success { background: #28a745; color: white; }
        
        .btn-admin:hover {
            opacity: 0.9;
        }
        
        .add-branch-btn {
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
        
        .add-branch-btn:hover {
            background: #1a9f47;
        }
        
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
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            position: relative;
            max-height: 80vh;
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
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #20bf55;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>🏢 Manage Branches</h1>
                <p>Add, edit, and manage restaurant branches</p>
            </div>
            <div>
                <button class="btn-admin btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add New Branch
                </button>
                <a href="super_admin.php" class="btn-admin btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Super Admin
                </a>
            </div>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        
        <table class="branches-table">
            <thead>
                <tr>
                    <th>Branch Name</th>
                    <th>Address</th>
                    <th>Manager</th>
                    <th>Contact</th>
                    <th>Users</th>
                    <th>Orders</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $branch): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($branch['name']); ?></strong>
                    </td>
                    <td><?php echo htmlspecialchars($branch['address']); ?></td>
                    <td><?php echo htmlspecialchars($branch['manager_name'] ?? 'Not assigned'); ?></td>
                    <td>
                        <div><?php echo htmlspecialchars($branch['phone'] ?? 'N/A'); ?></div>
                        <small><?php echo htmlspecialchars($branch['email'] ?? 'N/A'); ?></small>
                    </td>
                    <td><?php echo $branch['user_count']; ?></td>
                    <td><?php echo $branch['order_count']; ?></td>
                    <td>
                        <span class="status-badge <?php echo $branch['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $branch['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-admin btn-warning" onclick="showEditModal(<?php echo $branch['id']; ?>, '<?php echo addslashes($branch['name']); ?>', '<?php echo addslashes($branch['address']); ?>', '<?php echo addslashes($branch['phone']); ?>', '<?php echo addslashes($branch['email']); ?>', '<?php echo addslashes($branch['manager_name']); ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($branch['is_active']): ?>
                                <button class="btn-admin btn-danger" onclick="deleteBranch(<?php echo $branch['id']; ?>, '<?php echo addslashes($branch['name']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn-admin btn-success" onclick="activateBranch(<?php echo $branch['id']; ?>, '<?php echo addslashes($branch['name']); ?>')">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Add Branch Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Branch</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Branch Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                <div class="form-group">
                    <label>Manager Name</label>
                    <input type="text" name="manager_name">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-admin btn-primary">Add Branch</button>
                    <button type="button" class="btn-admin btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Branch Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Branch</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Branch Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="edit_address" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" id="edit_phone">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email">
                </div>
                <div class="form-group">
                    <label>Manager Name</label>
                    <input type="text" name="manager_name" id="edit_manager_name">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-admin btn-primary">Update Branch</button>
                    <button type="button" class="btn-admin btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function showEditModal(id, name, address, phone, email, managerName) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_address').value = address;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_manager_name').value = managerName;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function deleteBranch(id, name) {
            if (confirm('Are you sure you want to delete "' + name + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function activateBranch(id, name) {
            if (confirm('Are you sure you want to activate "' + name + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
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
