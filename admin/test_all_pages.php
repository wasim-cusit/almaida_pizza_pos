<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = "Admin Pages Test";
include 'includes/header.php';
?>

<div class="admin-section">
    <h2><i class="fas fa-check-circle"></i> Admin Pages Navigation Test</h2>
    <p>This page tests all admin pages to ensure they have proper sidebar navigation and consistent design.</p>
    
    <div style="margin-top: 30px;">
        <h3>✅ Updated Admin Pages:</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
            
            <!-- Dashboard Section -->
            <div style="padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #20bf55;">
                <h4><i class="fas fa-tachometer-alt"></i> Dashboard Section</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>✅ <a href="index.php" style="color: #20bf55;">Dashboard</a> - Main admin dashboard</li>
                    <li>✅ <a href="../index.php" style="color: #20bf55;">Back to POS</a> - Return to POS system</li>
                </ul>
            </div>
            
            <!-- Orders Section -->
            <div style="padding: 20px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196f3;">
                <h4><i class="fas fa-receipt"></i> Orders Section</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>✅ <a href="view_orders.php" style="color: #2196f3;">View Orders</a> - Order management</li>
                    <li>✅ <a href="kitchen_display.php" style="color: #2196f3;">Kitchen Display</a> - Real-time kitchen orders</li>
                </ul>
            </div>
            
            <!-- Inventory Section -->
            <div style="padding: 20px; background: #fff3e0; border-radius: 8px; border-left: 4px solid #ff9800;">
                <h4><i class="fas fa-utensils"></i> Inventory Section</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>✅ <a href="manage_items.php" style="color: #ff9800;">Manage Items</a> - Menu item management</li>
                    <li>✅ <a href="manage_categories.php" style="color: #ff9800;">Manage Categories</a> - Category management</li>
                    <li>✅ <a href="received_stock.php" style="color: #ff9800;">Received Stock</a> - Stock receiving</li>
                </ul>
            </div>
            
            <!-- Staff Management Section -->
            <div style="padding: 20px; background: #f3e5f5; border-radius: 8px; border-left: 4px solid #9c27b0;">
                <h4><i class="fas fa-users"></i> Staff Management</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>✅ <a href="manage_users.php" style="color: #9c27b0;">Manage Users</a> - User management</li>
                    <li>✅ <a href="shift_schedule.php" style="color: #9c27b0;">Shift Schedule</a> - Staff scheduling</li>
                </ul>
            </div>
            
            <!-- Financial Section -->
            <div style="padding: 20px; background: #e8f5e8; border-radius: 8px; border-left: 4px solid #4caf50;">
                <h4><i class="fas fa-cash-register"></i> Financial Section</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>✅ <a href="revenue_reconciliation.php" style="color: #4caf50;">Revenue Reconciliation</a> - Daily revenue tracking</li>
                    <li>✅ <a href="reports.php" style="color: #4caf50;">Reports</a> - Analytics and reports</li>
                </ul>
            </div>
            
            <!-- Marketing Section -->
            <div style="padding: 20px; background: #fff8e1; border-radius: 8px; border-left: 4px solid #ffc107;">
                <h4><i class="fas fa-gift"></i> Marketing Section</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>✅ <a href="manage_special_offers.php" style="color: #ffc107;">Special Offers</a> - Promotional management</li>
                </ul>
            </div>
            
            <!-- Settings Section -->
            <div style="padding: 20px; background: #f5f5f5; border-radius: 8px; border-left: 4px solid #607d8b;">
                <h4><i class="fas fa-cog"></i> Settings Section</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>✅ <a href="settings.php" style="color: #607d8b;">Settings</a> - System configuration</li>
                    <li>✅ <a href="../logout.php" style="color: #607d8b;">Logout</a> - Sign out</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div style="margin-top: 30px; padding: 20px; background: #e8f5e8; border-radius: 8px; border-left: 4px solid #4caf50;">
        <h4><i class="fas fa-palette"></i> Features Implemented:</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
            <div>
                <h5>✅ Sidebar Navigation</h5>
                <ul style="margin: 5px 0; padding-left: 15px; font-size: 0.9em;">
                    <li>Organized sections</li>
                    <li>Active page highlighting</li>
                    <li>Mobile responsive</li>
                    <li>Smooth animations</li>
                </ul>
            </div>
            <div>
                <h5>✅ Dark/Light Mode</h5>
                <ul style="margin: 5px 0; padding-left: 15px; font-size: 0.9em;">
                    <li>Theme toggle button</li>
                    <li>Persistent storage</li>
                    <li>Complete dark styling</li>
                    <li>System preference detection</li>
                </ul>
            </div>
            <div>
                <h5>✅ Mobile Responsive</h5>
                <ul style="margin: 5px 0; padding-left: 15px; font-size: 0.9em;">
                    <li>Hamburger menu</li>
                    <li>Touch-friendly interface</li>
                    <li>Responsive breakpoints</li>
                    <li>Mobile optimizations</li>
                </ul>
            </div>
            <div>
                <h5>✅ Consistent Design</h5>
                <ul style="margin: 5px 0; padding-left: 15px; font-size: 0.9em;">
                    <li>Shared components</li>
                    <li>Uniform styling</li>
                    <li>Professional appearance</li>
                    <li>Modern UI/UX</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div style="margin-top: 30px; padding: 20px; background: #fff3e0; border-radius: 8px; border-left: 4px solid #ff9800;">
        <h4><i class="fas fa-bug"></i> Testing Instructions:</h4>
        <ol style="margin: 15px 0; padding-left: 20px;">
            <li><strong>Navigation Test:</strong> Click on each navigation item in the sidebar to verify all pages load correctly</li>
            <li><strong>Theme Test:</strong> Click the 🌙/☀️ button in the top-right to toggle between light and dark modes</li>
            <li><strong>Mobile Test:</strong> Resize browser to mobile size and test hamburger menu functionality</li>
            <li><strong>Active Page Test:</strong> Verify that the current page is highlighted in the sidebar</li>
            <li><strong>Responsive Test:</strong> Test on different screen sizes to ensure proper layout</li>
        </ol>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
