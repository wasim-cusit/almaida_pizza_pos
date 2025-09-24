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
            
        case 'clear_old_notifications':
            $days = (int)($_POST['days'] ?? 30);
            $query = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $db->prepare($query);
            $stmt->execute([$days]);
            $deleted = $stmt->rowCount();
            echo json_encode(['success' => true, 'message' => "Deleted {$deleted} old notifications"]);
            exit();
            
        case 'get_notification_settings':
            echo json_encode(['success' => true, 'settings' => [
                'push_notifications' => false,
                'auto_clear_days' => 30,
                'priority_filter' => 'all',
                'display_limit' => 100
            ]]);
            exit();
            
        case 'update_notification_settings':
            $push_notifications = (int)($_POST['push_notifications'] ?? 0);
            $auto_clear_days = (int)($_POST['auto_clear_days'] ?? 30);
            $priority_filter = $_POST['priority_filter'] ?? 'all';
            $display_limit = (int)($_POST['display_limit'] ?? 100);
            
            // In a real application, you would save these to a settings table
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
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

$query = "SELECT COUNT(*) as high_priority_count FROM notifications WHERE priority = 'high' AND is_read = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$high_priority = $stmt->fetch()['high_priority_count'];

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
        <div style="display: flex; gap: 10px; align-items: center;">
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
            <button class="btn btn-secondary" onclick="clearFilters()" style="padding: 8px 12px; font-size: 12px;">
                <i class="fas fa-times"></i> Clear Filters
            </button>
        </div>
    </div>
    <div id="notifications-container">
        <div style="text-align: center; color: #666; padding: 40px;">
            <i class="fas fa-spinner fa-spin"></i> Loading notifications...
        </div>
    </div>
</div>

<script>
    // Store all notifications for filtering
    let allNotifications = [];

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadNotifications();
        
        // Add event listeners for filters
        document.getElementById('priority-filter').addEventListener('change', filterNotifications);
        document.getElementById('status-filter').addEventListener('change', filterNotifications);
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
                allNotifications = data.notifications;
                filterNotifications();
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            const container = document.getElementById('notifications-container');
            container.innerHTML = `
                <div style="text-align: center; color: #dc3545; padding: 40px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3em; margin-bottom: 20px; opacity: 0.7;"></i>
                    <h3 style="margin-bottom: 10px;">Error Loading Notifications</h3>
                    <p style="margin-bottom: 20px; color: #666;">Failed to load notifications. Please try again.</p>
                    <button class="btn btn-primary" onclick="loadNotifications()">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                </div>
            `;
            showNotification('Failed to load notifications', 'error');
        });
    }

    // Filter notifications
    function filterNotifications() {
        const priorityFilter = document.getElementById('priority-filter').value;
        const statusFilter = document.getElementById('status-filter').value;
        
        let filteredNotifications = allNotifications.filter(notification => {
            const matchesPriority = !priorityFilter || notification.priority === priorityFilter;
            const matchesStatus = statusFilter === '' || notification.is_read == statusFilter;
            return matchesPriority && matchesStatus;
        });
        
        displayNotifications(filteredNotifications);
    }

    // Clear all filters
    function clearFilters() {
        document.getElementById('priority-filter').value = '';
        document.getElementById('status-filter').value = '';
        filterNotifications();
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
                showNotification('Notification marked as read', 'success');
                loadNotifications();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
            showNotification('Error marking notification as read', 'error');
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
                showNotification('All notifications marked as read', 'success');
                    loadNotifications();
                } else {
                showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error marking all notifications as read:', error);
            showNotification('Error marking all notifications as read', 'error');
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
                showNotification('Notification deleted successfully', 'success');
                loadNotifications();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error deleting notification:', error);
            showNotification('Error deleting notification', 'error');
        });
        }
    }

    // Clear old notifications
    function clearOldNotifications() {
        const days = prompt('Enter number of days to keep notifications (default: 30):', '30');
        if (days === null) return;
        
        const daysToKeep = parseInt(days) || 30;
        
        if (confirm(`Are you sure you want to delete notifications older than ${daysToKeep} days?`)) {
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=clear_old_notifications&days=${daysToKeep}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    loadNotifications();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error clearing old notifications:', error);
                showNotification('Error clearing old notifications', 'error');
            });
        }
    }

    // Show notification settings
    function showSettings() {
        // Create settings modal
        const settingsModal = document.createElement('div');
        settingsModal.id = 'settings-modal';
        settingsModal.style.cssText = 'display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;';
        settingsModal.innerHTML = `
            <div style="background: white; margin: 5% auto; padding: 30px; border-radius: 15px; width: 90%; max-width: 500px; position: relative; max-height: 80vh; overflow-y: auto;">
                <span onclick="closeModal('settings-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
                <h3>Notification Settings</h3>
                <form id="settings-form">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 10px; margin: 15px 0;">
                            <input type="checkbox" id="push-notifications">
                            <span>Push Notifications</span>
                            <small style="color: #666; margin-left: 10px;">Receive real-time notifications</small>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>Auto-clear notifications older than (days):</label>
                        <input type="number" class="form-control" id="auto-clear-days" value="30" min="1" max="365">
                        <small style="color: #666;">Notifications older than this will be automatically deleted</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Default Priority Filter:</label>
                        <select class="form-control" id="default-priority-filter">
                            <option value="all">All Priorities</option>
                            <option value="high">High Priority Only</option>
                            <option value="medium">Medium Priority Only</option>
                            <option value="low">Low Priority Only</option>
                        </select>
                        <small style="color: #666;">Default filter when loading notifications</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Notification Display Limit:</label>
                        <select class="form-control" id="display-limit">
                            <option value="50">50 notifications</option>
                            <option value="100">100 notifications</option>
                            <option value="200">200 notifications</option>
                            <option value="500">500 notifications</option>
                        </select>
                        <small style="color: #666;">Maximum notifications to display at once</small>
                    </div>
                    
                    <div style="text-align: right; margin-top: 30px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('settings-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(settingsModal);
        document.getElementById('settings-modal').style.display = 'block';
        
        // Load current settings
        loadNotificationSettings();
        
        // Handle form submission
        document.getElementById('settings-form').addEventListener('submit', function(e) {
            e.preventDefault();
            saveNotificationSettings();
        });
    }

    // Load notification settings
    function loadNotificationSettings() {
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_notification_settings'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const settings = data.settings;
                document.getElementById('push-notifications').checked = settings.push_notifications;
                document.getElementById('auto-clear-days').value = settings.auto_clear_days;
                document.getElementById('default-priority-filter').value = settings.priority_filter;
                document.getElementById('display-limit').value = settings.display_limit;
            }
        })
        .catch(error => {
            console.error('Error loading notification settings:', error);
        });
    }

    // Save notification settings
    function saveNotificationSettings() {
        const formData = new FormData();
        formData.append('action', 'update_notification_settings');
        formData.append('push_notifications', document.getElementById('push-notifications').checked ? 1 : 0);
        formData.append('auto_clear_days', document.getElementById('auto-clear-days').value);
        formData.append('priority_filter', document.getElementById('default-priority-filter').value);
        formData.append('display_limit', document.getElementById('display-limit').value);

        fetch('notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Settings saved successfully', 'success');
                closeModal('settings-modal');
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error saving notification settings:', error);
            showNotification('Error saving settings', 'error');
        });
    }

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

    // Modal functions
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            modal.remove();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
