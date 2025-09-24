<?php
/**
 * View Orders - Admin Panel
 * Fast Food POS System
 */

require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin or cashier
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'cashier')) {
    header('Location: ../login.php');
    exit;
}

// Get filter parameters
$status = $_GET['status'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($status) {
    $whereConditions[] = "o.order_status = ?";
    $params[] = $status;
}

if ($from_date && $to_date) {
    $whereConditions[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
} elseif ($from_date) {
    $whereConditions[] = "DATE(o.created_at) >= ?";
    $params[] = $from_date;
} elseif ($to_date) {
    $whereConditions[] = "DATE(o.created_at) <= ?";
    $params[] = $to_date;
}

if ($search) {
    $whereConditions[] = "(o.order_number LIKE ? OR c.name LIKE ? OR c.contact LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Get orders
$query = "SELECT o.*, u.name as user_name, c.name as customer_name, c.contact as customer_phone,
          (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          LEFT JOIN customers c ON o.customer_id = c.id 
          $whereClause
          ORDER BY o.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsWhereConditions = [];
$statsParams = [];

if ($from_date && $to_date) {
    $statsWhereConditions[] = "DATE(created_at) BETWEEN ? AND ?";
    $statsParams[] = $from_date;
    $statsParams[] = $to_date;
} elseif ($from_date) {
    $statsWhereConditions[] = "DATE(created_at) >= ?";
    $statsParams[] = $from_date;
} elseif ($to_date) {
    $statsWhereConditions[] = "DATE(created_at) <= ?";
    $statsParams[] = $to_date;
} else {
    $statsWhereConditions[] = "DATE(created_at) = CURDATE()";
}

$statsWhereClause = '';
if (!empty($statsWhereConditions)) {
    $statsWhereClause = 'WHERE ' . implode(' AND ', $statsWhereConditions);
}

$statsQuery = "SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    COUNT(CASE WHEN order_status = 'pending' THEN 1 END) as pending_orders,
    COUNT(CASE WHEN order_status = 'completed' THEN 1 END) as completed_orders
    FROM orders 
    $statsWhereClause";

$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders - Admin Panel</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #20bf55 0%, #01baef 100%);
            color: white;
            padding: 30px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8fafc;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #20bf55;
        }
        
        .stat-card h3 {
            font-size: 2.5em;
            color: #20bf55;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .stat-card p {
            color: #64748b;
            font-size: 1.1em;
            font-weight: 600;
        }
        
        .filters {
            padding: 30px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input,
        .form-group select {
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #20bf55;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #20bf55, #01baef);
            color: white;
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .orders-table {
            padding: 30px;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        th {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            font-weight: 700;
            color: #374151;
            position: sticky;
            top: 0;
        }
        
        tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-view {
            background: #3b82f6;
            color: white;
        }
        
        .btn-print {
            background: #10b981;
            color: white;
        }
        
        .btn-edit {
            background: #f59e0b;
            color: white;
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #d1d5db;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            text-decoration: none;
            color: #374151;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover,
        .pagination a.active {
            background: #20bf55;
            color: white;
            border-color: #20bf55;
        }
        
        .date-presets {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .date-presets .btn {
            padding: 8px 16px;
            font-size: 12px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .date-presets .btn:hover {
            background: #20bf55;
            color: white;
            border-color: #20bf55;
            transform: translateY(-1px);
        }
        
        .filter-form .form-group:last-child {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            justify-content: flex-start;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                font-size: 12px;
            }
            
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div>
                    <h1><i class="fas fa-receipt"></i> Order Management</h1>
                    <p>View and manage all orders in the system</p>
                </div>
                <div class="header-actions">
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Admin Dashboard
                        </a>
                    <?php else: ?>
                        <a href="cashier_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Cashier Dashboard
                        </a>
                    <?php endif; ?>
                    <a href="../index.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> Back to POS
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Filter Status -->
        <?php if ($from_date || $to_date || $status || $search): ?>
        <div style="background: #f8fafc; padding: 15px 30px; border-bottom: 1px solid #e2e8f0;">
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <span style="font-weight: 600; color: #374151;">Active Filters:</span>
                <?php if ($from_date && $to_date): ?>
                    <span class="status-badge" style="background: #dbeafe; color: #1e40af;">
                        Date: <?php echo date('M d, Y', strtotime($from_date)); ?> - <?php echo date('M d, Y', strtotime($to_date)); ?>
                    </span>
                <?php elseif ($from_date): ?>
                    <span class="status-badge" style="background: #dbeafe; color: #1e40af;">
                        From: <?php echo date('M d, Y', strtotime($from_date)); ?>
                    </span>
                <?php elseif ($to_date): ?>
                    <span class="status-badge" style="background: #dbeafe; color: #1e40af;">
                        Until: <?php echo date('M d, Y', strtotime($to_date)); ?>
                    </span>
                <?php endif; ?>
                <?php if ($status): ?>
                    <span class="status-badge" style="background: #fef3c7; color: #92400e;">
                        Status: <?php echo ucfirst($status); ?>
                    </span>
                <?php endif; ?>
                <?php if ($search): ?>
                    <span class="status-badge" style="background: #e0e7ff; color: #3730a3;">
                        Search: "<?php echo htmlspecialchars($search); ?>"
                    </span>
                <?php endif; ?>
                <a href="view_orders.php" style="margin-left: auto; color: #ef4444; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-times"></i> Clear All
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_orders']); ?></h3>
                <p><?php 
                    if ($from_date && $to_date) {
                        echo 'Orders (' . date('M d', strtotime($from_date)) . ' - ' . date('M d', strtotime($to_date)) . ')';
                    } elseif ($from_date) {
                        echo 'Orders (From ' . date('M d', strtotime($from_date)) . ')';
                    } elseif ($to_date) {
                        echo 'Orders (Until ' . date('M d', strtotime($to_date)) . ')';
                    } else {
                        echo "Today's Orders";
                    }
                ?></p>
            </div>
            <div class="stat-card">
                <h3>PKR <?php echo number_format($stats['total_revenue'], 2); ?></h3>
                <p><?php 
                    if ($from_date && $to_date) {
                        echo 'Revenue (' . date('M d', strtotime($from_date)) . ' - ' . date('M d', strtotime($to_date)) . ')';
                    } elseif ($from_date) {
                        echo 'Revenue (From ' . date('M d', strtotime($from_date)) . ')';
                    } elseif ($to_date) {
                        echo 'Revenue (Until ' . date('M d', strtotime($to_date)) . ')';
                    } else {
                        echo "Today's Revenue";
                    }
                ?></p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['pending_orders']); ?></h3>
                <p><?php 
                    if ($from_date || $to_date) {
                        echo 'Pending Orders';
                    } else {
                        echo "Today's Pending";
                    }
                ?></p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['completed_orders']); ?></h3>
                <p><?php 
                    if ($from_date || $to_date) {
                        echo 'Completed Orders';
                    } else {
                        echo "Today's Completed";
                    }
                ?></p>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search">Search Orders</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Order number, customer, or cashier...">
                </div>
                <div class="form-group">
                    <label for="status">Order Status</label>
                    <select id="status" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="from_date">From Date</label>
                    <input type="date" id="from_date" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
                </div>
                <div class="form-group">
                    <label for="to_date">To Date</label>
                    <input type="date" id="to_date" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
                </div>
                <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="view_orders.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
            </form>
        </div>
        
        <!-- Orders Table -->
        <div class="orders-table">
            <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Orders Found</h3>
                <p>No orders match your current filters.</p>
            </div>
            <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Cashier</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                <?php if ($order['table_number']): ?>
                                <br><small>Table <?php echo htmlspecialchars($order['table_number']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                <br><small><?php echo date('H:i', strtotime($order['created_at'])); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($order['customer_name'] ?? 'Walk-in Customer'); ?>
                                <?php if ($order['customer_phone']): ?>
                                <br><small><?php echo htmlspecialchars($order['customer_phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($order['user_name'] ?? 'Admin'); ?></td>
                            <td>
                                <span class="status-badge"><?php echo $order['item_count']; ?> items</span>
                            </td>
                            <td>
                                <strong>PKR <?php echo number_format($order['total_amount'], 2); ?></strong>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $order['order_status']; ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge">
                                    <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_order_details.php?id=<?php echo $order['id']; ?>" 
                                       class="btn btn-sm btn-view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="../print_receipt.php?order_id=<?php echo $order['id']; ?>" 
                                       target="_blank" class="btn btn-sm btn-print" title="Print Receipt">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <button onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'completed')" 
                                            class="btn btn-sm" style="background: #10b981; color: white;" title="Mark as Completed">
                                        <i class="fas fa-check-double"></i>
                                    </button>
                                    <button onclick="quickUpdateStatus(<?php echo $order['id']; ?>, 'cancelled')" 
                                            class="btn btn-sm" style="background: #ef4444; color: white;" title="Cancel Order">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                    <button onclick="deleteOrder(<?php echo $order['id']; ?>)" 
                                            class="btn btn-sm btn-delete" title="Delete Order">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function deleteOrder(orderId) {
            if (confirm('Are you sure you want to delete this order? This action cannot be undone.')) {
                fetch('delete_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ order_id: orderId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Order deleted successfully');
                        location.reload();
                    } else {
                        alert('Error deleting order: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting order');
                });
            }
        }
        
        function quickUpdateStatus(orderId, status) {
            const statusNames = {
                'completed': 'Completed',
                'cancelled': 'Cancelled'
            };
            
            if (confirm(`Are you sure you want to mark this order as ${statusNames[status]}?`)) {
                fetch('update_order_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        order_id: orderId,
                        status: status 
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Order status updated to ${statusNames[status]} successfully!`);
                        location.reload();
                    } else {
                        alert('Error updating order status: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating order status');
                });
            }
        }
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
        
        // Enhanced date filtering functionality
        document.addEventListener('DOMContentLoaded', function() {
            const fromDateInput = document.getElementById('from_date');
            const toDateInput = document.getElementById('to_date');
            
            // Add quick date presets
            addQuickDatePresets();
            
            // Add date validation
            if (fromDateInput && toDateInput) {
                fromDateInput.addEventListener('change', validateDateRange);
                toDateInput.addEventListener('change', validateDateRange);
            }
            
            // Auto-fill today's date if no dates are selected
            if (!fromDateInput.value && !toDateInput.value) {
                const today = new Date().toISOString().split('T')[0];
                fromDateInput.value = today;
                toDateInput.value = today;
            }
        });
        
        function validateDateRange() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            
            if (fromDate && toDate && fromDate > toDate) {
                alert('From Date cannot be later than To Date');
                document.getElementById('to_date').value = fromDate;
            }
        }
        
        function addQuickDatePresets() {
            const filtersDiv = document.querySelector('.filters');
            if (!filtersDiv) return;
            
            const presetsDiv = document.createElement('div');
            presetsDiv.className = 'date-presets';
            
            const presets = [
                { label: 'Today', days: 0 },
                { label: 'Yesterday', days: -1 },
                { label: 'Last 7 Days', days: -7 },
                { label: 'Last 30 Days', days: -30 },
                { label: 'This Month', days: 'month' },
                { label: 'Last Month', days: 'last_month' }
            ];
            
            presets.forEach(preset => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-secondary';
                button.textContent = preset.label;
                button.onclick = () => setDateRange(preset.days);
                presetsDiv.appendChild(button);
            });
            
            filtersDiv.appendChild(presetsDiv);
        }
        
        function setDateRange(days) {
            const fromDateInput = document.getElementById('from_date');
            const toDateInput = document.getElementById('to_date');
            const today = new Date();
            
            let fromDate, toDate;
            
            if (days === 'month') {
                // This month
                fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                toDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            } else if (days === 'last_month') {
                // Last month
                fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                toDate = new Date(today.getFullYear(), today.getMonth(), 0);
            } else {
                // Specific number of days
                toDate = new Date(today);
                fromDate = new Date(today);
                fromDate.setDate(today.getDate() + days);
            }
            
            fromDateInput.value = fromDate.toISOString().split('T')[0];
            toDateInput.value = toDate.toISOString().split('T')[0];
            
            // Auto-submit the form
            document.querySelector('.filter-form').submit();
        }
    </script>
</body>
</html> 