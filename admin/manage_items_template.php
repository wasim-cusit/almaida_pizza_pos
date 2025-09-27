<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = "Manage Items";
include 'includes/header.php';
?>

<div class="admin-section">
    <h2><i class="fas fa-utensils"></i> Manage Items</h2>
    <p>This page demonstrates the sidebar navigation and dark mode functionality.</p>
    
    <div style="margin-top: 20px;">
        <h3>Features Available:</h3>
        <ul style="margin: 15px 0; padding-left: 20px;">
            <li>✅ Responsive sidebar navigation</li>
            <li>✅ Dark/Light mode toggle</li>
            <li>✅ Mobile-friendly interface</li>
            <li>✅ Consistent styling across pages</li>
        </ul>
        
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #20bf55;">
            <h4><i class="fas fa-palette"></i> Theme Testing:</h4>
            <p>Click the theme toggle button (🌙/☀️) in the top-right corner to switch between light and dark modes.</p>
            <p>The theme preference is automatically saved and will persist across page reloads and browser sessions.</p>
        </div>
        
        <div style="margin-top: 20px; padding: 20px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196f3;">
            <h4><i class="fas fa-mobile-alt"></i> Mobile Testing:</h4>
            <p>On mobile devices, use the hamburger menu (☰) in the top-left to access the sidebar navigation.</p>
            <p>The interface automatically adapts to different screen sizes for optimal user experience.</p>
        </div>
        
        <div style="margin-top: 20px;">
            <h4>Sample Actions:</h4>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
                <button class="btn-admin btn-primary">
                    <i class="fas fa-plus"></i> Add New Item
                </button>
                <button class="btn-admin btn-secondary">
                    <i class="fas fa-edit"></i> Edit Items
                </button>
                <button class="btn-admin btn-info">
                    <i class="fas fa-eye"></i> View All Items
                </button>
                <button class="btn-admin btn-success">
                    <i class="fas fa-upload"></i> Import Items
                </button>
            </div>
        </div>
    </div>
</div>

<div class="admin-section">
    <h2><i class="fas fa-chart-bar"></i> Item Statistics</h2>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number">45</div>
            <div class="stat-label">Total Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">12</div>
            <div class="stat-label">Low Stock Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">8</div>
            <div class="stat-label">Categories</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">156</div>
            <div class="stat-label">Items Sold Today</div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
