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
        case 'get_notifications':
            $query = "SELECT n.*, u.name as user_name, b.name as branch_name 
                     FROM notifications n 
                     LEFT JOIN users u ON n.user_id = u.id 
                     LEFT JOIN branches b ON n.branch_id = b.id 
                     ORDER BY n.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $notifications = $stmt->fetchAll();
            echo json_encode(['success' => true, 'notifications' => $notifications]);
            exit();
            
        case 'mark_as_read':
            $id = (int)$_POST['id'];
            $query = "UPDATE notifications SET is_read = 1 WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
            exit();
            
        case 'mark_all_read':
            $query = "UPDATE notifications SET is_read = 1 WHERE is_read = 0";
            $stmt = $db->prepare($query);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
            exit();
            
        case 'delete_notification':
            $id = (int)$_POST['id'];
            $query = "DELETE FROM notifications WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Notification deleted']);
            exit();
    }
}

// Get statistics
$query = "SELECT COUNT(*) as total_notifications FROM notifications";
$stmt = $db->prepare($query);
$stmt->execute();
$total_notifications = $stmt->fetch()['total_notifications'];

$query = "SELECT COUNT(*) as unread_notifications FROM notifications WHERE is_read = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$unread_notifications = $stmt->fetch()['unread_notifications'];

$query = "SELECT COUNT(*) as today_notifications FROM notifications WHERE DATE(created_at) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute();
$today_notifications = $stmt->fetch()['today_notifications'];

$query = "SELECT COUNT(*) as high_priority FROM notifications WHERE priority = 'high' AND is_read = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$high_priority = $stmt->fetch()['high_priority'];

$page_title = "Notifications";
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-bell"></i> Notifications
    </h1>
    <p class="page-subtitle">System notifications and alerts</p>
</div>

<!-- Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_notifications; ?></div>
        <div>Total Notifications</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $unread_notifications; ?></div>
        <div>Unread</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #3b82f6, #3b82f6); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $today_notifications; ?></div>
        <div>Today</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f59e0b, #f59e0b); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $high_priority; ?></div>
        <div>High Priority</div>
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
        <button class="btn btn-success" onclick="markAllAsRead()">
            <i class="fas fa-check-double"></i> Mark All as Read
        </button>
        <button class="btn btn-info" onclick="loadNotifications()">
            <i class="fas fa-refresh"></i> Refresh List
        </button>
        <button class="btn btn-warning" onclick="clearOldNotifications()">
            <i class="fas fa-trash"></i> Clear Old
        </button>
        <button class="btn btn-primary" onclick="showSettings()">
            <i class="fas fa-cog"></i> Settings
        </button>
    </div>
</div>

<!-- Notifications List -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Notifications
        </h2>
        <div style="display: flex; gap: 10px;">
            <select class="form-control" style="width: auto;" id="priority-filter">
                <option value="">All Priorities</option>
                <option value="high">High Priority</option>
                <option value="medium">Medium Priority</option>
                <option value="low">Low Priority</option>
            </select>
            <select class="form-control" style="width: auto;" id="status-filter">
                <option value="">All Status</option>
                <option value="0">Unread</option>
                <option value="1">Read</option>
            </select>
        </div>
    </div>
    <div id="notifications-container">
        <div style="text-align: center; color: #666; padding: 40px;">
            <i class="fas fa-spinner fa-spin"></i> Loading notifications...
        </div>
    </div>
</div>

<script>
    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadNotifications();
    });

    // Load notifications
    function loadNotifications() {
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
                displayNotifications(data.notifications);
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
        });
    }

    // Display notifications
    function displayNotifications(notifications) {
        const container = document.getElementById('notifications-container');
        
        if (notifications.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No notifications found</p>';
            return;
        }

        let html = '';
        notifications.forEach(notification => {
            const priorityClass = getPriorityClass(notification.priority);
            const readClass = notification.is_read ? 'read' : 'unread';
            const timeAgo = getTimeAgo(notification.created_at);
            
            html += `
                <div class="notification-item ${readClass}" style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid var(--light-border); transition: all 0.3s ease; ${notification.is_read ? 'opacity: 0.7;' : 'background: #f8fafc;'}">
                    <div style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 1.2em; color: white; background: ${priorityClass.bg};">
                        <i class="fas fa-${getNotificationIcon(notification.notification_type)}"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 5px;">
                            <h4 style="margin: 0; font-weight: 600; color: var(--light-text);">${notification.title}</h4>
                            <div style="display: flex; gap: 5px;">
                                <span style="padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; background: ${priorityClass.bg}; color: white;">${notification.priority.toUpperCase()}</span>
                                ${!notification.is_read ? '<span style="width: 8px; height: 8px; background: #ef4444; border-radius: 50%; display: inline-block;"></span>' : ''}
                            </div>
                        </div>
                        <p style="margin: 0 0 5px 0; color: var(--light-text); opacity: 0.8;">${notification.message}</p>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <small style="color: var(--light-text); opacity: 0.6;">${timeAgo}</small>
                            <div style="display: flex; gap: 5px;">
                                ${!notification.is_read ? 
                                    `<button class="btn btn-sm btn-success" onclick="markAsRead(${notification.id})" style="padding: 4px 8px; font-size: 12px;">
                                        <i class="fas fa-check"></i> Mark Read
                                    </button>` : ''
                                }
                                <button class="btn btn-sm btn-danger" onclick="deleteNotification(${notification.id})" style="padding: 4px 8px; font-size: 12px;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Get priority class
    function getPriorityClass(priority) {
        switch(priority) {
            case 'high': return { bg: 'linear-gradient(135deg, #ef4444, #dc2626)' };
            case 'medium': return { bg: 'linear-gradient(135deg, #f59e0b, #f59e0b)' };
            case 'low': return { bg: 'linear-gradient(135deg, #3b82f6, #3b82f6)' };
            default: return { bg: 'linear-gradient(135deg, #6c757d, #6c757d)' };
        }
    }

    // Get notification icon
    function getNotificationIcon(type) {
        switch(type) {
            case 'stock_alert': return 'exclamation-triangle';
            case 'order': return 'shopping-cart';
            case 'user': return 'user';
            case 'system': return 'cog';
            default: return 'bell';
        }
    }

    // Get time ago
    function getTimeAgo(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
        if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
        return Math.floor(diffInSeconds / 86400) + 'd ago';
    }

    // Mark as read
    function markAsRead(notificationId) {
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_as_read&id=${notificationId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
            alert('Error marking notification as read');
        });
    }

    // Mark all as read
    function markAllAsRead() {
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
                    alert('All notifications marked as read');
                    loadNotifications();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error marking all notifications as read:', error);
                alert('Error marking all notifications as read');
            });
        }
    }

    // Delete notification
    function deleteNotification(notificationId) {
        if (confirm('Are you sure you want to delete this notification?')) {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_notification&id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error deleting notification:', error);
                alert('Error deleting notification');
            });
        }
    }

    // Placeholder functions
    function clearOldNotifications() {
        if (confirm('Are you sure you want to clear old notifications?')) {
            alert('Clear old notifications functionality - Coming soon!');
        }
    }

    function showSettings() {
        alert('Notification settings - Coming soon!');
    }
</script>

<?php include 'includes/footer.php'; ?>
