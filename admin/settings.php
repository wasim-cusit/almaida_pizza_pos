<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_settings') {
        $settings = [
            'company_name' => sanitize($_POST['company_name']),
            'company_address' => sanitize($_POST['company_address']),
            'company_phone' => sanitize($_POST['company_phone']),
            'company_email' => sanitize($_POST['company_email']),
            'company_website' => sanitize($_POST['company_website']),
            'company_gst' => sanitize($_POST['company_gst']),
            'company_license' => sanitize($_POST['company_license']),
            'tax_rate' => (float)$_POST['tax_rate'],
            'currency' => sanitize($_POST['currency']),
            'receipt_footer' => sanitize($_POST['receipt_footer']),
            'auto_order_number' => $_POST['auto_order_number'] ? 'true' : 'false',
            'order_prefix' => sanitize($_POST['order_prefix'])
        ];
        
        foreach ($settings as $key => $value) {
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                      ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$key, $value, $value]);
        }
        
        header('Location: settings.php?success=Settings updated successfully');
        exit();
    }
}

// Get current settings
$query = "SELECT setting_key, setting_value FROM settings";
$stmt = $db->prepare($query);
$stmt->execute();
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Set defaults if not found
$defaults = [
    'company_name' => 'US FOODS',
    'company_address' => 'Commercial Market #1 OPF Colony Peshawar',
    'company_phone' => '091-2614488',
    'company_email' => 'info@fastfoodpos.com',
    'company_website' => 'https://mindgigspk.com/',
    'company_gst' => 'GST123456789',
    'company_license' => 'LIC123456789',
    'tax_rate' => '0.00',
    'currency' => 'PKR',
    'receipt_footer' => 'Thank you for your order!',
    'auto_order_number' => 'true',
    'order_prefix' => 'ORD'
];

foreach ($defaults as $key => $default) {
    if (!isset($settings[$key])) {
        $settings[$key] = $default;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Fast Food POS</title>
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
            max-width: 800px;
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
        
        .settings-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .form-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
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
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #20bf55;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .btn-admin {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-primary { background: #20bf55; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn-admin:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .info-box {
            background: #e3f2fd;
            color: #0d47a1;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>⚙️ System Settings</h1>
                <p>Configure POS system preferences</p>
            </div>
            <div>
                <a href="index.php" class="btn-admin btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <strong>💡 Tip:</strong> These settings affect the entire POS system. Changes will apply to new orders and receipts.
        </div>
        
        <form method="POST" class="settings-form">
            <input type="hidden" name="action" value="update_settings">
            
            <!-- Company Settings -->
            <div class="form-section">
                <h3><i class="fas fa-building"></i> Company Information</h3>
                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input type="text" id="company_name" name="company_name" 
                           value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="company_address">Company Address</label>
                    <textarea id="company_address" name="company_address" rows="3" 
                              placeholder="Enter complete company address"><?php echo htmlspecialchars($settings['company_address']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="company_phone">Phone Number</label>
                    <input type="text" id="company_phone" name="company_phone" 
                           value="<?php echo htmlspecialchars($settings['company_phone']); ?>" 
                           placeholder="+92 300 1234567">
                </div>
                <div class="form-group">
                    <label for="company_email">Email Address</label>
                    <input type="Phone" id="company_email" name="company_email" 
                           value="<?php echo htmlspecialchars($settings['company_email']); ?>" 
                           placeholder="info@company.com">
                </div>
                <div class="form-group">
                    <label for="company_website">Website</label>
                    <input type="url" id="company_website" name="company_website" 
                           value="<?php echo htmlspecialchars($settings['company_website']); ?>" 
                           placeholder="www.company.com">
                </div>
                <div class="form-group">
                    <label for="company_gst">GST Number</label>
                    <input type="text" id="company_gst" name="company_gst" 
                           value="<?php echo htmlspecialchars($settings['company_gst']); ?>" 
                           placeholder="GST123456789">
                </div>
                <div class="form-group">
                    <label for="company_license">Business License</label>
                    <input type="text" id="company_license" name="company_license" 
                           value="<?php echo htmlspecialchars($settings['company_license']); ?>" 
                           placeholder="LIC123456789">
                </div>
            </div>
            
            <!-- Receipt Settings -->
            <div class="form-section">
                <h3><i class="fas fa-receipt"></i> Receipt Settings</h3>
                <div class="form-group">
                    <label for="receipt_footer">Receipt Footer Message</label>
                    <textarea id="receipt_footer" name="receipt_footer"><?php echo htmlspecialchars($settings['receipt_footer']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="order_prefix">Order Number Prefix</label>
                    <input type="text" id="order_prefix" name="order_prefix" 
                           value="<?php echo htmlspecialchars($settings['order_prefix']); ?>" required>
                </div>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="auto_order_number" name="auto_order_number" 
                               <?php echo $settings['auto_order_number'] === 'true' ? 'checked' : ''; ?>>
                        <label for="auto_order_number">Auto-generate order numbers</label>
                    </div>
                </div>
            </div>
            
            <!-- Tax & Currency Settings -->
            <div class="form-section">
                <h3><i class="fas fa-calculator"></i> Tax & Currency</h3>
                <div class="form-group">
                    <label for="tax_rate">Tax Rate (%)</label>
                    <input type="number" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100"
                           value="<?php echo htmlspecialchars($settings['tax_rate']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="currency">Currency Symbol</label>
                    <select id="currency" name="currency" required>
                        <option value="PKR" <?php echo $settings['currency'] === 'PKR' ? 'selected' : ''; ?>>PKR (Pakistani Rupee)</option>
                        <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                        <option value="EUR" <?php echo $settings['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                        <option value="GBP" <?php echo $settings['currency'] === 'GBP' ? 'selected' : ''; ?>>GBP (British Pound)</option>
                        <option value="INR" <?php echo $settings['currency'] === 'INR' ? 'selected' : ''; ?>>INR (Indian Rupee)</option>
                    </select>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="form-section">
                <h3><i class="fas fa-info-circle"></i> System Information</h3>
                <div class="form-group">
                    <label>PHP Version</label>
                    <input type="text" value="<?php echo phpversion(); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Database</label>
                    <input type="text" value="MySQL" readonly>
                </div>
                <div class="form-group">
                    <label>Server Time</label>
                    <input type="text" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="form-section">
                <button type="submit" class="btn-admin btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
                <a href="index.php" class="btn-admin btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    
    <script>
        // Auto-save form data to prevent loss
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input, select, textarea');
        
        // Save form data to localStorage
        function saveFormData() {
            const formData = {};
            inputs.forEach(input => {
                if (input.type === 'checkbox') {
                    formData[input.name] = input.checked;
                } else {
                    formData[input.name] = input.value;
                }
            });
            localStorage.setItem('pos_settings_form', JSON.stringify(formData));
        }
        
        // Load form data from localStorage
        function loadFormData() {
            const saved = localStorage.getItem('pos_settings_form');
            if (saved) {
                const formData = JSON.parse(saved);
                inputs.forEach(input => {
                    if (formData[input.name] !== undefined) {
                        if (input.type === 'checkbox') {
                            input.checked = formData[input.name];
                        } else {
                            input.value = formData[input.name];
                        }
                    }
                });
            }
        }
        
        // Auto-save on input change
        inputs.forEach(input => {
            input.addEventListener('change', saveFormData);
            input.addEventListener('input', saveFormData);
        });
        
        // Load saved data on page load
        document.addEventListener('DOMContentLoaded', loadFormData);
        
        // Clear saved data on successful form submission
        form.addEventListener('submit', function() {
            localStorage.removeItem('pos_settings_form');
        });
    </script>
</body>
</html> 