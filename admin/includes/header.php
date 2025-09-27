<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($_SESSION['branch_name']) && $_SESSION['branch_name'] ? $_SESSION['branch_name'] . ' Branch - ' : ''; ?><?php echo isset($page_title) ? $page_title : 'Admin Panel'; ?> - Fast Food POS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
            transform: translateX(0);
        }
        
        .sidebar.collapsed {
            transform: translateX(-280px);
        }
        
        .sidebar-header {
            padding: 20px;
            background: linear-gradient(135deg, #20bf55 0%, #01baef 100%);
            color: white;
            text-align: center;
        }
        
        .sidebar-header h1 {
            font-size: 1.5em;
            margin-bottom: 5px;
        }
        
        .sidebar-header .branch-info {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .sidebar-header .user-info {
            font-size: 0.8em;
            margin-top: 10px;
            opacity: 0.8;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .nav-section {
            margin-bottom: 30px;
        }
        
        .nav-section-title {
            padding: 0 20px 10px;
            font-size: 0.8em;
            text-transform: uppercase;
            color: #666;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .nav-item {
            display: block;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
        }
        
        .nav-item:hover {
            background: rgba(32, 191, 85, 0.1);
            color: #20bf55;
            border-left-color: #20bf55;
        }
        
        .nav-item.active {
            background: rgba(32, 191, 85, 0.15);
            color: #20bf55;
            border-left-color: #20bf55;
            font-weight: 600;
        }
        
        .nav-item i {
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }
        
        .nav-item .nav-text {
            font-size: 0.95em;
        }
        
        .nav-item .nav-badge {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: #dc3545;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.7em;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            overflow-y: auto;
            height: 100vh;
            padding-bottom: 50px;
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .menu-toggle {
            display: none;
            background: #20bf55;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2em;
            transition: background 0.3s ease;
        }
        
        .menu-toggle:hover {
            background: #1aa049;
        }
        
        .top-bar-title h1 {
            color: #333;
            font-size: 1.8em;
            margin-bottom: 5px;
        }
        
        .top-bar-title .branch-name {
            color: #20bf55;
            font-size: 1em;
            font-weight: 600;
        }
        
        .top-bar-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-top {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-top.btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-top.btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-top.btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-top.btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-280px);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                height: auto;
                min-height: 100vh;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .top-bar-actions {
                flex-direction: column;
                gap: 5px;
            }
            
            .btn-top {
                padding: 10px 15px;
                font-size: 0.9em;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
                height: auto;
                min-height: 100vh;
            }
            
            .top-bar {
                padding: 15px;
                margin-bottom: 20px;
            }
            
            .top-bar-title h1 {
                font-size: 1.4em;
            }
        }
        
        /* Common admin styles */
        .admin-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .admin-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-admin {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 5px;
        }
        
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-primary { background: #20bf55; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-success { background: #28a745; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        
        .btn-admin:hover {
            opacity: 0.9;
        }
        
        /* Dark Mode Styles */
        [data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --bg-tertiary: #3a3a3a;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --text-muted: #808080;
            --border-color: #404040;
            --shadow: rgba(0, 0, 0, 0.3);
        }
        
        [data-theme="dark"] body {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }
        
        [data-theme="dark"] .sidebar {
            background: rgba(45, 45, 45, 0.95);
            border-right: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .sidebar-header {
            background: linear-gradient(135deg, #27ae60 0%, #3498db 100%);
        }
        
        [data-theme="dark"] .nav-item {
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .nav-item:hover {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        
        [data-theme="dark"] .nav-item.active {
            background: rgba(52, 152, 219, 0.15);
            color: #3498db;
        }
        
        [data-theme="dark"] .nav-section-title {
            color: var(--text-muted);
        }
        
        [data-theme="dark"] .top-bar {
            background: rgba(45, 45, 45, 0.95);
            border: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .top-bar-title h1 {
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .top-bar-title .branch-name {
            color: #3498db;
        }
        
        [data-theme="dark"] .admin-section {
            background: rgba(45, 45, 45, 0.95);
            border: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .admin-section h2 {
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .stat-card h3 {
            color: var(--text-secondary);
        }
        
        [data-theme="dark"] .stat-card .stat-number {
            color: #27ae60;
        }
        
        [data-theme="dark"] .stat-card .stat-label {
            color: var(--text-secondary);
        }
        
        [data-theme="dark"] .orders-table {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .orders-table th {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .orders-table td {
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .orders-table tr:hover {
            background: var(--bg-tertiary);
        }
        
        [data-theme="dark"] .order-status {
            color: var(--text-primary);
        }
        
        /* Theme Toggle Button */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            background: var(--bg-secondary, #ffffff);
            border: 2px solid var(--border-color, #e0e0e0);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px var(--shadow, rgba(0,0,0,0.15));
        }
        
        .theme-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px var(--shadow, rgba(0,0,0,0.2));
        }
        
        .theme-toggle i {
            font-size: 1.2em;
            color: var(--text-primary, #333);
        }
        
        [data-theme="dark"] .theme-toggle {
            background: var(--bg-secondary);
            border-color: var(--border-color);
        }
        
        [data-theme="dark"] .theme-toggle i {
            color: var(--text-primary);
        }
        
        /* Responsive theme toggle */
        @media (max-width: 768px) {
            .theme-toggle {
                top: 15px;
                right: 15px;
                width: 45px;
                height: 45px;
            }
            
            .theme-toggle i {
                font-size: 1.1em;
            }
        }
    </style>
</head>
<body>
    <!-- Theme Toggle Button -->
    <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>
    
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="admin-container">
        <div class="main-content" id="mainContent">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="top-bar-title">
                        <h1><?php echo isset($page_title) ? $page_title : 'Admin Panel'; ?></h1>
                        <?php if (isset($_SESSION['branch_name']) && $_SESSION['branch_name']): ?>
                            <div class="branch-name">📍 <?php echo htmlspecialchars($_SESSION['branch_name']); ?> Branch</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="top-bar-actions">
                    <a href="../index.php" class="btn-top btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to POS
                    </a>
                    <a href="../logout.php" class="btn-top btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
