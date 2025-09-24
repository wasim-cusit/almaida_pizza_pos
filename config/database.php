<?php
/**
 * Database Configuration and Connection
 * Fast Food POS System
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'u515862593_almaida';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }

    public function closeConnection() {
        $this->conn = null;
    }
}

// Global database instance
$database = new Database();
$db = $database->getConnection();

// Set default timezone for consistent date/time handling
// Get timezone from settings, fallback to Pakistan timezone
$timezone = getSetting('timezone') ?: 'Asia/Karachi';
date_default_timezone_set($timezone);

// Set MySQL timezone to match PHP timezone
try {
    $db->exec("SET time_zone = '+05:00'"); // UTC+5 for Pakistan
} catch (Exception $e) {
    // Silently fail if timezone setting is not supported
}

// Helper functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateOrderNumber() {
    global $db;
    
    $prefix = 'ORD';
    $date = date('Ymd');
    
    // Get the highest order number for today to ensure proper sequencing
    $query = "SELECT order_number FROM orders WHERE DATE(created_at) = CURDATE() ORDER BY order_number DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        // Extract the numeric part from the existing order number
        $existingNumber = $result['order_number'];
        $numericPart = substr($existingNumber, -4); // Get last 4 digits
        $count = intval($numericPart) + 1;
    } else {
        // No orders today, start with 1
        $count = 1;
    }
    
    return $prefix . $date . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function formatCurrency($amount) {
    return number_format($amount, 2);
}

function getSetting($key, $default = '') {
    global $db;
    
    try {
        $query = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php?error=access_denied');
        exit();
    }
}

/**
 * Format date consistently across the system
 * @param string $dateString Database date string
 * @param string $format Date format (default: 'M d, Y g:i A')
 * @param bool $showTimezone Whether to show timezone abbreviation
 * @return string Formatted date string
 */
function formatDate($dateString, $format = 'M d, Y g:i A', $showTimezone = true) {
    // Since the database timestamp is already in local timezone, 
    // we don't need to convert from UTC
    $dateTime = new DateTime($dateString);
    
    $formatted = $dateTime->format($format);
    
    if ($showTimezone) {
        $abbr = $dateTime->format('T'); // Timezone abbreviation (PKT, EST, etc.)
        return $formatted . ' (' . $abbr . ')';
    }
    
    return $formatted;
}
?>