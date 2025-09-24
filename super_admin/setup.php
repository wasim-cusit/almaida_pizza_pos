<?php
/**
 * Super Admin Setup Script
 * This script initializes the super admin system with required database tables and default data
 */

require_once '../config/database.php';

// Check if setup is already completed
$query = "SHOW TABLES LIKE 'suppliers'";
$stmt = $db->prepare($query);
$stmt->execute();
$suppliers_exists = $stmt->fetch();

if ($suppliers_exists) {
    die('Super Admin system is already set up. <a href="login.php">Go to Login</a>');
}

$setup_errors = [];
$setup_success = [];

try {
    $db->beginTransaction();
    
    // Read and execute the database setup script
    $sql_file = '../database_stock_management.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        $statements = explode(';', $sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $db->exec($statement);
                    $setup_success[] = "Executed: " . substr($statement, 0, 50) . "...";
                } catch (Exception $e) {
                    $setup_errors[] = "Error executing: " . substr($statement, 0, 50) . "... - " . $e->getMessage();
                }
            }
        }
    } else {
        $setup_errors[] = "Database setup file not found: $sql_file";
    }
    
    // Create default super admin user if not exists
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'super_admin'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $super_admin_count = $stmt->fetch()['count'];
    
    if ($super_admin_count == 0) {
        // Create default super admin user
        $username = 'superadmin';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $name = 'Super Administrator';
        
        $query = "INSERT INTO users (username, password, name, role, is_active, created_at) 
                 VALUES (?, ?, ?, 'super_admin', 1, NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute([$username, $password, $name]);
        
        $setup_success[] = "Created default super admin user (username: superadmin, password: admin123)";
    }
    
    // Create default branch if not exists
    $query = "SELECT COUNT(*) as count FROM branches";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $branch_count = $stmt->fetch()['count'];
    
    if ($branch_count == 0) {
        $query = "INSERT INTO branches (name, address, phone, email, manager_name, is_active) 
                 VALUES ('Main Branch', 'Main Street, City', '091-1234567', 'main@almaida.com', 'Branch Manager', 1)";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $setup_success[] = "Created default main branch";
    }
    
    $db->commit();
    
} catch (Exception $e) {
    $db->rollBack();
    $setup_errors[] = "Setup failed: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Setup - Almaida POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .setup-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 600px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .setup-header .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .setup-header .logo i {
            font-size: 2em;
            color: white;
        }
        
        .setup-header h1 {
            color: #1f2937;
            font-size: 1.8em;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .setup-header p {
            color: #6b7280;
            font-size: 14px;
        }
        
        .setup-results {
            margin-bottom: 30px;
        }
        
        .success-list, .error-list {
            margin-bottom: 20px;
        }
        
        .success-list h3 {
            color: #059669;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .error-list h3 {
            color: #dc2626;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .success-list ul, .error-list ul {
            list-style: none;
            padding: 0;
        }
        
        .success-list li, .error-list li {
            padding: 8px 12px;
            margin-bottom: 5px;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .success-list li {
            background: #d1fae5;
            color: #065f46;
            border-left: 3px solid #10b981;
        }
        
        .error-list li {
            background: #fee2e2;
            color: #991b1b;
            border-left: 3px solid #ef4444;
        }
        
        .setup-actions {
            text-align: center;
        }
        
        .btn-setup {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            margin: 0 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-setup:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .credentials-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .credentials-box h4 {
            color: #1f2937;
            margin-bottom: 10px;
        }
        
        .credentials-box p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .credentials-box strong {
            color: #1f2937;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <div class="logo">
                <i class="fas fa-crown"></i>
            </div>
            <h1>Super Admin Setup</h1>
            <p>Initializing advanced stock management system</p>
        </div>
        
        <div class="setup-results">
            <?php if (!empty($setup_success)): ?>
                <div class="success-list">
                    <h3><i class="fas fa-check-circle"></i> Setup Completed Successfully</h3>
                    <ul>
                        <?php foreach ($setup_success as $success): ?>
                            <li><?php echo htmlspecialchars($success); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($setup_errors)): ?>
                <div class="error-list">
                    <h3><i class="fas fa-exclamation-triangle"></i> Setup Errors</h3>
                    <ul>
                        <?php foreach ($setup_errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($setup_errors)): ?>
            <div class="credentials-box">
                <h4><i class="fas fa-key"></i> Default Super Admin Credentials</h4>
                <p><strong>Username:</strong> superadmin</p>
                <p><strong>Password:</strong> admin123</p>
                <p style="color: #dc2626; font-weight: 600;">⚠️ Please change these credentials after first login!</p>
            </div>
        <?php endif; ?>
        
        <div class="setup-actions">
            <?php if (empty($setup_errors)): ?>
                <a href="login.php" class="btn-setup btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Go to Super Admin Login
                </a>
                <a href="../index.php" class="btn-setup btn-success">
                    <i class="fas fa-arrow-left"></i>
                    Back to Main System
                </a>
            <?php else: ?>
                <a href="setup.php" class="btn-setup btn-primary">
                    <i class="fas fa-redo"></i>
                    Retry Setup
                </a>
                <a href="../index.php" class="btn-setup btn-success">
                    <i class="fas fa-arrow-left"></i>
                    Back to Main System
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
