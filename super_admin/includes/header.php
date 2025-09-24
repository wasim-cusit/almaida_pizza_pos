<?php
// Common header for super admin pages
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Super Admin'; ?> - Almaida POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #20bf55;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-bg: #f8fafc;
            --dark-bg: #1a1a1a;
            --light-text: #1f2937;
            --dark-text: #f9fafb;
            --light-card: #ffffff;
            --dark-card: #2d2d2d;
            --light-border: #e2e8f0;
            --dark-border: #404040;
            --sidebar-width: 280px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-bg);
            color: var(--light-text);
            transition: all 0.3s ease;
            overflow-x: hidden;
        }

        body.dark-mode {
            background: var(--dark-bg);
            color: var(--dark-text);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            font-size: 1.5em;
            margin-bottom: 5px;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .sidebar-header h2 {
            opacity: 0;
        }

        .sidebar-header p {
            font-size: 0.9em;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .sidebar-header p {
            opacity: 0;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            border-right: 3px solid white;
            font-weight: 600;
        }

        .nav-link i {
            width: 20px;
            margin-right: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed .nav-link span {
            opacity: 0;
            width: 0;
        }

        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }

        .nav-badge {
            background: var(--danger-color);
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 0.8em;
            margin-left: auto;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .nav-badge {
            opacity: 0;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed + .main-content {
            margin-left: 70px;
        }

        /* Header */
        .header {
            background: var(--light-card);
            border-bottom: 1px solid var(--light-border);
            padding: 0 30px;
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            transition: all 0.3s ease;
        }

        body.dark-mode .header {
            background: var(--dark-card);
            border-bottom: 1px solid var(--dark-border);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.2em;
            cursor: pointer;
            color: var(--light-text);
            transition: all 0.3s ease;
        }

        body.dark-mode .sidebar-toggle {
            color: var(--dark-text);
        }

        .header-title {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--light-text);
        }

        body.dark-mode .header-title {
            color: var(--dark-text);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .theme-toggle {
            background: none;
            border: none;
            font-size: 1.2em;
            cursor: pointer;
            color: var(--light-text);
            transition: all 0.3s ease;
        }

        body.dark-mode .theme-toggle {
            color: var(--dark-text);
        }

        .user-menu {
            position: relative;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }

        /* Content Area */
        .content {
            padding: 30px;
        }

        /* Page Content Styles */
        .page-header {
            background: var(--light-card);
            border: 1px solid var(--light-border);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }

        body.dark-mode .page-header {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
        }

        .page-title {
            font-size: 1.8em;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-subtitle {
            color: var(--light-text);
            opacity: 0.8;
        }

        body.dark-mode .page-subtitle {
            color: var(--dark-text);
        }

        /* Cards */
        .card {
            background: var(--light-card);
            border: 1px solid var(--light-border);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }

        body.dark-mode .card {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        body.dark-mode .card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-border);
        }

        body.dark-mode .card-header {
            border-bottom: 1px solid var(--dark-border);
        }

        .card-title {
            font-size: 1.3em;
            font-weight: 600;
            color: var(--light-text);
        }

        body.dark-mode .card-title {
            color: var(--dark-text);
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-primary { 
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); 
            color: white; 
        }
        .btn-success { background: var(--success-color); color: white; }
        .btn-warning { background: var(--warning-color); color: white; }
        .btn-danger { background: var(--danger-color); color: white; }
        .btn-info { background: var(--info-color); color: white; }
        .btn-secondary { background: #6c757d; color: white; }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-border);
        }

        body.dark-mode .table th,
        body.dark-mode .table td {
            border-bottom: 1px solid var(--dark-border);
        }

        .table th {
            background: var(--light-bg);
            font-weight: 600;
            color: var(--light-text);
        }

        body.dark-mode .table th {
            background: var(--dark-bg);
            color: var(--dark-text);
        }

        .table tr:hover {
            background: var(--light-bg);
        }

        body.dark-mode .table tr:hover {
            background: var(--dark-bg);
        }

        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--light-text);
        }

        body.dark-mode .form-group label {
            color: var(--dark-text);
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--light-border);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--light-card);
            color: var(--light-text);
        }

        body.dark-mode .form-control {
            background: var(--dark-card);
            border: 2px solid var(--dark-border);
            color: var(--dark-text);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .content {
                padding: 20px;
            }

            .header {
                padding: 0 20px;
            }

            .header-title {
                font-size: 1.2em;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 15px;
            }

            .card {
                padding: 20px;
            }
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Loading indicator */
        .loading {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .loading.show {
            display: block;
        }

        .spinner {
            border: 4px solid var(--light-border);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-bg);
        }

        body.dark-mode ::-webkit-scrollbar-track {
            background: var(--dark-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        /* Fade in animation */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-crown"></i> Super Admin</h2>
            <p>Advanced Management</p>
        </div>
        
        <div class="sidebar-nav">
            <!-- Dashboard -->
            <div class="nav-item">
                <a href="index.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" data-page="dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <!-- Branch Management -->
            <div class="nav-item">
                <a href="manage_branches.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_branches.php') ? 'active' : ''; ?>" data-page="manage-branches">
                    <i class="fas fa-building"></i>
                    <span>Manage Branches</span>
                </a>
            </div>
            
            <!-- Suppliers Management -->
            <div class="nav-item">
                <a href="suppliers.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'suppliers.php') ? 'active' : ''; ?>" data-page="suppliers">
                    <i class="fas fa-truck-loading"></i>
                    <span>Manage Suppliers</span>
                </a>
            </div>
            
            <!-- Stock Management Workflow -->
            <div class="nav-item">
                <a href="stock_purchases.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'stock_purchases.php') ? 'active' : ''; ?>" data-page="stock-purchases">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Stock Purchases</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="warehouse_stock.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'warehouse_stock.php') ? 'active' : ''; ?>" data-page="warehouse-stock">
                    <i class="fas fa-warehouse"></i>
                    <span>Warehouse Stock</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="stock_distributions.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'stock_distributions.php') ? 'active' : ''; ?>" data-page="stock-distributions">
                    <i class="fas fa-truck"></i>
                    <span>Stock Distribution</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="stock_management.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'stock_management.php') ? 'active' : ''; ?>" data-page="stock-management">
                    <i class="fas fa-boxes"></i>
                    <span>Stock Management</span>
                </a>
            </div>
            
            <!-- Reports & Analytics -->
            <div class="nav-item">
                <a href="stock_reports.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'stock_reports.php') ? 'active' : ''; ?>" data-page="stock-reports">
                    <i class="fas fa-chart-bar"></i>
                    <span>Stock Reports</span>
                </a>
            </div>
            
            <!-- Notifications -->
            <div class="nav-item">
                <a href="notifications.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'notifications.php') ? 'active' : ''; ?>" data-page="notifications">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <?php if (isset($unread_notifications) && $unread_notifications > 0): ?>
                        <span class="nav-badge"><?php echo $unread_notifications; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- System Settings -->
            <div class="nav-item">
                <a href="settings.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>" data-page="settings">
                    <i class="fas fa-cog"></i>
                    <span>System Settings</span>
                </a>
            </div>
            
            <!-- Logout -->
            <div class="nav-item" style="margin-top: 20px;">
                <a href="../logout.php" class="nav-link" style="color: #ff6b6b;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="header-title"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
            </div>
            
            <div class="header-right">
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                
                <div class="user-menu">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content fade-in">
            <!-- Loading indicator -->
            <div class="loading" id="loadingIndicator">
                <div class="spinner"></div>
                <p>Loading...</p>
            </div>
