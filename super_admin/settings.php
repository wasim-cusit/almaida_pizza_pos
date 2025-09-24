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
        case 'get_settings':
            try {
                // Check if system_settings table exists, if not create it
                $query = "CREATE TABLE IF NOT EXISTS system_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    updated_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                $db->exec($query);
                
                // Get existing settings
                $query = "SELECT setting_key, setting_value FROM system_settings";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                // Define default settings
                $defaultSettings = [
                    'company_name' => 'Almaida POS',
                    'company_email' => 'admin@almaida.com',
                    'company_phone' => '+1-555-0123',
                    'company_address' => '123 Business St, City, State',
                    'currency' => 'USD',
                    'timezone' => 'UTC',
                    'low_stock_threshold' => '20',
                    'auto_reorder_point' => '10',
                    'stock_alert_email' => 'alerts@almaida.com',
                    'stock_alert_frequency' => 'daily',
                    'auto_stock_alerts' => '1',
                    'low_stock_notifications' => '1',
                    'email_stock_alerts' => '1',
                    'email_order_notifications' => '1',
                    'email_system_alerts' => '1',
                    'email_reports' => '1',
                    'sms_critical_alerts' => '0',
                    'sms_system_down' => '0',
                    'notification_email' => 'notifications@almaida.com',
                    'admin_email' => 'admin@almaida.com',
                    'session_timeout' => '30',
                    'max_login_attempts' => '5',
                    'password_min_length' => '8',
                    'password_expiry' => '90',
                    'require_strong_passwords' => '1',
                    'two_factor_auth' => '0',
                    'audit_logging' => '1'
                ];
                
                // Merge with existing settings
                $mergedSettings = array_merge($defaultSettings, $settings);
                
                // Insert missing settings
                foreach ($defaultSettings as $key => $value) {
                    if (!isset($settings[$key])) {
                        $query = "INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$key, $value, $_SESSION['user_id']]);
                    }
                }
                
                echo json_encode(['success' => true, 'settings' => $mergedSettings]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading settings: ' . $e->getMessage()]);
            }
            exit();
            
        case 'update_settings':
            $settings = json_decode($_POST['settings'], true);
            
            try {
                $db->beginTransaction();
                
                foreach ($settings as $key => $value) {
                    $query = "INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                             VALUES (?, ?, ?) 
                             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$key, $value, $_SESSION['user_id']]);
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error updating settings: ' . $e->getMessage()]);
            }
            exit();
            
        case 'export_settings':
            try {
                $query = "SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                $exportData = [
                    'export_date' => date('Y-m-d H:i:s'),
                    'exported_by' => $_SESSION['user_id'],
                    'settings' => $settings
                ];
                
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="settings_export_' . date('Y-m-d') . '.json"');
                echo json_encode($exportData, JSON_PRETTY_PRINT);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error exporting settings: ' . $e->getMessage()]);
            }
            exit();
            
        case 'reset_to_defaults':
            try {
                $db->beginTransaction();
                
                // Delete all existing settings
                $query = "DELETE FROM system_settings";
                $db->exec($query);
                
                // Define default settings
                $defaultSettings = [
                    'company_name' => 'Almaida POS',
                    'company_email' => 'admin@almaida.com',
                    'company_phone' => '+1-555-0123',
                    'company_address' => '123 Business St, City, State',
                    'currency' => 'USD',
                    'timezone' => 'UTC',
                    'low_stock_threshold' => '20',
                    'auto_reorder_point' => '10',
                    'stock_alert_email' => 'alerts@almaida.com',
                    'stock_alert_frequency' => 'daily',
                    'auto_stock_alerts' => '1',
                    'low_stock_notifications' => '1',
                    'email_stock_alerts' => '1',
                    'email_order_notifications' => '1',
                    'email_system_alerts' => '1',
                    'email_reports' => '1',
                    'sms_critical_alerts' => '0',
                    'sms_system_down' => '0',
                    'notification_email' => 'notifications@almaida.com',
                    'admin_email' => 'admin@almaida.com',
                    'session_timeout' => '30',
                    'max_login_attempts' => '5',
                    'password_min_length' => '8',
                    'password_expiry' => '90',
                    'require_strong_passwords' => '1',
                    'two_factor_auth' => '0',
                    'audit_logging' => '1'
                ];
                
                // Insert default settings
                $query = "INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)";
                $stmt = $db->prepare($query);
                foreach ($defaultSettings as $key => $value) {
                    $stmt->execute([$key, $value, $_SESSION['user_id']]);
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Settings reset to defaults successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error resetting settings: ' . $e->getMessage()]);
            }
            exit();
    }
}

$page_title = "System Settings";
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-cog"></i> System Settings
    </h1>
    <p class="page-subtitle">Configure system parameters and preferences</p>
</div>

<!-- Settings Tabs -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-sliders-h"></i> Configuration Settings
        </h2>
    </div>
    
    <div style="display: flex; border-bottom: 1px solid var(--light-border); margin-bottom: 20px;">
        <button class="tab-button active" onclick="showTab('general')" style="padding: 15px 20px; border: none; background: none; cursor: pointer; border-bottom: 2px solid var(--primary-color);">
            <i class="fas fa-cog"></i> General
        </button>
        <button class="tab-button" onclick="showTab('stock')" style="padding: 15px 20px; border: none; background: none; cursor: pointer;">
            <i class="fas fa-boxes"></i> Stock
        </button>
        <button class="tab-button" onclick="showTab('notifications')" style="padding: 15px 20px; border: none; background: none; cursor: pointer;">
            <i class="fas fa-bell"></i> Notifications
        </button>
        <button class="tab-button" onclick="showTab('security')" style="padding: 15px 20px; border: none; background: none; cursor: pointer;">
            <i class="fas fa-shield-alt"></i> Security
        </button>
    </div>

    <!-- General Settings -->
    <div id="general-tab" class="settings-tab">
        <form id="general-settings-form">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" class="form-control" name="company_name" value="Almaida POS">
                </div>
                <div class="form-group">
                    <label>Company Email</label>
                    <input type="email" class="form-control" name="company_email" value="admin@almaida.com">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Company Phone</label>
                    <input type="tel" class="form-control" name="company_phone" value="+1-555-0123">
                </div>
                <div class="form-group">
                    <label>Company Address</label>
                    <input type="text" class="form-control" name="company_address" value="123 Business St, City, State">
                </div>
            </div>
            
            <div class="form-group">
                <label>Currency</label>
                <select class="form-control" name="currency">
                    <option value="USD">USD - US Dollar</option>
                    <option value="EUR">EUR - Euro</option>
                    <option value="GBP">GBP - British Pound</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Time Zone</label>
                <select class="form-control" name="timezone">
                    <option value="UTC">UTC</option>
                    <option value="America/New_York">Eastern Time</option>
                    <option value="America/Chicago">Central Time</option>
                    <option value="America/Denver">Mountain Time</option>
                    <option value="America/Los_Angeles">Pacific Time</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Stock Settings -->
    <div id="stock-tab" class="settings-tab" style="display: none;">
        <form id="stock-settings-form">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Default Low Stock Threshold (%)</label>
                    <input type="number" class="form-control" name="low_stock_threshold" value="20" min="1" max="100">
                </div>
                <div class="form-group">
                    <label>Auto Reorder Point</label>
                    <input type="number" class="form-control" name="auto_reorder_point" value="10" min="0">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Stock Alert Email</label>
                    <input type="email" class="form-control" name="stock_alert_email" value="alerts@almaida.com">
                </div>
                <div class="form-group">
                    <label>Stock Alert Frequency</label>
                    <select class="form-control" name="stock_alert_frequency">
                        <option value="immediate">Immediate</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="auto_stock_alerts" checked> Enable Automatic Stock Alerts
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="low_stock_notifications" checked> Send Low Stock Notifications
                </label>
            </div>
        </form>
    </div>

    <!-- Notification Settings -->
    <div id="notifications-tab" class="settings-tab" style="display: none;">
        <form id="notification-settings-form">
            <div class="form-group">
                <label>Email Notifications</label>
                <div style="margin-left: 20px;">
                    <label><input type="checkbox" name="email_stock_alerts" checked> Stock Alerts</label><br>
                    <label><input type="checkbox" name="email_order_notifications" checked> Order Notifications</label><br>
                    <label><input type="checkbox" name="email_system_alerts" checked> System Alerts</label><br>
                    <label><input type="checkbox" name="email_reports" checked> Report Notifications</label>
                </div>
            </div>
            
            <div class="form-group">
                <label>SMS Notifications</label>
                <div style="margin-left: 20px;">
                    <label><input type="checkbox" name="sms_critical_alerts"> Critical Stock Alerts</label><br>
                    <label><input type="checkbox" name="sms_system_down"> System Down Alerts</label>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Notification Email</label>
                    <input type="email" class="form-control" name="notification_email" value="notifications@almaida.com">
                </div>
                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" class="form-control" name="admin_email" value="admin@almaida.com">
                </div>
            </div>
        </form>
    </div>

    <!-- Security Settings -->
    <div id="security-tab" class="settings-tab" style="display: none;">
        <form id="security-settings-form">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Session Timeout (minutes)</label>
                    <input type="number" class="form-control" name="session_timeout" value="30" min="5" max="480">
                </div>
                <div class="form-group">
                    <label>Max Login Attempts</label>
                    <input type="number" class="form-control" name="max_login_attempts" value="5" min="3" max="10">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Password Min Length</label>
                    <input type="number" class="form-control" name="password_min_length" value="8" min="6" max="20">
                </div>
                <div class="form-group">
                    <label>Password Expiry (days)</label>
                    <input type="number" class="form-control" name="password_expiry" value="90" min="30" max="365">
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="require_strong_passwords" checked> Require Strong Passwords
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="two_factor_auth"> Enable Two-Factor Authentication
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="audit_logging" checked> Enable Audit Logging
                </label>
            </div>
        </form>
    </div>

    <div style="text-align: right; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--light-border);">
        <button class="btn btn-info" onclick="exportSettings()">
            <i class="fas fa-download"></i> Export Settings
        </button>
        <button class="btn btn-warning" onclick="importSettings()">
            <i class="fas fa-upload"></i> Import Settings
        </button>
        <button class="btn btn-secondary" onclick="resetSettings()">
            <i class="fas fa-undo"></i> Reset to Defaults
        </button>
        <button class="btn btn-primary" onclick="saveSettings()">
            <i class="fas fa-save"></i> Save Settings
        </button>
    </div>
</div>

<!-- System Information -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-info-circle"></i> System Information
        </h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
            <h4>Application Version</h4>
            <p style="font-size: 1.2em; font-weight: 600; color: var(--primary-color);">v2.1.0</p>
        </div>
        
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
            <h4>Database Version</h4>
            <p style="font-size: 1.2em; font-weight: 600; color: var(--primary-color);">MySQL 8.0</p>
        </div>
        
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
            <h4>PHP Version</h4>
            <p style="font-size: 1.2em; font-weight: 600; color: var(--primary-color);"><?php echo PHP_VERSION; ?></p>
        </div>
        
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
            <h4>Server</h4>
            <p style="font-size: 1.2em; font-weight: 600; color: var(--primary-color);"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
        </div>
    </div>
</div>

<!-- Hidden file input for import -->
<input type="file" id="import-file" accept=".json" style="display: none;" onchange="handleImportFile(event)">

<script>
    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadSettings();
    });

    // Show tab
    function showTab(tabName) {
        // Hide all tabs
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.style.display = 'none';
        });
        
        // Remove active class from all buttons
        document.querySelectorAll('.tab-button').forEach(button => {
            button.style.borderBottom = 'none';
        });
        
        // Show selected tab
        document.getElementById(tabName + '-tab').style.display = 'block';
        
        // Add active class to clicked button
        event.target.style.borderBottom = '2px solid var(--primary-color)';
    }

    // Load settings
    function loadSettings() {
        fetch('settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_settings'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate settings forms with data
                populateSettings(data.settings);
            }
        })
        .catch(error => {
            console.error('Error loading settings:', error);
            showNotification('Failed to load settings', 'error');
        });
    }

    // Global settings storage
    let currentSettings = {};

    // Populate settings forms
    function populateSettings(settings) {
        currentSettings = settings;
        
        // Populate all forms with settings data
        Object.keys(settings).forEach(key => {
            const elements = document.querySelectorAll(`[name="${key}"]`);
            elements.forEach(element => {
                if (element.type === 'checkbox') {
                    element.checked = settings[key] === '1' || settings[key] === 'true';
                } else {
                    element.value = settings[key];
                }
            });
        });
    }

    // Save settings
    function saveSettings() {
        const allSettings = {};
        
        // Collect settings from all forms
        document.querySelectorAll('form').forEach(form => {
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                allSettings[key] = value;
            }
        });
        
        // Handle checkboxes that might not be in FormData
        document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            if (!allSettings.hasOwnProperty(checkbox.name)) {
                allSettings[checkbox.name] = checkbox.checked ? '1' : '0';
            }
        });
        
        fetch('settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_settings&settings=${encodeURIComponent(JSON.stringify(allSettings))}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Settings saved successfully', 'success');
                loadSettings(); // Reload to get updated values
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error saving settings:', error);
            showNotification('Error saving settings', 'error');
        });
    }

    // Reset settings
    function resetSettings() {
        if (confirm('Are you sure you want to reset all settings to defaults? This action cannot be undone.')) {
            fetch('settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=reset_to_defaults'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Settings reset to defaults', 'success');
                    loadSettings();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error resetting settings:', error);
                showNotification('Error resetting settings', 'error');
            });
        }
    }

    // Export settings
    function exportSettings() {
        fetch('settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=export_settings'
        })
        .then(response => {
            if (response.ok) {
                return response.blob();
            }
            throw new Error('Export failed');
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `settings_export_${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            showNotification('Settings exported successfully', 'success');
        })
        .catch(error => {
            console.error('Error exporting settings:', error);
            showNotification('Error exporting settings', 'error');
        });
    }

    // Import settings
    function importSettings() {
        document.getElementById('import-file').click();
    }

    // Handle import file
    function handleImportFile(event) {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = JSON.parse(e.target.result);
                if (data.settings) {
                    // Import settings
                    const settings = data.settings;
                    
                    fetch('settings.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=update_settings&settings=${encodeURIComponent(JSON.stringify(settings))}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('Settings imported successfully', 'success');
                            loadSettings();
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error importing settings:', error);
                        showNotification('Error importing settings', 'error');
                    });
                } else {
                    showNotification('Invalid settings file format', 'error');
                }
            } catch (error) {
                console.error('Error parsing settings file:', error);
                showNotification('Error parsing settings file', 'error');
            }
        };
        reader.readAsText(file);
        
        // Reset file input
        event.target.value = '';
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
</script>

<style>
    .tab-button {
        transition: all 0.3s ease;
    }
    
    .tab-button:hover {
        background: var(--light-bg);
    }
    
    .settings-tab {
        min-height: 400px;
    }
</style>

<?php include 'includes/footer.php'; ?>
