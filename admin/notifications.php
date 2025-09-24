<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_notifications':
            $user_id = $_SESSION['user_id'];
            $branch_id = $_SESSION['branch_id'] ?? null;
            
            // Get notifications for user or their branch
            $query = "SELECT n.*, b.name as branch_name 
                     FROM notifications n 
                     LEFT JOIN branches b ON n.branch_id = b.id 
                     WHERE (n.user_id = ? OR n.branch_id = ? OR n.user_id IS NULL) 
                     ORDER BY n.created_at DESC 
                     LIMIT 50";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id, $branch_id]);
            $notifications = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'notifications' => $notifications]);
            exit();
            
        case 'mark_read':
            $notification_id = (int)$_POST['notification_id'];
            
            $query = "UPDATE notifications SET is_read = 1 WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$notification_id]);
            
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            exit();
            
        case 'mark_all_read':
            $user_id = $_SESSION['user_id'];
            $branch_id = $_SESSION['branch_id'] ?? null;
            
            $query = "UPDATE notifications SET is_read = 1 
                     WHERE (user_id = ? OR n.branch_id = ? OR user_id IS NULL) AND is_read = 0";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id, $branch_id]);
            
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
            exit();
            
        case 'get_unread_count':
            $user_id = $_SESSION['user_id'];
            $branch_id = $_SESSION['branch_id'] ?? null;
            
            $query = "SELECT COUNT(*) as unread_count 
                     FROM notifications 
                     WHERE (user_id = ? OR branch_id = ? OR user_id IS NULL) AND is_read = 0";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id, $branch_id]);
            $result = $stmt->fetch();
            
            echo json_encode(['success' => true, 'unread_count' => $result['unread_count']]);
            exit();
    }
}

// Get user's branch for display
$user_branch_id = null;
if (isset($_SESSION['user_id'])) {
    $query = "SELECT branch_id FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $user_branch_id = $user ? $user['branch_id'] : null;
}

// Get notifications for display
$query = "SELECT n.*, b.name as branch_name 
         FROM notifications n 
         LEFT JOIN branches b ON n.branch_id = b.id 
         WHERE (n.user_id = ? OR n.branch_id = ? OR n.user_id IS NULL) 
         ORDER BY n.created_at DESC 
         LIMIT 100";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id'], $user_branch_id]);
$notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Almaida POS</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
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
        
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #20bf55; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        
        .btn-super:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .notification-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .notification-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .notification-item.unread {
            border-left: 4px solid #667eea;
            background: #f8fafc;
        }
        
        .notification-item.urgent {
            border-left: 4px solid #dc3545;
            background: #fef2f2;
        }
        
        .notification-item.high {
            border-left: 4px solid #f59e0b;
            background: #fffbeb;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 16px;
            color: #1f2937;
            margin: 0;
        }
        
        .notification-time {
            font-size: 12px;
            color: #6b7280;
        }
        
        .notification-message {
            color: #4b5563;
            margin-bottom: 10px;
        }
        
        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #6b7280;
        }
        
        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .priority-low { background: #e5e7eb; color: #374151; }
        .priority-medium { background: #dbeafe; color: #1e40af; }
        .priority-high { background: #fef3c7; color: #92400e; }
        .priority-urgent { background: #fee2e2; color: #991b1b; }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #d1d5db;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-tab {
            padding: 8px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-tab.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .filter-tab:hover {
            background: #f3f4f6;
        }
        
        .filter-tab.active:hover {
            background: #667eea;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <p>Stay updated with system alerts and important messages</p>
            <div style="margin-top: 20px;">
                <a href="index.php" class="btn-super btn-info">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button class="btn-super btn-success" onclick="markAllRead()">
                    <i class="fas fa-check"></i> Mark All Read
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> All Notifications</h2>
                <div class="filter-tabs">
                    <div class="filter-tab active" onclick="filterNotifications('all')">All</div>
                    <div class="filter-tab" onclick="filterNotifications('unread')">Unread</div>
                    <div class="filter-tab" onclick="filterNotifications('urgent')">Urgent</div>
                    <div class="filter-tab" onclick="filterNotifications('stock')">Stock</div>
                </div>
            </div>

            <div id="notifications-container">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Notifications</h3>
                        <p>You're all caught up! No new notifications at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo $notification['priority']; ?>" 
                             data-type="<?php echo $notification['notification_type']; ?>"
                             onclick="markAsRead(<?php echo $notification['id']; ?>)">
                            <div class="notification-header">
                                <h4 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                <div>
                                    <span class="priority-badge priority-<?php echo $notification['priority']; ?>">
                                        <?php echo $notification['priority']; ?>
                                    </span>
                                    <div class="notification-time">
                                        <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="notification-message">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <div class="notification-meta">
                                <div>
                                    <?php if ($notification['branch_name']): ?>
                                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($notification['branch_name']); ?>
                                    <?php endif; ?>
                                    <span style="margin-left: 15px;">
                                        <i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $notification['notification_type'])); ?>
                                    </span>
                                </div>
                                <div>
                                    <?php if (!$notification['is_read']): ?>
                                        <span style="color: #667eea; font-weight: 600;">New</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($notification['related_id'] && $notification['related_type']): ?>
                                <div class="notification-actions">
                                    <button class="btn-super btn-small btn-primary" onclick="viewRelated(<?php echo $notification['related_id']; ?>, '<?php echo $notification['related_type']; ?>')">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Mark notification as read
        function markAsRead(notificationId) {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_read&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove unread styling
                    const notification = document.querySelector(`[onclick*="${notificationId}"]`);
                    if (notification) {
                        notification.classList.remove('unread');
                    }
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            });
        }

        // Mark all notifications as read
        function markAllRead() {
            if (confirm('Are you sure you want to mark all notifications as read?')) {
                fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=mark_all_read'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove unread styling from all notifications
                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                        });
                        alert('All notifications marked as read');
                    }
                })
                .catch(error => {
                    console.error('Error marking all notifications as read:', error);
                    alert('Error marking notifications as read');
                });
            }
        }

        // Filter notifications
        function filterNotifications(filter) {
            // Update active tab
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');

            // Filter notifications
            const notifications = document.querySelectorAll('.notification-item');
            notifications.forEach(notification => {
                let show = true;
                
                switch(filter) {
                    case 'unread':
                        show = notification.classList.contains('unread');
                        break;
                    case 'urgent':
                        show = notification.classList.contains('urgent');
                        break;
                    case 'stock':
                        show = notification.dataset.type.includes('stock');
                        break;
                    case 'all':
                    default:
                        show = true;
                        break;
                }
                
                notification.style.display = show ? 'block' : 'none';
            });
        }

        // View related item
        function viewRelated(relatedId, relatedType) {
            switch(relatedType) {
                case 'purchase':
                    window.location.href = `stock_purchases.php#purchase-${relatedId}`;
                    break;
                case 'distribution':
                    window.location.href = `stock_distributions.php#distribution-${relatedId}`;
                    break;
                case 'order':
                    window.location.href = `view_orders.php#order-${relatedId}`;
                    break;
                case 'item':
                    window.location.href = `super_admin.php#item-${relatedId}`;
                    break;
                default:
                    alert('Related item view not implemented yet');
                    break;
            }
        }

        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_notifications'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update notification count in header if exists
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        const unreadCount = data.notifications.filter(n => !n.is_read).length;
                        badge.textContent = unreadCount;
                        badge.style.display = unreadCount > 0 ? 'inline' : 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error refreshing notifications:', error);
            });
        }, 30000);
    </script>
</body>
</html>
