<?php
/**
 * Fast Food POS System - Installation Script
 * This script helps set up the database and initial configuration
 */

// Check if already installed
if (file_exists('config/installed.txt')) {
    die('POS System is already installed. Remove config/installed.txt to reinstall.');
}

$errors = [];
$success = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'] ?? 'localhost';
    $dbname = $_POST['dbname'] ?? 'pos_system';
    $username = $_POST['username'] ?? 'root';
    $password = $_POST['password'] ?? '';
    
    try {
        // Test database connection
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
        $pdo->exec("USE `$dbname`");
        
        // Read and execute SQL file
        $sql = file_get_contents('database.sql');
        $pdo->exec($sql);
        
        // Create config file
        $configContent = "<?php
/**
 * Database Configuration and Connection
 * Fast Food POS System
 */

class Database {
    private \$host = '$host';
    private \$db_name = '$dbname';
    private \$username = '$username';
    private \$password = '$password';
    private \$conn;

    public function getConnection() {
        \$this->conn = null;

        try {
            \$this->conn = new PDO(
                \"mysql:host=\" . \$this->host . \";dbname=\" . \$this->db_name,
                \$this->username,
                \$this->password
            );
            \$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            \$this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException \$exception) {
            echo \"Connection error: \" . \$exception->getMessage();
        }

        return \$this->conn;
    }

    public function closeConnection() {
        \$this->conn = null;
    }
}

// Global database instance
\$database = new Database();
\$db = \$database->getConnection();

// Helper functions
function sanitize(\$data) {
    return htmlspecialchars(strip_tags(trim(\$data)));
}

function generateOrderNumber() {
    global \$db;
    
    \$prefix = 'ORD';
    \$date = date('Ymd');
    \$query = \"SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()\";
    \$stmt = \$db->prepare(\$query);
    \$stmt->execute();
    \$result = \$stmt->fetch();
    
    \$count = \$result['count'] + 1;
    return \$prefix . \$date . str_pad(\$count, 4, '0', STR_PAD_LEFT);
}

function formatCurrency(\$amount) {
    return number_format(\$amount, 2);
}

function getSetting(\$key) {
    global \$db;
    
    \$query = \"SELECT setting_value FROM settings WHERE setting_key = ?\";
    \$stmt = \$db->prepare(\$query);
    \$stmt->execute([\$key]);
    \$result = \$stmt->fetch();
    
    return \$result ? \$result['setting_value'] : null;
}

function isLoggedIn() {
    return isset(\$_SESSION['user_id']) && !empty(\$_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function isAdmin() {
    return isset(\$_SESSION['user_role']) && \$_SESSION['user_role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php?error=access_denied');
        exit();
    }
}
?>";
        
        // Create config directory if it doesn't exist
        if (!is_dir('config')) {
            mkdir('config', 0755, true);
        }
        
        // Write config file
        file_put_contents('config/database.php', $configContent);
        
        // Create installed marker
        file_put_contents('config/installed.txt', date('Y-m-d H:i:s'));
        
        $success[] = 'Database setup completed successfully!';
        $success[] = 'Configuration file created.';
        $success[] = 'POS System is ready to use.';
        
    } catch (Exception $e) {
        $errors[] = 'Database Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fast Food POS System - Installation</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .install-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        
        .install-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .install-header h1 {
            color: #333;
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .install-header p {
            color: #666;
            margin: 10px 0 0 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .install-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .install-btn:hover {
            opacity: 0.9;
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
        }
        
        .success-message {
            background: #efe;
            color: #363;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #cfc;
        }
        
        .login-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        
        .login-info strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>🍕 Fast Food POS</h1>
            <p>System Installation</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <?php foreach ($success as $msg): ?>
                <div class="success-message"><?php echo htmlspecialchars($msg); ?></div>
            <?php endforeach; ?>
            
            <div class="login-info">
                <h3>Installation Complete!</h3>
                <p>Your POS system is now ready to use.</p>
                <p><strong>Default Login Credentials:</strong></p>
                <p>Username: <strong>admin</strong></p>
                <p>Password: <strong>password</strong></p>
                <br>
                <a href="login.php" style="background: #20bf55; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; display: inline-block;">
                    Go to Login
                </a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="host">Database Host</label>
                    <input type="text" id="host" name="host" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="dbname">Database Name</label>
                    <input type="text" id="dbname" name="dbname" value="pos_system" required>
                </div>
                
                <div class="form-group">
                    <label for="username">Database Username</label>
                    <input type="text" id="username" name="username" value="root" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Database Password</label>
                    <input type="password" id="password" name="password">
                </div>
                
                <button type="submit" class="install-btn">Install POS System</button>
            </form>
            
            <div class="login-info">
                <p><strong>Requirements:</strong></p>
                <p>• PHP 7.4+ with PDO MySQL extension</p>
                <p>• MySQL 5.7+ server</p>
                <p>• Web server (Apache/Nginx)</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 