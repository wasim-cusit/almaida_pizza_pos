<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is cashier or admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'cashier' && $_SESSION['user_role'] !== 'admin')) {
    header('Location: ../login.php');
    exit;
}

// Get statistics
$stats = [];

// Total orders today
$query = "SELECT COUNT(*) as count, SUM(total_amount) as total FROM orders WHERE DATE(created_at) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute();
$todayStats = $stmt->fetch();
$stats['today_orders'] = $todayStats['count'] ?? 0;
$stats['today_revenue'] = $todayStats['total'] ?? 0;

// Total orders (all time)
$query = "SELECT COUNT(*) as count, SUM(total_amount) as total FROM orders";
$stmt = $db->prepare($query);
$stmt->execute();
$allTimeStats = $stmt->fetch();
$stats['total_orders'] = $allTimeStats['count'] ?? 0;
$stats['total_revenue'] = $allTimeStats['total'] ?? 0;

// Orders by current user (if cashier)
if ($_SESSION['user_role'] === 'cashier') {
    $query = "SELECT COUNT(*) as count, SUM(total_amount) as total FROM orders WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $userStats = $stmt->fetch();
    $stats['user_orders'] = $userStats['count'] ?? 0;
    $stats['user_revenue'] = $userStats['total'] ?? 0;
}

// Recent orders
$query = "SELECT o.*, u.name as user_name, c.name as customer_name 
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          LEFT JOIN customers c ON o.customer_id = c.id 
          ORDER BY o.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recentOrders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - Fast Food POS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Override main CSS for admin pages to enable scrolling */
        body {
            overflow: auto !important;
            height: auto !important;
            min-height: 100vh;
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #20bf55;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .change.positive { color: #20bf55; }
        .change.negative { color: #dc3545; }
        
        .search-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-box input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .search-box button {
            padding: 12px 20px;
            background: #20bf55;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .orders-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .orders-table tr:hover {
            background: #f8f9fa;
        }
        
        .btn-admin {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin: 5px;
        }
        
        .btn-primary { background: #20bf55; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        
        .btn-admin:hover {
            opacity: 0.9;
        }
        
        .order-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-preparing { background: #d1ecf1; color: #0c5460; }
        .status-ready { background: #d4edda; color: #155724; }
        .status-completed { background: #c3e6cb; color: #155724; }
        .status-cancelled { background: #f5c6cb; color: #721c24; }
        
        .admin-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .admin-section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .admin-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: transform 0.3s ease;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            color: #333;
        }
        
        .action-card i {
            font-size: 48px;
            color: #20bf55;
            margin-bottom: 15px;
        }
        
        .action-card h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .action-card p {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>🍕 Fast Food POS - Cashier Dashboard</h1>
                <p>Welcome, <?php echo $_SESSION['user_name']; ?> (<?php echo ucfirst($_SESSION['user_role']); ?>)</p>
            </div>
            <div>
                <a href="../index.php" class="btn-admin btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to POS
                </a>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="index.php" class="btn-admin btn-primary">
                        <i class="fas fa-cog"></i> Admin Dashboard
                    </a>
                <?php endif; ?>
                <a href="../logout.php" class="btn-admin btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['today_orders']; ?></div>
                <div class="stat-label">Orders Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">PKR <?php echo number_format($stats['today_revenue'], 2); ?></div>
                <div class="stat-label">Revenue Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                <div class="stat-label">Total Orders (All Time)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">PKR <?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue (All Time)</div>
            </div>
            <?php if ($_SESSION['user_role'] === 'cashier'): ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['user_orders']; ?></div>
                <div class="stat-label">Your Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">PKR <?php echo number_format($stats['user_revenue'], 2); ?></div>
                <div class="stat-label">Your Revenue</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Order Search -->
        <div class="search-section">
            <h2><i class="fas fa-search"></i> Search Orders</h2>
            <div class="search-box">
                <input type="text" id="order-search" placeholder="Search by order number, customer name, or phone..." onkeyup="searchOrders(this.value)">
                <button onclick="searchOrders(document.getElementById('order-search').value)">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
            <div id="search-results"></div>
        </div>
        
        <!-- Recent Orders -->
        <div class="admin-section">
            <h2><i class="fas fa-list"></i> Recent Orders</h2>
            <?php if (empty($recentOrders)): ?>
                <p>No recent orders found.</p>
            <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Walk-in'); ?></td>
                            <td>
                                <?php
                                $query = "SELECT COUNT(*) as count FROM order_items WHERE order_id = ?";
                                $stmt = $db->prepare($query);
                                $stmt->execute([$order['id']]);
                                $itemCount = $stmt->fetch()['count'];
                                echo $itemCount . ' items';
                                ?>
                            </td>
                            <td>PKR <?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="order-status status-<?php echo $order['order_status']; ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                            <td>
                                <a href="view_order_details.php?id=<?php echo $order['id']; ?>" class="btn-admin btn-secondary" style="padding: 5px 10px; font-size: 12px;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Quick Actions -->
        <div class="admin-section">
            <h2><i class="fas fa-cogs"></i> Quick Actions</h2>
            <div class="admin-actions">
                <a href="view_orders.php" class="btn-admin btn-primary">
                    <i class="fas fa-receipt"></i> View All Orders
                </a>
                <a href="kitchen_display.php" class="btn-admin btn-primary">
                    <i class="fas fa-utensils"></i> Kitchen Display
                </a>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <a href="manage_items.php" class="btn-admin btn-primary">
                    <i class="fas fa-utensils"></i> Manage Items
                </a>
                <a href="manage_categories.php" class="btn-admin btn-primary">
                    <i class="fas fa-tags"></i> Manage Categories
                </a>
                <a href="manage_users.php" class="btn-admin btn-primary">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="reports.php" class="btn-admin btn-secondary">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
                <a href="settings.php" class="btn-admin btn-secondary">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="manage_special_offers.php" class="btn-admin btn-primary">
                    <i class="fas fa-gift"></i> Special Offers
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Search orders function
        function searchOrders(query) {
            if (query.length < 2) {
                document.getElementById('search-results').innerHTML = '';
                return;
            }
            
            fetch(`../api/search_orders.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    let resultsHTML = '';
                    if (data.success && data.orders.length > 0) {
                        resultsHTML = `
                            <div style="margin-top: 20px;">
                                <h4>Search Results (${data.orders.length} found)</h4>
                                <table class="orders-table">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${data.orders.map(order => `
                                            <tr>
                                                <td>${order.order_number}</td>
                                                <td>${order.customer_name || 'Walk-in'}</td>
                                                <td>PKR ${parseFloat(order.total_amount).toFixed(2)}</td>
                                                <td>
                                                    <span class="order-status status-${order.order_status}">
                                                        ${order.order_status.charAt(0).toUpperCase() + order.order_status.slice(1)}
                                                    </span>
                                                </td>
                                                <td>${new Date(order.created_at).toLocaleDateString()}</td>
                                                <td>
                                                    <a href="view_order_details.php?id=${order.id}" class="btn-admin btn-secondary" style="padding: 5px 10px; font-size: 12px;">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `;
                    } else {
                        resultsHTML = '<p style="margin-top: 20px; color: #666;">No orders found matching your search.</p>';
                    }
                    document.getElementById('search-results').innerHTML = resultsHTML;
                })
                .catch(error => {
                    console.error('Error searching orders:', error);
                    document.getElementById('search-results').innerHTML = '<p style="margin-top: 20px; color: #dc3545;">Error searching orders.</p>';
                });
        }
        
        // Auto-refresh dashboard every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html> 