<?php
session_start();
require_once '../config/database.php';

// Log logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    try {
        $query = "INSERT INTO user_logs (user_id, action, ip_address, user_agent, created_at) 
                 VALUES (?, 'super_admin_logout', ?, ?, NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Log error but don't prevent logout
        error_log('Logout logging failed: ' . $e->getMessage());
    }
}

// Destroy session
session_destroy();

// Clear all session variables
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header('Location: login.php');
exit();
?>
