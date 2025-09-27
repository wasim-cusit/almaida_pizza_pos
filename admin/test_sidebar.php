<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = "Sidebar Test";
include 'includes/header.php';
?>

<div class="admin-section">
    <h2><i class="fas fa-flask"></i> Sidebar Test Page</h2>
    <p>This is a test page to verify the sidebar navigation works correctly on both desktop and mobile devices.</p>
    
    <div style="margin-top: 20px;">
        <h3>Test Features:</h3>
        <ul style="margin: 15px 0; padding-left: 20px;">
            <li>✅ Sidebar navigation with organized sections</li>
            <li>✅ Mobile responsive hamburger menu</li>
            <li>✅ Active page highlighting</li>
            <li>✅ Smooth animations and transitions</li>
            <li>✅ Touch-friendly mobile interface</li>
        </ul>
        
        <h3>Navigation Sections:</h3>
        <ul style="margin: 15px 0; padding-left: 20px;">
            <li><strong>Dashboard:</strong> Main dashboard and POS access</li>
            <li><strong>Orders:</strong> Order management and kitchen display</li>
            <li><strong>Inventory:</strong> Items, categories, and stock management</li>
            <li><strong>Staff Management:</strong> User management and shift scheduling</li>
            <li><strong>Financial:</strong> Revenue reconciliation and reports</li>
            <li><strong>Marketing:</strong> Special offers management</li>
            <li><strong>Settings:</strong> System settings and logout</li>
        </ul>
        
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #20bf55;">
            <h4><i class="fas fa-mobile-alt"></i> Mobile Testing Instructions:</h4>
            <ol style="margin: 10px 0; padding-left: 20px;">
                <li>Resize your browser window to mobile size (768px or less)</li>
                <li>Click the hamburger menu (☰) in the top-left corner</li>
                <li>Verify the sidebar slides in from the left</li>
                <li>Test navigation by clicking on different menu items</li>
                <li>Verify the sidebar closes after navigation</li>
                <li>Test the overlay functionality by clicking outside the sidebar</li>
            </ol>
        </div>
        
        <div style="margin-top: 20px; padding: 20px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196f3;">
            <h4><i class="fas fa-desktop"></i> Desktop Testing Instructions:</h4>
            <ol style="margin: 10px 0; padding-left: 20px;">
                <li>Ensure your browser window is wider than 768px</li>
                <li>Verify the sidebar is always visible on the left</li>
                <li>Test hover effects on navigation items</li>
                <li>Verify active page highlighting works correctly</li>
                <li>Test all navigation links lead to correct pages</li>
            </ol>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
