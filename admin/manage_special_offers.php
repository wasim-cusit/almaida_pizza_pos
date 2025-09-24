<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$db = new Database();
$pdo = $db->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $title = $_POST['title'];
                $description = $_POST['description'];
                $discount_type = $_POST['discount_type'];
                $discount_value = $_POST['discount_value'];
                $start_date = $_POST['start_date'] ?: null;
                $end_date = $_POST['end_date'] ?: null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $query = "INSERT INTO special_offers (name, description, discount_type, discount_value, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$title, $description, $discount_type, $discount_value, $start_date, $end_date, $is_active]);
                
                header('Location: manage_special_offers.php?success=1');
                exit();
                break;
                
            case 'edit':
                $id = $_POST['id'];
                $title = $_POST['title'];
                $description = $_POST['description'];
                $discount_type = $_POST['discount_type'];
                $discount_value = $_POST['discount_value'];
                $start_date = $_POST['start_date'] ?: null;
                $end_date = $_POST['end_date'] ?: null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $query = "UPDATE special_offers SET name = ?, description = ?, discount_type = ?, discount_value = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$title, $description, $discount_type, $discount_value, $start_date, $end_date, $is_active, $id]);
                
                header('Location: manage_special_offers.php?success=2');
                exit();
                break;
                
            case 'delete':
                $id = $_POST['id'];
                
                // First check if the offer exists
                $checkQuery = "SELECT * FROM special_offers WHERE id = ?";
                $checkStmt = $pdo->prepare($checkQuery);
                $checkStmt->execute([$id]);
                $offer = $checkStmt->fetch();
                
                if ($offer) {
                    // Try to delete
                    $query = "DELETE FROM special_offers WHERE id = ?";
                    $stmt = $pdo->prepare($query);
                    $result = $stmt->execute([$id]);
                    
                    if ($result) {
                        header('Location: manage_special_offers.php?success=3');
                    } else {
                        header('Location: manage_special_offers.php?error=delete_failed');
                    }
                } else {
                    header('Location: manage_special_offers.php?error=offer_not_found');
                }
                exit();
                break;
        }
    }
}

// Fetch all special offers
$query = "SELECT * FROM special_offers ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for product selection
$query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name";
$stmt = $pdo->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch items for product selection
$query = "SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.is_available = 1 ORDER BY c.name, i.name";
$stmt = $pdo->prepare($query);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Special Offers - Admin Panel</title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .page-header h1 {
            margin: 0;
            font-size: 32px;
            font-weight: 700;
        }
        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .btn-add {
            background: #10b981;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-add:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            border: 2px solid #e5e7eb;
        }
        .stat-card i {
            font-size: 36px;
            color: #3b82f6;
            margin-bottom: 15px;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .stat-card .label {
            color: #6b7280;
            font-size: 14px;
            font-weight: 600;
        }
        .offers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .offer-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .offer-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981, #3b82f6);
        }
        .offer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        .offer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .offer-title {
            font-size: 20px;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
            flex: 1;
        }
        .offer-status {
            padding: 6px 15px;
            border-radius: 25px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        .offer-description {
            color: #6b7280;
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 15px;
        }
        .offer-pricing {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 2px solid #e2e8f0;
        }
        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .price-row:last-child {
            margin-bottom: 0;
            padding-top: 10px;
            border-top: 2px solid #e2e8f0;
        }
        .original-price {
            text-decoration: line-through;
            color: #9ca3af;
            font-size: 16px;
            font-weight: 500;
        }
        .discounted-price {
            color: #10b981;
            font-weight: 700;
            font-size: 24px;
        }
        .discount-badge {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
        }
        .offer-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .detail-item {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .detail-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .detail-value {
            font-size: 14px;
            color: #1f2937;
            font-weight: 600;
        }
        .offer-items {
            background: #fef3c7;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 2px solid #f59e0b;
        }
        .offer-items strong {
            color: #92400e;
            font-size: 14px;
        }
        .offer-items span {
            color: #78350f;
            font-size: 14px;
        }
        .offer-actions {
            display: flex;
            gap: 12px;
        }
        .btn-edit, .btn-delete {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            flex: 1;
            justify-content: center;
        }
        .btn-edit {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        .btn-edit:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        .btn-delete {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .btn-delete:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
        }
        .modal-content {
            background-color: white;
            margin: 3% auto;
            padding: 40px;
            border-radius: 20px;
            width: 90%;
            max-width: 700px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            border: 2px solid #e5e7eb;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #e5e7eb;
        }
        .modal-header h2 {
            margin: 0;
            color: #1f2937;
            font-size: 28px;
            font-weight: 700;
        }
        .close {
            color: #9ca3af;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .close:hover {
            color: #1f2937;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #374151;
            font-size: 16px;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #d1d5db;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f9fafb;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
        }
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #10b981;
        }
        .checkbox-group label {
            margin: 0;
            font-size: 16px;
            color: #374151;
        }
        .btn-submit {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }
        .success-message {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 2px solid #10b981;
            font-weight: 600;
            font-size: 16px;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #6b7280;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .empty-state i {
            font-size: 64px;
            margin-bottom: 25px;
            color: #d1d5db;
        }
        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 15px;
            color: #374151;
        }
        .empty-state p {
            font-size: 16px;
            margin-bottom: 30px;
        }
        .priority-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1><i class="fas fa-gift"></i> Special Offers Management</h1>
                    <p>Create and manage promotional offers to boost sales</p>
                </div>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <button class="btn-add" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Create New Offer
                    </button>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php 
                switch ($_GET['success']) {
                    case '1': echo 'Special offer created successfully!'; break;
                    case '2': echo 'Special offer updated successfully!'; break;
                    case '3': echo 'Special offer deleted successfully!'; break;
                }
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="success-message" style="background: linear-gradient(135deg, #fee2e2, #fecaca); color: #991b1b; border-color: #ef4444;">
                <i class="fas fa-exclamation-triangle"></i>
                <?php 
                switch ($_GET['error']) {
                    case 'delete_failed': echo 'Failed to delete special offer. Please try again.'; break;
                    case 'offer_not_found': echo 'Special offer not found. It may have been already deleted.'; break;
                    default: echo 'An error occurred. Please try again.'; break;
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <i class="fas fa-gift"></i>
                <div class="number"><?php echo count($offers); ?></div>
                <div class="label">Total Offers</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <div class="number"><?php echo count(array_filter($offers, function($o) { return $o['is_active'] == 1; })); ?></div>
                <div class="label">Active Offers</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar"></i>
                <div class="number"><?php echo count(array_filter($offers, function($o) { 
                    return $o['is_active'] == 1 && 
                           (!$o['start_date'] || $o['start_date'] <= date('Y-m-d')) && 
                           (!$o['end_date'] || $o['end_date'] >= date('Y-m-d')); 
                })); ?></div>
                <div class="label">Currently Active</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-percentage"></i>
                <div class="number"><?php 
                    $avgDiscount = array_reduce($offers, function($carry, $item) {
                        if ($item['discount_type'] === 'percentage') {
                            return $carry + $item['discount_value'];
                        }
                        return $carry;
                    }, 0);
                    $percentageOffers = array_filter($offers, function($item) {
                        return $item['discount_type'] === 'percentage';
                    });
                    echo count($percentageOffers) > 0 ? round($avgDiscount / count($percentageOffers), 1) : 0;
                ?>%</div>
                <div class="label">Avg Discount</div>
            </div>
        </div>

        <?php if (empty($offers)): ?>
            <div class="empty-state">
                <i class="fas fa-gift"></i>
                <h3>No Special Offers Found</h3>
                <p>Create your first special offer to attract customers and boost sales!</p>
                <button class="btn-add" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Create First Offer
                </button>
            </div>
        <?php else: ?>
            <div class="offers-grid">
                <?php foreach ($offers as $offer): ?>
                    <div class="offer-card <?php echo $offer['is_active'] ? 'active' : 'inactive'; ?>">
                            <div class="offer-header">
                                <h3 class="offer-title"><?php echo htmlspecialchars($offer['name']); ?></h3>
                                <span class="offer-status <?php echo $offer['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $offer['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        
                        <p class="offer-description"><?php echo htmlspecialchars($offer['description']); ?></p>
                        
                        <div class="offer-pricing">
                            <div class="price-row">
                                <span class="discount-badge"><?php echo $offer['discount_type'] === 'percentage' ? '-' . number_format($offer['discount_value'], 1) . '%' : '-PKR ' . number_format($offer['discount_value'], 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="offer-details">
                            <div class="detail-item">
                                <div class="detail-label">Start Date</div>
                                <div class="detail-value"><?php echo $offer['start_date'] ? date('M d, Y', strtotime($offer['start_date'])) : 'No Start Date'; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">End Date</div>
                                <div class="detail-value"><?php echo $offer['end_date'] ? date('M d, Y', strtotime($offer['end_date'])) : 'No End Date'; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Created On</div>
                                <div class="detail-value"><?php echo date('M d, Y', strtotime($offer['created_at'])); ?></div>
                            </div>
                        </div>
                        
                        <div class="offer-actions">
                            <button class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($offer)); ?>)">
                                <i class="fas fa-edit"></i> Edit Offer
                            </button>
                            <button class="btn-delete" onclick="deleteOffer(<?php echo $offer['id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add/Edit Modal -->
    <div id="offerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Create Special Offer</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <form id="offerForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="offerId">
                
                <div class="form-group">
                    <label for="title">Offer Name *</label>
                    <input type="text" id="title" name="title" placeholder="e.g., Pizza Combo Special" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Offer Description *</label>
                    <textarea id="description" name="description" rows="3" placeholder="Describe what this offer includes..." required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="discount_type">Discount Type *</label>
                        <select id="discount_type" name="discount_type" required onchange="toggleDiscountInput()">
                            <option value="">Select Type</option>
                            <option value="percentage">Percentage Discount</option>
                            <option value="fixed_amount">Fixed Amount Discount</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="discount_value">Discount Value *</label>
                        <input type="number" id="discount_value" name="discount_value" step="0.01" min="0" placeholder="0" required>
                        <small id="discount_help" style="color: #6b7280; font-size: 12px;"></small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date">
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" checked>
                        <label for="is_active">Active Offer</label>
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 30px;">
                    <button type="button" class="btn-submit" style="background: #6b7280; margin-right: 15px;" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Save Offer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            console.log('openAddModal function called');
            document.getElementById('modalTitle').textContent = 'Create Special Offer';
            document.getElementById('formAction').value = 'add';
            document.getElementById('offerForm').reset();
            document.getElementById('offerId').value = '';
            document.getElementById('offerModal').style.display = 'block';
            console.log('Modal display set to block');
            toggleDiscountInput();
        }
        
        function openEditModal(offer) {
            document.getElementById('modalTitle').textContent = 'Edit Special Offer';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('offerId').value = offer.id;
            document.getElementById('title').value = offer.name; // Changed from 'title' to 'name'
            document.getElementById('description').value = offer.description;
            document.getElementById('discount_type').value = offer.discount_type;
            document.getElementById('discount_value').value = offer.discount_value;
            document.getElementById('start_date').value = offer.start_date;
            document.getElementById('end_date').value = offer.end_date;
            document.getElementById('is_active').checked = offer.is_active == 1;
            
            document.getElementById('offerModal').style.display = 'block';
            toggleDiscountInput();
        }
        
        function closeModal() {
            document.getElementById('offerModal').style.display = 'none';
        }
        
        function deleteOffer(id) {
            if (confirm('Are you sure you want to delete this special offer? This action cannot be undone.')) {
                console.log('Attempting to delete offer ID:', id);
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                
                // Add a small delay to ensure form is properly added
                setTimeout(() => {
                    form.submit();
                }, 100);
            }
        }
        
        function toggleDiscountInput() {
            console.log('toggleDiscountInput function called');
            const discountType = document.getElementById('discount_type').value;
            const discountValue = document.getElementById('discount_value');
            const discountHelp = document.getElementById('discount_help');
            
            if (discountType === 'percentage') {
                discountValue.placeholder = 'e.g., 15 for 15%';
                discountHelp.textContent = 'Enter percentage (e.g., 15 for 15% discount)';
            } else if (discountType === 'fixed_amount') {
                discountValue.placeholder = 'e.g., 100 for PKR 100 off';
                discountHelp.textContent = 'Enter amount in PKR (e.g., 100 for PKR 100 off)';
            } else {
                discountValue.placeholder = '0';
                discountHelp.textContent = '';
            }
            console.log('toggleDiscountInput completed');
        }
        

        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('offerModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html> 