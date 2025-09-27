<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$page_title = "Reports";

// Handle AJAX requests for dynamic filtering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'filter_reports') {
    // Suppress any output that might interfere with JSON
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $_POST['end_date'] ?? date('Y-m-d');
        
        // Get branch ID for filtering
        $branch_id = $_SESSION['branch_id'] ?? null;
    
        // Get sales statistics (branch-specific)
    $query = "SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order_value,
                COUNT(DISTINCT user_id) as unique_users
              FROM orders 
              WHERE DATE(created_at) BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    if ($branch_id) {
        $query .= " AND branch_id = ?";
        $params[] = $branch_id;
    }
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $sales_stats = $stmt->fetch();

    // Get top selling items (branch-specific)
    $query = "SELECT 
                oi.item_name,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.total_price) as total_revenue
              FROM order_items oi
              JOIN orders o ON oi.order_id = o.id
              WHERE DATE(o.created_at) BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    if ($branch_id) {
        $query .= " AND o.branch_id = ?";
        $params[] = $branch_id;
    }
    $query .= " GROUP BY oi.item_name ORDER BY total_quantity DESC LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $top_items = $stmt->fetchAll();

    // Get daily sales (branch-specific)
    $query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as orders,
                SUM(total_amount) as revenue
              FROM orders 
              WHERE DATE(created_at) BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    if ($branch_id) {
        $query .= " AND branch_id = ?";
        $params[] = $branch_id;
    }
    $query .= " GROUP BY DATE(created_at) ORDER BY date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $daily_sales = $stmt->fetchAll();

    // Get category performance (branch-specific)
    $query = "SELECT 
                c.name as category_name,
                COUNT(oi.id) as item_count,
                SUM(oi.total_price) as category_revenue
              FROM order_items oi
              JOIN orders o ON oi.order_id = o.id
              JOIN items i ON oi.item_id = i.id
              JOIN categories c ON i.category_id = c.id
              WHERE DATE(o.created_at) BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    if ($branch_id) {
        $query .= " AND o.branch_id = ?";
        $params[] = $branch_id;
    }
    $query .= " GROUP BY c.id, c.name ORDER BY category_revenue DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $category_performance = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'sales_stats' => $sales_stats,
            'top_items' => $top_items,
            'daily_sales' => $daily_sales,
            'category_performance' => $category_performance
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error loading data: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Get date range from request (for initial load)
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get branch ID for filtering
$branch_id = $_SESSION['branch_id'] ?? null;

// Get sales statistics (branch-specific)
$query = "SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value,
            COUNT(DISTINCT user_id) as unique_users
          FROM orders 
          WHERE DATE(created_at) BETWEEN ? AND ?";
$params = [$start_date, $end_date];
if ($branch_id) {
    $query .= " AND branch_id = ?";
    $params[] = $branch_id;
}
$stmt = $db->prepare($query);
$stmt->execute($params);
$sales_stats = $stmt->fetch();

// Get top selling items (branch-specific)
$query = "SELECT 
            oi.item_name,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.total_price) as total_revenue
          FROM order_items oi
          JOIN orders o ON oi.order_id = o.id
          WHERE DATE(o.created_at) BETWEEN ? AND ?";
$params = [$start_date, $end_date];
if ($branch_id) {
    $query .= " AND o.branch_id = ?";
    $params[] = $branch_id;
}
$query .= " GROUP BY oi.item_name ORDER BY total_quantity DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute($params);
$top_items = $stmt->fetchAll();

// Get daily sales (branch-specific)
$query = "SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            SUM(total_amount) as revenue
          FROM orders 
          WHERE DATE(created_at) BETWEEN ? AND ?";
$params = [$start_date, $end_date];
if ($branch_id) {
    $query .= " AND branch_id = ?";
    $params[] = $branch_id;
}
$query .= " GROUP BY DATE(created_at) ORDER BY date DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$daily_sales = $stmt->fetchAll();

// Get category performance (branch-specific)
$query = "SELECT 
            c.name as category_name,
            COUNT(oi.id) as item_count,
            SUM(oi.total_price) as category_revenue
          FROM order_items oi
          JOIN orders o ON oi.order_id = o.id
          JOIN items i ON oi.item_id = i.id
          JOIN categories c ON i.category_id = c.id
          WHERE DATE(o.created_at) BETWEEN ? AND ?";
$params = [$start_date, $end_date];
if ($branch_id) {
    $query .= " AND o.branch_id = ?";
    $params[] = $branch_id;
}
$query .= " GROUP BY c.id, c.name ORDER BY category_revenue DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$category_performance = $stmt->fetchAll();

include 'includes/header.php';
?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #20bf55;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .report-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .report-section h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }
        
        .date-filter {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .date-filter form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-filter input {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-buttons .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .action-buttons .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        }
        
        .action-buttons .btn-primary {
            background: linear-gradient(135deg, #20bf55, #28a745);
            color: white;
        }
        
        .action-buttons .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .reports-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .reports-table th,
        .reports-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .reports-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .date-filter form {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            
            .date-filter input {
                width: 100%;
            }
        }
        
        /* Dark mode adjustments */
        .dark-mode .action-buttons .btn-primary {
            background: linear-gradient(135deg, #20bf55, #28a745);
        }
        
        .dark-mode .action-buttons .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
        }
    </style>
</head>
<body>
    <div class="admin-section">
        <div class="page-header">
            <div>
                <h2>📊 Sales Reports</h2>
                <?php if (isset($_SESSION['branch_name']) && $_SESSION['branch_name']): ?>
                    <p style="color: #20bf55; margin: 5px 0; font-size: 1.1em;">📍 <?php echo htmlspecialchars($_SESSION['branch_name']); ?> Branch</p>
                <?php endif; ?>
                <p>Analytics and performance insights</p>
            </div>
        </div>
        
        <div class="admin-section">
            <h3><i class="fas fa-filter"></i> Date Filter</h3>
            <div class="date-filter">
                <form id="filter-form">
                    <label>Date Range:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    <span>to</span>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    <div class="action-buttons" style="margin-top: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filter
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="clearFilter()">
                            <i class="fas fa-times"></i> Clear Filter
                        </button>
                    </div>
                </form>
                <div id="loading-indicator" style="display: none; text-align: center; margin-top: 10px;">
                    <i class="fas fa-spinner fa-spin"></i> Loading data...
                </div>
            </div>
        </div>
        
        <div class="admin-section">
            <h3><i class="fas fa-chart-bar"></i> Statistics Overview</h3>
            <div class="stats-grid" id="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="total-orders"><?php echo number_format($sales_stats['total_orders']); ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="total-revenue">PKR <?php echo number_format($sales_stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="avg-order-value">PKR <?php echo number_format($sales_stats['avg_order_value'], 2); ?></div>
                <div class="stat-label">Average Order Value</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="unique-users"><?php echo number_format($sales_stats['unique_users']); ?></div>
                <div class="stat-label">Active Users</div>
            </div>
        </div>
        </div>
        
        <div class="admin-section">
            <h2><i class="fas fa-chart-line"></i> Daily Sales Trend</h2>
            <div class="chart-container">
                <canvas id="dailySalesChart"></canvas>
            </div>
        </div>
        
        <div class="admin-section">
            <h2><i class="fas fa-trophy"></i> Top Selling Items</h2>
            <div class="table-responsive">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Quantity Sold</th>
                            <th>Revenue</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody id="top-items-tbody">
                        <?php 
                        $total_revenue = array_sum(array_column($top_items, 'total_revenue'));
                        foreach ($top_items as $item): 
                            $percentage = $total_revenue > 0 ? ($item['total_revenue'] / $total_revenue) * 100 : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td><?php echo number_format($item['total_quantity']); ?></td>
                            <td>PKR <?php echo number_format($item['total_revenue'], 2); ?></td>
                            <td><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="admin-section">
            <h2><i class="fas fa-tags"></i> Category Performance</h2>
            <div class="table-responsive">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Items Sold</th>
                            <th>Revenue</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody id="category-performance-tbody">
                        <?php 
                        $total_category_revenue = array_sum(array_column($category_performance, 'category_revenue'));
                        foreach ($category_performance as $category): 
                            $percentage = $total_category_revenue > 0 ? ($category['category_revenue'] / $total_category_revenue) * 100 : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                            <td><?php echo number_format($category['item_count']); ?></td>
                            <td>PKR <?php echo number_format($category['category_revenue'], 2); ?></td>
                            <td><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="admin-section">
            <h3><i class="fas fa-download"></i> Export Reports</h3>
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="exportReport('sales')">
                    <i class="fas fa-file-excel"></i> Export Sales Report
                </button>
                <button class="btn btn-primary" onclick="exportReport('items')">
                    <i class="fas fa-file-excel"></i> Export Items Report
                </button>
                <button class="btn btn-primary" onclick="exportReport('categories')">
                    <i class="fas fa-file-excel"></i> Export Categories Report
                </button>
            </div>
        </div>
    </div>
    </div>
    
    <script>
        // Cache busting timestamp: <?php echo time(); ?>
        
        // Global variables
        let dailySalesChart;
        let dailySalesData = <?php echo json_encode($daily_sales); ?>;
        
        // Initialize the chart
        function initializeChart() {
            const ctx = document.getElementById('dailySalesChart').getContext('2d');
            dailySalesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dailySalesData.map(item => item.date),
                datasets: [{
                    label: 'Revenue (PKR)',
                    data: dailySalesData.map(item => item.revenue),
                    borderColor: '#20bf55',
                    backgroundColor: 'rgba(32, 191, 85, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Orders',
                    data: dailySalesData.map(item => item.orders),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (PKR)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Orders'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
            });
        }
        
        // Initialize filter form
        function initializeFilterForm() {
            document.getElementById('filter-form').addEventListener('submit', function(e) {
                e.preventDefault();
                applyFilter();
            });
        }
        
        // Apply filter with AJAX
        function applyFilter() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const loadingIndicator = document.getElementById('loading-indicator');
            
            // Show loading indicator
            loadingIndicator.style.display = 'block';
            
            // Make AJAX request
            fetch('reports.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=filter_reports&start_date=${startDate}&end_date=${endDate}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Response is not valid JSON:', text);
                        throw new Error('Server returned invalid JSON response');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    updateStatistics(data.sales_stats);
                    updateTopItemsTable(data.top_items);
                    updateCategoryPerformanceTable(data.category_performance);
                    updateChart(data.daily_sales);
                    updateExportData(data);
                    showNotification('Data updated successfully', 'success');
                } else {
                    showNotification(data.message || 'Error loading data', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error loading data: ' + error.message, 'error');
            })
            .finally(() => {
                // Hide loading indicator
                loadingIndicator.style.display = 'none';
            });
        }
        
        // Clear filter
        function clearFilter() {
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            applyFilter();
        }
        
        // Update statistics
        function updateStatistics(stats) {
            document.getElementById('total-orders').textContent = Number(stats.total_orders).toLocaleString();
            document.getElementById('total-revenue').textContent = 'PKR ' + Number(stats.total_revenue).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('avg-order-value').textContent = 'PKR ' + Number(stats.avg_order_value).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('unique-users').textContent = Number(stats.unique_users).toLocaleString();
        }
        
        // Update top items table
        function updateTopItemsTable(items) {
            const tbody = document.getElementById('top-items-tbody');
            const totalRevenue = items.reduce((sum, item) => sum + parseFloat(item.total_revenue), 0);
            
            tbody.innerHTML = '';
            items.forEach(item => {
                const percentage = totalRevenue > 0 ? (parseFloat(item.total_revenue) / totalRevenue) * 100 : 0;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.item_name}</td>
                    <td>${Number(item.total_quantity).toLocaleString()}</td>
                    <td>PKR ${Number(item.total_revenue).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td>${percentage.toFixed(1)}%</td>
                `;
                tbody.appendChild(row);
            });
        }
        
        // Update category performance table
        function updateCategoryPerformanceTable(categories) {
            const tbody = document.getElementById('category-performance-tbody');
            const totalCategoryRevenue = categories.reduce((sum, category) => sum + parseFloat(category.category_revenue), 0);
            
            tbody.innerHTML = '';
            categories.forEach(category => {
                const percentage = totalCategoryRevenue > 0 ? (parseFloat(category.category_revenue) / totalCategoryRevenue) * 100 : 0;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${category.category_name}</td>
                    <td>${Number(category.item_count).toLocaleString()}</td>
                    <td>PKR ${Number(category.category_revenue).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td>${percentage.toFixed(1)}%</td>
                `;
                tbody.appendChild(row);
            });
        }
        
        // Update chart
        function updateChart(dailySales) {
            dailySalesData = dailySales;
            dailySalesChart.data.labels = dailySales.map(item => item.date);
            dailySalesChart.data.datasets[0].data = dailySales.map(item => item.revenue);
            dailySalesChart.data.datasets[1].data = dailySales.map(item => item.orders);
            dailySalesChart.update();
        }
        
        // Update export data
        function updateExportData(data) {
            window.currentExportData = data;
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => notification.remove());
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 600;
                z-index: 10000;
                max-width: 400px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                transform: translateX(100%);
                transition: transform 0.3s ease;
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#3b82f6'};
            `;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                ${message}
                <button onclick="this.parentElement.remove()" style="
                    background: none;
                    border: none;
                    color: white;
                    font-size: 18px;
                    cursor: pointer;
                    margin-left: auto;
                    padding: 0;
                    width: 20px;
                    height: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">&times;</button>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (notification.parentElement) {
                            notification.remove();
                        }
                    }, 300);
                }
            }, 3000);
        }
        
        function exportReport(type) {
            const startDate = document.getElementById('start_date').value || '<?php echo $start_date; ?>';
            const endDate = document.getElementById('end_date').value || '<?php echo $end_date; ?>';
            
            // Use current data if available, otherwise use initial data
            const data = window.currentExportData || {
                daily_sales: dailySalesData,
                top_items: <?php echo json_encode($top_items); ?>,
                category_performance: <?php echo json_encode($category_performance); ?>
            };
            
            // Create CSV content based on type
            let csvContent = '';
            let filename = '';
            
            switch(type) {
                case 'sales':
                    csvContent = 'Date,Orders,Revenue\n';
                    data.daily_sales.forEach(item => {
                        csvContent += `${item.date},${item.orders},${item.revenue}\n`;
                    });
                    filename = `sales_report_${startDate}_to_${endDate}.csv`;
                    break;
                    
                case 'items':
                    csvContent = 'Item Name,Quantity Sold,Revenue,Percentage\n';
                    const totalRevenue = data.top_items.reduce((sum, item) => sum + parseFloat(item.total_revenue), 0);
                    data.top_items.forEach(item => {
                        const percentage = totalRevenue > 0 ? (parseFloat(item.total_revenue) / totalRevenue) * 100 : 0;
                        csvContent += `${item.item_name},${item.total_quantity},${item.total_revenue},${percentage.toFixed(1)}%\n`;
                    });
                    filename = `items_report_${startDate}_to_${endDate}.csv`;
                    break;
                    
                case 'categories':
                    csvContent = 'Category,Items Sold,Revenue,Percentage\n';
                    const totalCategoryRevenue = data.category_performance.reduce((sum, category) => sum + parseFloat(category.category_revenue), 0);
                    data.category_performance.forEach(category => {
                        const percentage = totalCategoryRevenue > 0 ? (parseFloat(category.category_revenue) / totalCategoryRevenue) * 100 : 0;
                        csvContent += `${category.category_name},${category.item_count},${category.category_revenue},${percentage.toFixed(1)}%\n`;
                    });
                    filename = `categories_report_${startDate}_to_${endDate}.csv`;
                    break;
            }
            
            // Download CSV file
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            window.URL.revokeObjectURL(url);
            
            showNotification('Report exported successfully', 'success');
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            try {
                initializeChart();
                initializeFilterForm();
                console.log('Reports page initialized successfully');
            } catch (error) {
                console.error('Error initializing reports page:', error);
            }
        });
        
        // Make functions globally accessible
        window.clearFilter = clearFilter;
        window.applyFilter = applyFilter;
        window.exportReport = exportReport;
    </script>
<?php include 'includes/footer.php'; ?> 