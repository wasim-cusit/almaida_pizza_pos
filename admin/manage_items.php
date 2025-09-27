<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = "Manage Items";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize($_POST['name']);
                $category_id = (int)$_POST['category_id'];
                $price = (float)$_POST['price'];
                $description = sanitize($_POST['description']);
                $has_size_variants = isset($_POST['has_size_variants']) ? 1 : 0;
                
                try {
                    $db->beginTransaction();
                    
                    // Insert the item
                    $query = "INSERT INTO items (name, category_id, price, description, has_size_variants) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$name, $category_id, $price, $description, $has_size_variants]);
                    
                    $item_id = $db->lastInsertId();
                    
                    // Handle size variants if enabled
                    if ($has_size_variants && isset($_POST['size_variants'])) {
                        $size_variants = $_POST['size_variants'];
                        foreach ($size_variants as $variant) {
                            if (!empty($variant['name']) && !empty($variant['price'])) {
                                $query = "INSERT INTO item_size_variants (item_id, size_name, size_price) VALUES (?, ?, ?)";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$item_id, sanitize($variant['name']), (float)$variant['price']]);
                            }
                        }
                    }
                    
                    $db->commit();
                    header('Location: manage_items.php?success=Item added successfully');
                    exit();
                } catch (Exception $e) {
                    $db->rollBack();
                    header('Location: manage_items.php?error=Error adding item: ' . $e->getMessage());
                    exit();
                }
                break;
                
            case 'edit':
                $id = (int)$_POST['id'];
                $name = sanitize($_POST['name']);
                $category_id = (int)$_POST['category_id'];
                $price = (float)$_POST['price'];
                $description = sanitize($_POST['description']);
                $has_size_variants = isset($_POST['has_size_variants']) ? 1 : 0;
                
                try {
                    $db->beginTransaction();
                    
                    // Update the item
                    $query = "UPDATE items SET name = ?, category_id = ?, price = ?, description = ?, has_size_variants = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$name, $category_id, $price, $description, $has_size_variants, $id]);
                    
                    // Delete existing size variants
                    $query = "DELETE FROM item_size_variants WHERE item_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    
                    // Handle size variants if enabled
                    if ($has_size_variants && isset($_POST['size_variants'])) {
                        $size_variants = $_POST['size_variants'];
                        foreach ($size_variants as $variant) {
                            if (!empty($variant['name']) && !empty($variant['price'])) {
                                $query = "INSERT INTO item_size_variants (item_id, size_name, size_price) VALUES (?, ?, ?)";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$id, sanitize($variant['name']), (float)$variant['price']]);
                            }
                        }
                    }
                    
                    $db->commit();
                    header('Location: manage_items.php?success=Item updated successfully');
                    exit();
                } catch (Exception $e) {
                    $db->rollBack();
                    header('Location: manage_items.php?error=Error updating item: ' . $e->getMessage());
                    exit();
                }
                break;
                
            case 'delete':
                // Delete functionality is now handled via AJAX in delete_item.php
                header('Location: manage_items.php?error=Please use the delete button in the interface');
                exit();
                break;
                
            case 'restore':
                $id = (int)$_POST['id'];
                
                try {
                    $query = "UPDATE items SET is_deleted = 0, is_available = 1 WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    
                    header('Location: manage_items.php?success=Item restored successfully');
                    exit();
                } catch (Exception $e) {
                    header('Location: manage_items.php?error=Error restoring item: ' . $e->getMessage());
                    exit();
                }
                break;
        }
    }
}

// Get categories for dropdown
$query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll();

// Get items with category names and size variants (excluding soft-deleted items)
$query = "SELECT i.*, c.name as category_name 
          FROM items i 
          JOIN categories c ON i.category_id = c.id 
          WHERE i.is_deleted = 0
          ORDER BY c.name, i.name";
$stmt = $db->prepare($query);
$stmt->execute();
$items = $stmt->fetchAll();

// Get size variants for each item
$item_size_variants = [];
foreach ($items as $item) {
    $query = "SELECT * FROM item_size_variants WHERE item_id = ? ORDER BY size_price";
    $stmt = $db->prepare($query);
    $stmt->execute([$item['id']]);
    $item_size_variants[$item['id']] = $stmt->fetchAll();
}

include 'includes/header.php';
?>
    <style>
        /* Manage Items Page Specific Styles */
        
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .items-table th,
        .items-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .items-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .items-table tr:hover {
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
        
        /* Enhanced Button Styles */
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
            max-width: 600px;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #20bf55;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .size-variants-section {
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
            background: #f8f9fa;
        }
        
        .size-variants-section h4 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .size-variant-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
            align-items: end;
            margin-bottom: 10px;
        }
        
        .size-variant-row input {
            margin: 0;
        }
        
        .remove-size-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .add-size-btn {
            background: #20bf55;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            transition: all 0.3s ease;
        }
        
        .add-size-btn:hover {
            background: #1a9f47;
            transform: translateY(-1px);
        }
        
        .remove-size-btn:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
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
        
        .size-variants-display {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .size-variant-tag {
            display: inline-block;
            background: #e9ecef;
            color: #495057;
            padding: 2px 6px;
            border-radius: 4px;
            margin: 1px;
            font-size: 11px;
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
            
            .size-variant-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .size-variant-row button {
                width: 100%;
            }
        }
        
        /* Dark Mode Form Adjustments */
        [data-theme="dark"] .form-actions {
            border-top-color: var(--border-color);
        }
        
        [data-theme="dark"] .size-variants-section {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .size-variants-section h4 {
            color: var(--text-primary);
        }
        
        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            background: linear-gradient(135deg, #20bf55 0%, #01baef 100%);
        }
        
        .notification.error {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .notification.info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        }
        
        /* Notification Popup Styles */
        .notification-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10001;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .notification-popup.show {
            opacity: 1;
            visibility: visible;
        }
        
        .notification-popup-content {
            background: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transform: scale(0.8);
            transition: transform 0.3s ease;
        }
        
        .notification-popup.show .notification-popup-content {
            transform: scale(1);
        }
        
        .notification-popup-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        .notification-popup.success .notification-popup-icon {
            color: #20bf55;
        }
        
        .notification-popup.error .notification-popup-icon {
            color: #dc3545;
        }
        
        .notification-popup h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.5em;
        }
        
        .notification-popup p {
            margin: 0 0 25px 0;
            color: #666;
            font-size: 1.1em;
            line-height: 1.5;
        }
        
        .notification-popup .btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 1em;
            transition: all 0.3s ease;
        }
        
        .notification-popup .btn-primary {
            background: linear-gradient(135deg, #20bf55 0%, #01baef 100%);
            color: white;
        }
        
        .notification-popup .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(32, 191, 85, 0.3);
        }
        
        /* Dark Mode Notifications */
        [data-theme="dark"] .notification-popup-content {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .notification-popup h3 {
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .notification-popup p {
            color: var(--text-secondary);
        }
    </style>

    <!-- Page Header -->
    <div class="admin-section">
        <div class="page-header">
            <div>
                <h2>🍕 Manage Menu Items</h2>
                <p>Add, edit, and manage menu items with size variants</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="fas fa-plus"></i> Add New Item
                </button>
                <button class="btn btn-secondary" onclick="showSoftDeletedItems()">
                    <i class="fas fa-trash"></i> View Deleted Items
                </button>
            </div>
        </div>
    
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        
        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Size Variants</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                    <td>
                        <?php if ($item['has_size_variants']): ?>
                            <span style="color: #20bf55; font-weight: 600;">Multiple Sizes</span>
                        <?php else: ?>
                            PKR <?php echo number_format($item['price'], 2); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($item['has_size_variants'] && isset($item_size_variants[$item['id']])): ?>
                            <div class="size-variants-display">
                                <?php foreach ($item_size_variants[$item['id']] as $variant): ?>
                                    <span class="size-variant-tag">
                                        <?php echo htmlspecialchars($variant['size_name']); ?>: PKR <?php echo number_format($variant['size_price'], 2); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #999;">No variants</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
                    <td>
                        <span class="btn <?php echo $item['is_available'] ? 'btn-primary' : 'btn-secondary'; ?>" style="padding: 4px 8px; font-size: 12px;">
                            <?php echo $item['is_available'] ? 'Available' : 'Unavailable'; ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-warning" onclick="showEditModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['category_id']; ?>, <?php echo $item['price']; ?>, '<?php echo addslashes($item['description'] ?? ''); ?>', <?php echo $item['has_size_variants']; ?>, <?php echo htmlspecialchars(json_encode($item_size_variants[$item['id']] ?? [])); ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Add Item Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Item</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="name" placeholder="Enter item name" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price (PKR)</label>
                    <input type="number" name="price" step="0.01" min="0" placeholder="Enter item price" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Enter item description (optional)"></textarea>
                </div>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="has_size_variants" name="has_size_variants" onchange="toggleSizeVariants()">
                        <label for="has_size_variants">Enable Size Variants</label>
                    </div>
                </div>
                                 <div id="size-variants-section" class="size-variants-section" style="display: none;">
                     <h4>Size Variants</h4>
                     <div id="size-variants-container">
                         <div class="size-variant-row">
                             <input type="text" name="size_variants[0][name]" placeholder="Size Name (e.g., Small)">
                             <input type="number" name="size_variants[0][price]" step="0.01" placeholder="Price">
                             <button type="button" class="remove-size-btn" onclick="removeSizeVariant(this)">Remove</button>
                         </div>
                     </div>
                     <button type="button" class="add-size-btn" onclick="addSizeVariant()">Add Another Size</button>
                 </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Item</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Item Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Item</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" id="edit_category_id" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price (PKR)</label>
                    <input type="number" name="price" id="edit_price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="edit_has_size_variants" name="has_size_variants" onchange="toggleEditSizeVariants()">
                        <label for="edit_has_size_variants">Enable Size Variants</label>
                    </div>
                </div>
                <div id="edit-size-variants-section" class="size-variants-section" style="display: none;">
                    <h4>Size Variants</h4>
                    <div id="edit-size-variants-container">
                        <!-- Size variants will be loaded here -->
                    </div>
                    <button type="button" class="add-size-btn" onclick="addEditSizeVariant()">Add Another Size</button>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Item</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let sizeVariantCounter = 1;
        let editSizeVariantCounter = 0;
        
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
            // Reset form
            document.getElementById('has_size_variants').checked = false;
            toggleSizeVariants();
        }
        
        function showEditModal(id, name, categoryId, price, description, hasSizeVariants, sizeVariants) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_category_id').value = categoryId;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_has_size_variants').checked = hasSizeVariants;
            
            // Load size variants
            loadEditSizeVariants(sizeVariants);
            toggleEditSizeVariants();
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function deleteItem(id, name) {
            if (confirm('Are you sure you want to delete "' + name + '"?')) {
                // Show loading state
                showNotification('Deleting item...', 'info');
                
                fetch('delete_item.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + encodeURIComponent(id)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success popup
                        showNotificationPopup(data.message, 'success', data.type, data.order_count);
                        // Reload the page to update the table
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        showNotification('Error: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error deleting item', 'error');
                });
            }
        }
        
                 function toggleSizeVariants() {
             const checkbox = document.getElementById('has_size_variants');
             const section = document.getElementById('size-variants-section');
             const priceInput = document.querySelector('input[name="price"]');
             const sizeInputs = section.querySelectorAll('input[name*="[name]"], input[name*="[price]"]');
             
             if (checkbox.checked) {
                 section.style.display = 'block';
                 priceInput.required = false;
                 priceInput.placeholder = 'Base price (optional)';
                 // Make size variant inputs required when section is visible
                 sizeInputs.forEach(input => {
                     input.required = true;
                 });
             } else {
                 section.style.display = 'none';
                 priceInput.required = true;
                 priceInput.placeholder = 'Price';
                 // Remove required from size variant inputs when section is hidden
                 sizeInputs.forEach(input => {
                     input.required = false;
                 });
             }
         }
        
                 function toggleEditSizeVariants() {
             const checkbox = document.getElementById('edit_has_size_variants');
             const section = document.getElementById('edit-size-variants-section');
             const priceInput = document.getElementById('edit_price');
             const sizeInputs = section.querySelectorAll('input[name*="[name]"], input[name*="[price]"]');
             
             if (checkbox.checked) {
                 section.style.display = 'block';
                 priceInput.required = false;
                 priceInput.placeholder = 'Base price (optional)';
                 // Make size variant inputs required when section is visible
                 sizeInputs.forEach(input => {
                     input.required = true;
                 });
             } else {
                 section.style.display = 'none';
                 priceInput.required = true;
                 priceInput.placeholder = 'Price';
                 // Remove required from size variant inputs when section is hidden
                 sizeInputs.forEach(input => {
                     input.required = false;
                 });
             }
         }
        
                 function addSizeVariant() {
             const container = document.getElementById('size-variants-container');
             const checkbox = document.getElementById('has_size_variants');
             const newRow = document.createElement('div');
             newRow.className = 'size-variant-row';
             newRow.innerHTML = `
                 <input type="text" name="size_variants[${sizeVariantCounter}][name]" placeholder="Size Name (e.g., Medium)" ${checkbox.checked ? 'required' : ''}>
                 <input type="number" name="size_variants[${sizeVariantCounter}][price]" step="0.01" placeholder="Price" ${checkbox.checked ? 'required' : ''}>
                 <button type="button" class="remove-size-btn" onclick="removeSizeVariant(this)">Remove</button>
             `;
             container.appendChild(newRow);
             sizeVariantCounter++;
         }
        
                 function addEditSizeVariant() {
             const container = document.getElementById('edit-size-variants-container');
             const checkbox = document.getElementById('edit_has_size_variants');
             const newRow = document.createElement('div');
             newRow.className = 'size-variant-row';
             newRow.innerHTML = `
                 <input type="text" name="size_variants[${editSizeVariantCounter}][name]" placeholder="Size Name (e.g., Medium)" ${checkbox.checked ? 'required' : ''}>
                 <input type="number" name="size_variants[${editSizeVariantCounter}][price]" step="0.01" placeholder="Price" ${checkbox.checked ? 'required' : ''}>
                 <button type="button" class="remove-size-btn" onclick="removeSizeVariant(this)">Remove</button>
             `;
             container.appendChild(newRow);
             editSizeVariantCounter++;
         }
        
        function removeSizeVariant(button) {
            button.parentElement.remove();
        }
        
                 function loadEditSizeVariants(sizeVariants) {
             const container = document.getElementById('edit-size-variants-container');
             const checkbox = document.getElementById('edit_has_size_variants');
             container.innerHTML = '';
             editSizeVariantCounter = 0;
             
             if (sizeVariants && sizeVariants.length > 0) {
                 sizeVariants.forEach(variant => {
                     const newRow = document.createElement('div');
                     newRow.className = 'size-variant-row';
                     newRow.innerHTML = `
                         <input type="text" name="size_variants[${editSizeVariantCounter}][name]" value="${variant.size_name}" placeholder="Size Name" ${checkbox.checked ? 'required' : ''}>
                         <input type="number" name="size_variants[${editSizeVariantCounter}][price]" value="${variant.size_price}" step="0.01" placeholder="Price" ${checkbox.checked ? 'required' : ''}>
                         <button type="button" class="remove-size-btn" onclick="removeSizeVariant(this)">Remove</button>
                     `;
                     container.appendChild(newRow);
                     editSizeVariantCounter++;
                 });
             } else {
                 // Add one empty row
                 addEditSizeVariant();
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
        
        // Function to show soft-deleted items
        function showSoftDeletedItems() {
            fetch('get_soft_deleted_items.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSoftDeletedModal(data.items);
                    } else {
                        alert('Error loading soft-deleted items: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading soft-deleted items');
                });
        }
        
        function showSoftDeletedModal(items) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'block';
            
            let itemsHtml = '';
            if (items.length > 0) {
                items.forEach(item => {
                    itemsHtml += `
                        <div style="border: 1px solid #e0e0e0; padding: 15px; margin: 10px 0; border-radius: 8px; background: #f8f9fa;">
                            <h4 style="margin: 0 0 10px 0; color: #dc3545;">${item.name}</h4>
                            <p style="margin: 5px 0;"><strong>Category:</strong> ${item.category_name}</p>
                            <p style="margin: 5px 0;"><strong>Price:</strong> PKR ${item.price}</p>
                            <p style="margin: 5px 0; font-size: 12px; color: #666;"><strong>Deleted:</strong> ${item.updated_at}</p>
                            <button class="btn btn-primary" onclick="restoreItem(${item.id})" style="margin-top: 10px;">
                                <i class="fas fa-undo"></i> Restore Item
                            </button>
                        </div>
                    `;
                });
            } else {
                itemsHtml = '<p style="text-align: center; color: #666;">No soft-deleted items found.</p>';
            }
            
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Soft-Deleted Items (${items.length})</h3>
                        <span class="close" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</span>
                    </div>
                    <div class="modal-body">
                        ${itemsHtml}
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
                 function restoreItem(itemId) {
             if (confirm('Are you sure you want to restore this item?')) {
                 const form = document.createElement('form');
                 form.method = 'POST';
                 form.innerHTML = `
                     <input type="hidden" name="action" value="restore">
                     <input type="hidden" name="id" value="${itemId}">
                 `;
                 document.body.appendChild(form);
                 form.submit();
             }
         }
         
         // Form validation for size variants
         function validateSizeVariants() {
             const hasSizeVariants = document.getElementById('has_size_variants');
             const editHasSizeVariants = document.getElementById('edit_has_size_variants');
             
             // Check if we're in add or edit mode
             const isEditMode = editHasSizeVariants !== null;
             const checkbox = isEditMode ? editHasSizeVariants : hasSizeVariants;
             
             if (checkbox && checkbox.checked) {
                 const section = isEditMode ? 
                     document.getElementById('edit-size-variants-section') : 
                     document.getElementById('size-variants-section');
                 
                 const sizeInputs = section.querySelectorAll('input[name*="[name]"], input[name*="[price]"]');
                 let hasValidVariant = false;
                 
                 // Check if at least one complete size variant is filled
                 for (let i = 0; i < sizeInputs.length; i += 2) {
                     const nameInput = sizeInputs[i];
                     const priceInput = sizeInputs[i + 1];
                     
                     if (nameInput.value.trim() && priceInput.value.trim()) {
                         hasValidVariant = true;
                         break;
                     }
                 }
                 
                 if (!hasValidVariant) {
                     alert('Please add at least one size variant with both name and price.');
                     return false;
                 }
             }
             
             return true;
         }
         
         // Add form validation to both forms
         document.addEventListener('DOMContentLoaded', function() {
             const addForm = document.querySelector('#addModal form');
             const editForm = document.querySelector('#editModal form');
             
             if (addForm) {
                 addForm.addEventListener('submit', function(e) {
                     if (!validateSizeVariants()) {
                         e.preventDefault();
                     }
                 });
             }
             
             if (editForm) {
                 editForm.addEventListener('submit', function(e) {
                     if (!validateSizeVariants()) {
                         e.preventDefault();
                     }
                 });
             }
         });
         
         // Notification functions
         function showNotification(message, type) {
             const notification = document.createElement('div');
             notification.className = `notification ${type}`;
             notification.textContent = message;
             document.body.appendChild(notification);
             
             setTimeout(() => {
                 notification.classList.add('show');
             }, 100);
             
             setTimeout(() => {
                 notification.classList.remove('show');
                 setTimeout(() => {
                     document.body.removeChild(notification);
                 }, 300);
             }, 3000);
         }
         
         function showNotificationPopup(message, type, deleteType, orderCount) {
             const popup = document.createElement('div');
             popup.className = `notification-popup ${type}`;
             
             let icon = '✅';
             let title = 'Success!';
             
             if (type === 'error') {
                 icon = '❌';
                 title = 'Error!';
             } else if (deleteType === 'soft_delete') {
                 icon = '⚠️';
                 title = 'Item Soft Deleted';
             } else if (deleteType === 'hard_delete') {
                 icon = '🗑️';
                 title = 'Item Deleted';
             }
             
             popup.innerHTML = `
                 <div class="notification-popup-content">
                     <div class="notification-popup-icon">${icon}</div>
                     <h3>${title}</h3>
                     <p>${message}</p>
                     <button class="btn btn-primary" onclick="closeNotificationPopup(this)">OK</button>
                 </div>
             `;
             
             document.body.appendChild(popup);
             
             setTimeout(() => {
                 popup.classList.add('show');
             }, 100);
         }
         
         function closeNotificationPopup(button) {
             const popup = button.closest('.notification-popup');
             popup.classList.remove('show');
             setTimeout(() => {
                 document.body.removeChild(popup);
             }, 300);
         }
    </script>

<?php include 'includes/footer.php'; ?> 