<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
requireAdmin();

// Get date range from request
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get sales statistics
$query = "SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value,
            COUNT(DISTINCT user_id) as unique_users
          FROM orders 
          WHERE DATE(created_at) BETWEEN ? AND ?";
$stmt = $db->prepare($query);
$stmt->execute([$start_date, $end_date]);
$sales_stats = $stmt->fetch();

// Get top selling items
$query = "SELECT 
            oi.item_name,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.total_price) as total_revenue
          FROM order_items oi
          JOIN orders o ON oi.order_id = o.id
          WHERE DATE(o.created_at) BETWEEN ? AND ?
          GROUP BY oi.item_name
          ORDER BY total_quantity DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$start_date, $end_date]);
$top_items = $stmt->fetchAll();

// Get daily sales
$query = "SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            SUM(total_amount) as revenue
          FROM orders 
          WHERE DATE(created_at) BETWEEN ? AND ?
          GROUP BY DATE(created_at)
          ORDER BY date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$start_date, $end_date]);
$daily_sales = $stmt->fetchAll();

// Get category performance
$query = "SELECT 
            c.name as category_name,
            COUNT(oi.id) as item_count,
            SUM(oi.total_price) as category_revenue
          FROM order_items oi
          JOIN orders o ON oi.order_id = o.id
          JOIN items i ON oi.item_id = i.id
          JOIN categories c ON i.category_id = c.id
          WHERE DATE(o.created_at) BETWEEN ? AND ?
          GROUP BY c.id, c.name
          ORDER BY category_revenue DESC";
$stmt = $db->prepare($query);
$stmt->execute([$start_date, $end_date]);
$category_performance = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Fast Food POS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .btn-admin {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary { background: #20bf55; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
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
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>📊 Sales Reports</h1>
                <p>Analytics and performance insights</p>
            </div>
            <div>
                <a href="index.php" class="btn-admin btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Date Filter -->
        <div class="date-filter">
            <form method="GET">
                <label>Date Range:</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                <span>to</span>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                <button type="submit" class="btn-admin btn-primary">Apply Filter</button>
                <a href="reports.php" class="btn-admin btn-secondary" style="margin-left: 10px;">Clear Filter</a>
            </form>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($sales_stats['total_orders']); ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">PKR <?php echo number_format($sales_stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">PKR <?php echo number_format($sales_stats['avg_order_value'], 2); ?></div>
                <div class="stat-label">Average Order Value</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($sales_stats['unique_users']); ?></div>
                <div class="stat-label">Active Users</div>
            </div>
        </div>
        
        <!-- Daily Sales Chart -->
        <div class="report-section">
            <h2><i class="fas fa-chart-line"></i> Daily Sales Trend</h2>
            <div class="chart-container">
                <canvas id="dailySalesChart"></canvas>
            </div>
        </div>
        
        <!-- Top Selling Items -->
        <div class="report-section">
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
                    <tbody>
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
        
        <!-- Category Performance -->
        <div class="report-section">
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
                    <tbody>
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
        
        <!-- Export Options -->
        <div class="report-section">
            <h2><i class="fas fa-download"></i> Export Reports</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn-admin btn-primary" onclick="exportReport('sales')">
                    <i class="fas fa-file-excel"></i> Export Sales Report
                </button>
                <button class="btn-admin btn-primary" onclick="exportReport('items')">
                    <i class="fas fa-file-excel"></i> Export Items Report
                </button>
                <button class="btn-admin btn-primary" onclick="exportReport('categories')">
                    <i class="fas fa-file-excel"></i> Export Categories Report
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Daily Sales Chart
        const dailySalesData = <?php echo json_encode($daily_sales); ?>;
        const ctx = document.getElementById('dailySalesChart').getContext('2d');
        
        new Chart(ctx, {
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
        
        function exportReport(type) {
            const startDate = '<?php echo $start_date; ?>';
            const endDate = '<?php echo $end_date; ?>';
            
            // Create CSV content based on type
            let csvContent = '';
            let filename = '';
            
            switch(type) {
                case 'sales':
                    csvContent = 'Date,Orders,Revenue\n';
                    dailySalesData.forEach(item => {
                        csvContent += `${item.date},${item.orders},${item.revenue}\n`;
                    });
                    filename = `sales_report_${startDate}_to_${endDate}.csv`;
                    break;
                    
                case 'items':
                    csvContent = 'Item Name,Quantity Sold,Revenue,Percentage\n';
                    <?php foreach ($top_items as $item): ?>
                    csvContent += `<?php echo addslashes($item['item_name']); ?>,<?php echo $item['total_quantity']; ?>,<?php echo $item['total_revenue']; ?>,<?php echo ($item['total_revenue'] / $total_revenue) * 100; ?>\n`;
                    <?php endforeach; ?>
                    filename = `items_report_${startDate}_to_${endDate}.csv`;
                    break;
                    
                case 'categories':
                    csvContent = 'Category,Items Sold,Revenue,Percentage\n';
                    <?php foreach ($category_performance as $category): ?>
                    csvContent += `<?php echo addslashes($category['category_name']); ?>,<?php echo $category['item_count']; ?>,<?php echo $category['category_revenue']; ?>,<?php echo ($category['category_revenue'] / $total_category_revenue) * 100; ?>\n`;
                    <?php endforeach; ?>
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
        }
    </script>
</body>
</html> 