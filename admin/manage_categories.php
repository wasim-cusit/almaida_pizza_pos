<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize($_POST['name']);
                $display_order = (int)$_POST['display_order'];
                
                $query = "INSERT INTO categories (name, display_order) VALUES (?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $display_order]);
                
                header('Location: manage_categories.php?success=Category added successfully');
                exit();
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $name = sanitize($_POST['name']);
                $display_order = (int)$_POST['display_order'];
                
                $query = "UPDATE categories SET name = ?, display_order = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$name, $display_order, $id]);
                
                header('Location: manage_categories.php?success=Category updated successfully');
                exit();
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Check if category has items
                $query = "SELECT COUNT(*) as count FROM items WHERE category_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    header('Location: manage_categories.php?error=Cannot delete category with items');
                    exit();
                }
                
                $query = "DELETE FROM categories WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                
                header('Location: manage_categories.php?success=Category deleted successfully');
                exit();
                break;
        }
    }
}

// Get categories
$query = "SELECT c.*, COUNT(i.id) as item_count 
          FROM categories c 
          LEFT JOIN items i ON c.id = i.category_id 
          GROUP BY c.id 
          ORDER BY c.display_order, c.name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Fast Food POS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Override main CSS for admin pages to enable scrolling */
        body {
            overflow: auto !important;
            height: auto !important;
            min-height: 100vh;
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
        
        .categories-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .categories-table th,
        .categories-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .categories-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .categories-table tr:hover {
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
        
        .btn-admin:hover {
            opacity: 0.9;
        }
        
        .add-category-btn {
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
        
        .add-category-btn:hover {
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
        
        .warning-message {
            background: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>🍕 Manage Categories</h1>
                <p>Add, edit, and manage menu categories</p>
            </div>
            <div>
                <button class="btn-admin btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add New Category
                </button>
                <a href="index.php" class="btn-admin btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        
        <table class="categories-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Display Order</th>
                    <th>Items Count</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                    <td><?php echo $category['display_order']; ?></td>
                    <td><?php echo $category['item_count']; ?> items</td>
                    <td>
                        <span class="btn-admin <?php echo $category['is_active'] ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 4px 8px; font-size: 12px;">
                            <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn-admin btn-warning" onclick="showEditModal(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>', <?php echo $category['display_order']; ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-admin btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>', <?php echo $category['item_count']; ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Add Category Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Category</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" value="0" min="0">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-admin btn-primary">Add Category</button>
                    <button type="button" class="btn-admin btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Category Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Category</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="display_order" id="edit_display_order" min="0">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-admin btn-primary">Update Category</button>
                    <button type="button" class="btn-admin btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function showEditModal(id, name, displayOrder) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_display_order').value = displayOrder;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function deleteCategory(id, name, itemCount) {
            if (itemCount > 0) {
                alert('Cannot delete category with items. Please remove all items first.');
                return;
            }
            
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