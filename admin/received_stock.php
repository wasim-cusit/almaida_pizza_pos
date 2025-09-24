<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get current user's branch
$branch_id = $_SESSION['branch_id'] ?? null;
if (!$branch_id) {
    die('No branch assigned to your account. Please contact super admin.');
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_branch_distributions':
            try {
                $query = "SELECT sd.*, u.name as requested_by_name, u2.name as approved_by_name,
                         COUNT(sdi.id) as item_count
                         FROM stock_distributions sd
                         LEFT JOIN users u ON sd.requested_by = u.id
                         LEFT JOIN users u2 ON sd.approved_by = u2.id
                         LEFT JOIN stock_distribution_items sdi ON sd.id = sdi.distribution_id
                         WHERE sd.to_branch_id = ?
                         GROUP BY sd.id
                         ORDER BY sd.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id]);
                $distributions = $stmt->fetchAll();
                echo json_encode(['success' => true, 'distributions' => $distributions]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading distributions: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_distribution_items':
            $distribution_id = (int)$_POST['distribution_id'];
            try {
                $query = "SELECT sdi.*, i.name as item_name, c.name as category_name
                         FROM stock_distribution_items sdi
                         JOIN items i ON sdi.item_id = i.id
                         LEFT JOIN categories c ON i.category_id = c.id
                         WHERE sdi.distribution_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$distribution_id]);
                $items = $stmt->fetchAll();
                echo json_encode(['success' => true, 'items' => $items]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading items: ' . $e->getMessage()]);
            }
            exit();
            
        case 'receive_goods':
            $distribution_id = (int)$_POST['distribution_id'];
            try {
                $db->beginTransaction();
                
                // Update distribution status
                $query = "UPDATE stock_distributions SET status = 'received', received_by = ?, received_at = NOW() WHERE id = ? AND to_branch_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_SESSION['user_id'], $distribution_id, $branch_id]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Distribution not found or not assigned to your branch');
                }
                
                // Get distribution items
                $query = "SELECT item_id, received_quantity FROM stock_distribution_items WHERE distribution_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$distribution_id]);
                $items = $stmt->fetchAll();
                
                // Update branch stock
                foreach ($items as $item) {
                    if ($item['received_quantity'] > 0) {
                        // Check if branch item exists
                        $query = "SELECT id, current_stock FROM branch_items WHERE branch_id = ? AND item_id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$branch_id, $item['item_id']]);
                        $existing = $stmt->fetch();
                        
                        if ($existing) {
                            // Update existing stock
                            $new_stock = $existing['current_stock'] + $item['received_quantity'];
                            $query = "UPDATE branch_items SET current_stock = ? WHERE branch_id = ? AND item_id = ?";
                            $stmt = $db->prepare($query);
                            $stmt->execute([$new_stock, $branch_id, $item['item_id']]);
                        } else {
                            // Create new branch item record
                            $query = "INSERT INTO branch_items (branch_id, item_id, current_stock) VALUES (?, ?, ?)";
                            $stmt = $db->prepare($query);
                            $stmt->execute([$branch_id, $item['item_id'], $item['received_quantity']]);
                        }
                        
                        // Record stock movement
                        $query = "INSERT INTO stock_movements (branch_id, item_id, movement_type, quantity, previous_stock, new_stock, reference_type, notes, user_id) 
                                 VALUES (?, ?, 'in', ?, 0, ?, 'distribution_received', 'Stock received from distribution', ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$branch_id, $item['item_id'], $item['received_quantity'], $item['received_quantity'], $_SESSION['user_id']]);
                    }
                }
                
                // Create notification for super admin
                createNotification(
                    null, // Super admin notification
                    null, // No specific branch
                    'goods_received',
                    'Goods Received',
                    "Distribution has been received by branch admin.",
                    'medium',
                    $distribution_id,
                    'distribution'
                );
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Goods marked as received successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error receiving goods: ' . $e->getMessage()]);
            }
            exit();
            
        case 'update_received_quantity':
            $distribution_id = (int)$_POST['distribution_id'];
            $item_id = (int)$_POST['item_id'];
            $received_quantity = (int)$_POST['received_quantity'];
            
            try {
                // Validate that distribution belongs to this branch
                $query = "SELECT id FROM stock_distributions WHERE id = ? AND to_branch_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$distribution_id, $branch_id]);
                if (!$stmt->fetch()) {
                    throw new Exception('Distribution not found or not assigned to your branch');
                }
                
                // Update received quantity
                $query = "UPDATE stock_distribution_items SET received_quantity = ? WHERE distribution_id = ? AND item_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$received_quantity, $distribution_id, $item_id]);
                
                echo json_encode(['success' => true, 'message' => 'Received quantity updated']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating quantity: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Function to create notifications
function createNotification($user_id, $branch_id, $type, $title, $message, $priority, $related_id, $related_type) {
    global $db;
    
    try {
        $query = "INSERT INTO notifications (user_id, branch_id, notification_type, title, message, priority, related_id, related_type) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id, $branch_id, $type, $title, $message, $priority, $related_id, $related_type]);
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Notification creation failed: " . $e->getMessage());
    }
}

// Get statistics for this branch
$stats = [];

// Total distributions for this branch
$query = "SELECT COUNT(*) as total FROM stock_distributions WHERE to_branch_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$stats['total_distributions'] = $stmt->fetch()['total'] ?? 0;

// Pending distributions
$query = "SELECT COUNT(*) as total FROM stock_distributions WHERE to_branch_id = ? AND status = 'approved'";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$stats['pending_receipt'] = $stmt->fetch()['total'] ?? 0;

// Received distributions
$query = "SELECT COUNT(*) as total FROM stock_distributions WHERE to_branch_id = ? AND status = 'received'";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$stats['received_distributions'] = $stmt->fetch()['total'] ?? 0;

// Total items received
$query = "SELECT SUM(sdi.received_quantity) as total 
          FROM stock_distribution_items sdi 
          JOIN stock_distributions sd ON sdi.distribution_id = sd.id 
          WHERE sd.to_branch_id = ? AND sd.status = 'received'";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$stats['total_items_received'] = $stmt->fetch()['total'] ?? 0;

// Get branch name
$query = "SELECT name FROM branches WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$branch_name = $stmt->fetch()['name'] ?? 'Unknown Branch';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Received Stock - <?php echo $branch_name; ?></title>
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
        
        .admin-header {
            background: linear-gradient(135deg, #20bf55 0%, #01baef 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .admin-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .btn-super {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary { background: #20bf55; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        
        .btn-super:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .distribution-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .distribution-table th,
        .distribution-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .distribution-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .distribution-table tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-dispatched { background: #dbeafe; color: #1e40af; }
        .status-received { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
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
            margin: 2% auto;
            padding: 30px;
            border-radius: 15px;
            width: 95%;
            max-width: 1000px;
            position: relative;
            max-height: 90vh;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #20bf55;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: center;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .notification {
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
        }
        
        .notification-success { background: #10b981; }
        .notification-error { background: #ef4444; }
        .notification-warning { background: #f59e0b; }
        .notification-info { background: #3b82f6; }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-truck-loading"></i> Received Stock Management</h1>
            <p>Manage stock distributions for <?php echo $branch_name; ?></p>
            <div style="margin-top: 20px;">
                <a href="index.php" class="btn-super btn-info">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #20bf55;"><?php echo $stats['total_distributions']; ?></div>
                <div class="stat-label">Total Distributions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #ffc107;"><?php echo $stats['pending_receipt']; ?></div>
                <div class="stat-label">Pending Receipt</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #28a745;"><?php echo $stats['received_distributions']; ?></div>
                <div class="stat-label">Received</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #17a2b8;"><?php echo $stats['total_items_received']; ?></div>
                <div class="stat-label">Items Received</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> Stock Distributions</h2>
                <button class="btn-super btn-primary" onclick="loadDistributions()">
                    <i class="fas fa-refresh"></i> Refresh
                </button>
            </div>

            <div id="distributions-container">
                <p style="text-align: center; color: #666; padding: 40px;">
                    Loading distributions...
                </p>
            </div>
        </div>
    </div>

    <!-- Distribution Details Modal -->
    <div id="distribution-details-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('distribution-details-modal')">&times;</span>
            <h3>Distribution Details</h3>
            
            <div id="distribution-info" style="margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 8px;">
                <!-- Distribution info will be loaded here -->
            </div>
            
            <h4>Items in Distribution</h4>
            <div id="items-container">
                <p style="text-align: center; color: #666;">Loading items...</p>
            </div>
            
            <div id="receive-actions" style="text-align: right; margin-top: 20px; display: none;">
                <button type="button" class="btn-super btn-secondary" onclick="closeModal('distribution-details-modal')">Close</button>
                <button type="button" class="btn-super btn-success" onclick="receiveGoods()">
                    <i class="fas fa-check"></i> Mark as Received
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentDistributionId = null;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadDistributions();
        });

        // Load distributions
        function loadDistributions() {
            const container = document.getElementById('distributions-container');
            container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">Loading distributions...</p>';
            
            fetch('received_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_branch_distributions'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayDistributions(data.distributions);
                } else {
                    container.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading distributions: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading distributions:', error);
                container.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading distributions. Please try again.</div>';
            });
        }

        // Display distributions
        function displayDistributions(distributions) {
            const container = document.getElementById('distributions-container');
            
            if (distributions.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No distributions found for your branch</p>';
                return;
            }

            let html = `
                <table class="distribution-table">
                    <thead>
                        <tr>
                            <th>Distribution #</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Requested By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            distributions.forEach(distribution => {
                const statusClass = `status-${distribution.status}`;
                const statusText = distribution.status.charAt(0).toUpperCase() + distribution.status.slice(1);
                
                html += `
                    <tr>
                        <td><strong>${distribution.distribution_number}</strong></td>
                        <td>${new Date(distribution.distribution_date).toLocaleDateString()}</td>
                        <td>${distribution.item_count}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${distribution.requested_by_name}</td>
                        <td>
                            <button class="btn-super btn-info" onclick="viewDistribution(${distribution.id})">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            ${distribution.status === 'approved' || distribution.status === 'dispatched' ? 
                                `<button class="btn-super btn-success" onclick="quickReceive(${distribution.id})">
                                    <i class="fas fa-check"></i> Receive
                                </button>` : ''
                            }
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // View distribution details
        function viewDistribution(distributionId) {
            currentDistributionId = distributionId;
            document.getElementById('distribution-details-modal').style.display = 'block';
            
            // Load distribution info
            fetch('received_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_distribution_items&distribution_id=${distributionId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayDistributionDetails(data.items);
                } else {
                    showNotification('Error loading distribution details: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error loading distribution details:', error);
                showNotification('Error loading distribution details', 'error');
            });
        }

        // Display distribution details
        function displayDistributionDetails(items) {
            const container = document.getElementById('items-container');
            
            if (items.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666;">No items found</p>';
                return;
            }

            let html = '';
            let canReceive = false;
            
            items.forEach(item => {
                const isReceived = item.received_quantity > 0;
                if (item.requested_quantity > 0 && !isReceived) {
                    canReceive = true;
                }
                
                html += `
                    <div class="item-row">
                        <div>
                            <strong>${item.item_name}</strong>
                            <br><small>Category: ${item.category_name || 'Uncategorized'}</small>
                        </div>
                        <div>
                            <label>Requested:</label>
                            <input type="number" value="${item.requested_quantity}" readonly style="background: #f8f9fa;">
                        </div>
                        <div>
                            <label>Received:</label>
                            <input type="number" id="received_${item.item_id}" value="${item.received_quantity}" 
                                   min="0" max="${item.requested_quantity}" 
                                   onchange="updateReceivedQuantity(${item.item_id})">
                        </div>
                        <div>
                            <label>Unit Cost:</label>
                            <input type="number" value="${item.unit_cost}" readonly style="background: #f8f9fa;">
                        </div>
                        <div>
                            ${isReceived ? 
                                '<span class="status-badge status-received">Received</span>' :
                                '<span class="status-badge status-pending">Pending</span>'
                            }
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            
            // Show/hide receive actions
            const receiveActions = document.getElementById('receive-actions');
            receiveActions.style.display = canReceive ? 'block' : 'none';
        }

        // Update received quantity
        function updateReceivedQuantity(itemId) {
            const receivedQuantity = document.getElementById(`received_${itemId}`).value;
            
            fetch('received_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_received_quantity&distribution_id=${currentDistributionId}&item_id=${itemId}&received_quantity=${receivedQuantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Received quantity updated', 'success');
                } else {
                    showNotification('Error updating quantity: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error updating quantity:', error);
                showNotification('Error updating quantity', 'error');
            });
        }

        // Quick receive goods
        function quickReceive(distributionId) {
            if (confirm('Are you sure you want to mark all items in this distribution as received?')) {
                receiveGoods(distributionId);
            }
        }

        // Receive goods
        function receiveGoods(distributionId = null) {
            const distId = distributionId || currentDistributionId;
            
            fetch('received_stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=receive_goods&distribution_id=${distId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Goods marked as received successfully', 'success');
                    closeModal('distribution-details-modal');
                    loadDistributions();
                } else {
                    showNotification('Error receiving goods: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error receiving goods:', error);
                showNotification('Error receiving goods', 'error');
            });
        }

        // Modal functions
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
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

        // Show notification
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => notification.remove());
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                ${message}
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
    </script>
</body>
</html>
