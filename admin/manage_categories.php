<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = "Manage Categories";

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

// Get categories with branch-specific item counts
$query = "SELECT c.*, 
          COUNT(DISTINCT i.id) as total_items,
          COUNT(DISTINCT CASE WHEN bi.branch_id = ? THEN i.id END) as branch_items
          FROM categories c 
          LEFT JOIN items i ON c.id = i.category_id AND i.is_deleted = 0
          LEFT JOIN branch_items bi ON i.id = bi.item_id
          GROUP BY c.id 
          ORDER BY c.display_order, c.name";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$categories = $stmt->fetchAll();

// Get branch name
$query = "SELECT name FROM branches WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$branch_name = $stmt->fetch()['name'] ?? 'Unknown Branch';

include 'includes/header.php';
?>
    <style>
        
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
        
        .action-buttons .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }
        
        .action-buttons .btn-info:hover {
            background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-left: auto;
        }
        
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
        
        .header-actions .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }
        
        .header-actions .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268 0%, #343a40 100%);
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
        
        /* Responsive Button Design */
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
        }
        
        /* Dark Mode Button Adjustments */
        [data-theme="dark"] .header-actions .btn-primary {
            background: linear-gradient(135deg, #27ae60 0%, #3498db 100%);
        }
        
        [data-theme="dark"] .header-actions .btn-secondary {
            background: linear-gradient(135deg, #7f8c8d 0%, #34495e 100%);
        }
        
        [data-theme="dark"] .action-buttons .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        
        [data-theme="dark"] .action-buttons .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        
        [data-theme="dark"] .action-buttons .btn-info {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
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
        
        /* Form Responsive Design */
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Dark Mode Form Adjustments */
        [data-theme="dark"] .form-actions {
            border-top-color: var(--border-color);
        }
        
        .branch-items-count {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            background: #e0f2fe;
            color: #0277bd;
        }
        
        .dark-mode .branch-items-count {
            background: #1e3a8a;
            color: #93c5fd;
        }
    </style>

    <!-- Page Header -->
    <div class="admin-section">
        <div class="page-header">
            <div>
                <h2>🍕 Manage Categories</h2>
                <p>Add, edit, and manage menu categories - Branch: <?php echo htmlspecialchars($branch_name); ?></p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add New Category
                </button>
            </div>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        
        <!-- Categories Table -->
        <table class="categories-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Display Order</th>
                    <th>Total Items</th>
                    <th>Branch Items</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                    <td><?php echo $category['display_order']; ?></td>
                    <td><?php echo $category['total_items']; ?> items</td>
                    <td>
                        <span class="branch-items-count">
                            <?php echo $category['branch_items']; ?> items
                        </span>
                    </td>
                    <td>
                        <span class="btn <?php echo $category['is_active'] ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 4px 8px; font-size: 12px;">
                            <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-info" onclick="viewCategoryItems(<?php echo $category['id']; ?>, <?php echo json_encode($category['name']); ?>)" title="View Items">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-warning" onclick="showEditModal(<?php echo $category['id']; ?>, <?php echo json_encode($category['name']); ?>, <?php echo $category['display_order']; ?>)" title="Edit Category">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>, <?php echo json_encode($category['name']); ?>, <?php echo $category['item_count']; ?>)" title="Delete Category">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
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
                    <button type="submit" class="btn btn-primary">Add Category</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
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
                    <button type="submit" class="btn btn-primary">Update Category</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Category Items Modal -->
    <div id="viewItemsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 id="viewItemsTitle">Category Items</h3>
                <span class="close" onclick="closeModal('viewItemsModal')">&times;</span>
            </div>
            <div id="viewItemsContent">
                <!-- Items will be loaded here -->
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('viewItemsModal')">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Cache busting timestamp: <?php echo time(); ?>
        
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
        
        function viewCategoryItems(categoryId, categoryName) {
            document.getElementById('viewItemsTitle').textContent = categoryName + ' - Items';
            document.getElementById('viewItemsContent').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading items...</div>';
            document.getElementById('viewItemsModal').style.display = 'block';
            
            // Fetch category items
            fetch('get_category_items.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'category_id=' + categoryId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayCategoryItems(data.items);
                } else {
                    document.getElementById('viewItemsContent').innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> Error: ' + data.error + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('viewItemsContent').innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> Error loading items</div>';
            });
        }
        
        function displayCategoryItems(items) {
            let html = '';
            
            if (items.length === 0) {
                html = '<div style="text-align: center; padding: 40px; color: #6c757d;"><i class="fas fa-box-open" style="font-size: 3em; margin-bottom: 15px;"></i><br>No items found in this category</div>';
            } else {
                html = '<div style="max-height: 400px; overflow-y: auto;">';
                html += '<table style="width: 100%; border-collapse: collapse;">';
                html += '<thead style="background: #f8f9fa; position: sticky; top: 0;">';
                html += '<tr>';
                html += '<th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">Name</th>';
                html += '<th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">Price</th>';
                html += '<th style="padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6;">Status</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                
                items.forEach(item => {
                    html += '<tr>';
                    html += '<td style="padding: 12px; border-bottom: 1px solid #dee2e6;">' + item.name + '</td>';
                    html += '<td style="padding: 12px; border-bottom: 1px solid #dee2e6;">';
                    if (item.has_size_variants) {
                        html += '<span style="color: #20bf55; font-weight: 600;">Multiple Sizes</span>';
                    } else {
                        html += 'PKR ' + parseFloat(item.price).toFixed(2);
                    }
                    html += '</td>';
                    html += '<td style="padding: 12px; border-bottom: 1px solid #dee2e6;">';
                    html += '<span class="btn ' + (item.is_available ? 'btn-primary' : 'btn-secondary') + '" style="padding: 4px 8px; font-size: 12px;">';
                    html += item.is_available ? 'Available' : 'Unavailable';
                    html += '</span>';
                    html += '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody>';
                html += '</table>';
                html += '</div>';
                
                html += '<div style="margin-top: 15px; padding: 10px; background: #e9ecef; border-radius: 5px; text-align: center;">';
                html += '<strong>Total Items: ' + items.length + '</strong>';
                html += '</div>';
            }
            
            document.getElementById('viewItemsContent').innerHTML = html;
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

<?php include 'includes/footer.php'; ?> 