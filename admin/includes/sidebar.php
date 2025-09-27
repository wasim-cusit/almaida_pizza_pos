<?php
// Get current page for active nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h1>🍕 Admin Panel</h1>
        <?php if (isset($_SESSION['branch_name']) && $_SESSION['branch_name']): ?>
            <div class="branch-info">📍 <?php echo htmlspecialchars($_SESSION['branch_name']); ?> Branch</div>
        <?php endif; ?>
        <div class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
    </div>
    
    <div class="sidebar-nav">
        <!-- Dashboard Section -->
        <div class="nav-section">
            <div class="nav-section-title">Dashboard</div>
            <a href="index.php" class="nav-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="../index.php" class="nav-item">
                <i class="fas fa-arrow-left"></i>
                <span class="nav-text">Back to POS</span>
            </a>
        </div>
        
        <!-- Orders Section -->
        <div class="nav-section">
            <div class="nav-section-title">Orders</div>
            <a href="view_orders.php" class="nav-item <?php echo $current_page === 'view_orders.php' ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i>
                <span class="nav-text">View Orders</span>
            </a>
            <a href="kitchen_display.php" class="nav-item <?php echo $current_page === 'kitchen_display.php' ? 'active' : ''; ?>">
                <i class="fas fa-utensils"></i>
                <span class="nav-text">Kitchen Display</span>
            </a>
        </div>
        
        <!-- Inventory Section -->
        <div class="nav-section">
            <div class="nav-section-title">Inventory</div>
            <a href="manage_items.php" class="nav-item <?php echo $current_page === 'manage_items.php' ? 'active' : ''; ?>">
                <i class="fas fa-utensils"></i>
                <span class="nav-text">Manage Items</span>
            </a>
            <a href="manage_categories.php" class="nav-item <?php echo $current_page === 'manage_categories.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                <span class="nav-text">Manage Categories</span>
            </a>
            <a href="received_stock.php" class="nav-item <?php echo $current_page === 'received_stock.php' ? 'active' : ''; ?>">
                <i class="fas fa-truck-loading"></i>
                <span class="nav-text">Received Stock</span>
            </a>
        </div>
        
        <!-- Staff & Schedule Section -->
        <div class="nav-section">
            <div class="nav-section-title">Staff Management</div>
            <a href="manage_users.php" class="nav-item <?php echo $current_page === 'manage_users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span class="nav-text">Manage Users</span>
            </a>
            <a href="shift_schedule.php" class="nav-item <?php echo $current_page === 'shift_schedule.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span class="nav-text">Shift Schedule</span>
            </a>
        </div>
        
        <!-- Financial Section -->
        <div class="nav-section">
            <div class="nav-section-title">Financial</div>
            <a href="revenue_reconciliation.php" class="nav-item <?php echo $current_page === 'revenue_reconciliation.php' ? 'active' : ''; ?>">
                <i class="fas fa-cash-register"></i>
                <span class="nav-text">Revenue Reconciliation</span>
            </a>
            <a href="reports.php" class="nav-item <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span class="nav-text">Reports</span>
            </a>
        </div>
        
        <!-- Marketing Section -->
        <div class="nav-section">
            <div class="nav-section-title">Marketing</div>
            <a href="manage_special_offers.php" class="nav-item <?php echo $current_page === 'manage_special_offers.php' ? 'active' : ''; ?>">
                <i class="fas fa-gift"></i>
                <span class="nav-text">Special Offers</span>
            </a>
        </div>
        
        <!-- Settings Section -->
        <div class="nav-section">
            <div class="nav-section-title">Settings</div>
            <a href="settings.php" class="nav-item <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span class="nav-text">Settings</span>
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-text">Logout</span>
            </a>
        </div>
        
        <!-- Testing Section -->
        <div class="nav-section">
            <div class="nav-section-title">Testing</div>
            <a href="test_all_pages.php" class="nav-item <?php echo $current_page === 'test_all_pages.php' ? 'active' : ''; ?>">
                <i class="fas fa-vial"></i>
                <span class="nav-text">All Pages Test</span>
            </a>
            <a href="test_dark_mode.php" class="nav-item <?php echo $current_page === 'test_dark_mode.php' ? 'active' : ''; ?>">
                <i class="fas fa-palette"></i>
                <span class="nav-text">Dark Mode Test</span>
            </a>
        </div>
    </div>
</nav>
