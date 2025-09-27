<?php
/**
 * Kitchen Display System
 * Fast Food POS System - Real-time Order Tracking
 */

require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$page_title = "Kitchen Display" . (!empty($search) ? " - Search: " . htmlspecialchars($search) : "");

// Get active orders (branch-specific)
$query = "SELECT o.*, u.name as user_name, c.name as customer_name,
          (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          LEFT JOIN customers c ON o.customer_id = c.id 
          WHERE o.order_status IN ('pending', 'preparing', 'ready')";
$params = [];

// Filter by branch if user belongs to a specific branch
if (isset($_SESSION['branch_id']) && $_SESSION['branch_id']) {
    $query .= " AND o.branch_id = ?";
    $params[] = $_SESSION['branch_id'];
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (o.order_number LIKE ? OR u.name LIKE ? OR c.name LIKE ? OR o.table_number LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY o.created_at ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$activeOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>
    <style>
        /* Kitchen Display Page Specific Styles */
        .kitchen-display-container {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            padding: 20px;
            color: white;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 30px;
            background: linear-gradient(135deg, #20bf55 0%, #01baef 100%);
            color: white;
            border-radius: 15px;
        }
        
        .page-header h2 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .page-header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .order-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            color: #333;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            border-color: #20bf55;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .order-number {
            font-size: 1.5em;
            font-weight: bold;
            color: #20bf55;
        }
        
        .order-time {
            font-size: 1.1em;
            color: #666;
        }
        
        .order-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        
        .status-pending {
            background: #f59e0b;
            color: white;
        }
        
        .status-preparing {
            background: #3b82f6;
            color: white;
        }
        
        .status-ready {
            background: #10b981;
            color: white;
        }
        
        .order-info {
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 1.1em;
        }
        
        .info-label {
            color: #666;
            font-weight: 600;
        }
        
        .order-items {
            margin-bottom: 20px;
        }
        
        .item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .item:last-child {
            border-bottom: none;
        }
        
        .item-name {
            font-weight: 500;
            color: #333;
        }
        
        .item-quantity {
            background: #20bf55;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: bold;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 1em;
        }
        
        .btn-primary {
            background: #20bf55;
            color: white;
        }
        
        .btn-secondary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #20bf55;
        }
        
        .refresh-info {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 0.9em;
            padding: 15px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .orders-grid {
                grid-template-columns: 1fr;
            }
            
            .order-card {
                padding: 20px;
            }
            
            .order-actions {
                flex-direction: column;
            }
        }
        
        /* Search Form Styles */
        .search-form {
            margin-bottom: 20px;
        }
        
        .search-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .search-container.image-style {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .search-input-group {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1em;
            background: white;
            transition: all 0.3s ease;
        }
        
        .search-input.image-style {
            padding: 12px 18px;
            font-size: 1.05em;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #20bf55;
            box-shadow: 0 0 0 3px rgba(32, 191, 85, 0.1);
        }
        
        .search-label {
            background: linear-gradient(135deg, #20bf55 0%, #01baef 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1em;
            white-space: nowrap;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(32, 191, 85, 0.2);
        }
        
        .search-label::before {
            content: "🍽️";
            margin-right: 8px;
            font-size: 1.2em;
        }
        
        .search-clear {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .clear-search-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f59e0b;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .clear-search-btn:hover {
            background: #d97706;
            transform: translateY(-1px);
        }
        
        .search-results-count {
            color: #666;
            font-weight: 600;
            font-size: 1em;
        }
        
        /* Mobile Responsive for Search */
        @media (max-width: 768px) {
            .search-input-group {
                flex-direction: column;
                gap: 12px;
            }
            
            .search-input {
                width: 100%;
            }
            
            .search-input.image-style {
                padding: 10px 15px;
                font-size: 1em;
            }
            
            .search-label {
                width: 100%;
                justify-content: center;
                padding: 10px 15px;
                font-size: 1em;
            }
            
            .search-clear {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
            }
            
            .search-container.image-style {
                padding: 12px;
            }
        }
        
        /* Dark Mode Adjustments */
        [data-theme="dark"] .page-header {
            background: linear-gradient(135deg, #27ae60 0%, #3498db 100%);
        }
        
        [data-theme="dark"] .order-card {
            background: rgba(45, 45, 45, 0.95);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .order-header {
            border-bottom-color: var(--border-color);
        }
        
        [data-theme="dark"] .order-time {
            color: var(--text-secondary);
        }
        
        [data-theme="dark"] .info-label {
            color: var(--text-secondary);
        }
        
        [data-theme="dark"] .item {
            border-bottom-color: var(--border-color);
        }
        
        [data-theme="dark"] .item-name {
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .empty-state {
            color: var(--text-secondary);
        }
        
        [data-theme="dark"] .refresh-info {
            background: rgba(45, 45, 45, 0.95);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }
        
        /* Dark Mode Search Form */
        [data-theme="dark"] .search-container {
            background: rgba(45, 45, 45, 0.95);
            border: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .search-input {
            background: var(--bg-secondary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .search-input:focus {
            border-color: #20bf55;
        }
        
        [data-theme="dark"] .search-clear {
            border-top-color: var(--border-color);
        }
        
        [data-theme="dark"] .search-results-count {
            color: var(--text-secondary);
        }
    </style>
    <!-- Page Header -->
    <div class="page-header">
        <h2><i class="fas fa-utensils"></i> Kitchen Display</h2>
        <p>Real-time order tracking for kitchen staff</p>
    </div>
    
    <!-- Search Form -->
    <div class="admin-section">
        <form method="GET" class="search-form">
            <div class="search-container image-style">
                <div class="search-input-group">
                    <input type="text" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search orders..."
                           class="search-input image-style">
                    <span class="search-label">Kitchen Display</span>
                </div>
                <?php if (!empty($search)): ?>
                <div class="search-clear">
                    <a href="kitchen_display.php" class="clear-search-btn">
                        <i class="fas fa-times"></i> Clear
                    </a>
                    <span class="search-results-count">
                        Found <?php echo count($activeOrders); ?> order(s) for "<?php echo htmlspecialchars($search); ?>"
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <?php if (empty($activeOrders)): ?>
    <div class="empty-state">
        <?php if (!empty($search)): ?>
            <i class="fas fa-search"></i>
            <h2>No Orders Found</h2>
            <p>No orders match your search for "<?php echo htmlspecialchars($search); ?>"</p>
            <a href="kitchen_display.php" class="btn btn-primary" style="margin-top: 15px;">
                <i class="fas fa-eye"></i> View All Orders
            </a>
        <?php else: ?>
            <i class="fas fa-check-circle"></i>
            <h2>No Active Orders</h2>
            <p>All orders have been completed or are ready for pickup</p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="orders-grid">
        <?php foreach ($activeOrders as $order): ?>
        <div class="order-card">
            <div class="order-header">
                <div>
                    <div class="order-number"><?php echo htmlspecialchars($order['order_number']); ?></div>
                    <div class="order-time"><?php echo date('H:i', strtotime($order['created_at'])); ?></div>
                </div>
                <span class="order-status status-<?php echo $order['order_status']; ?>">
                    <?php echo ucfirst($order['order_status']); ?>
                </span>
            </div>
            
            <div class="order-info">
                <div class="info-row">
                    <span class="info-label">Customer:</span>
                    <span><?php echo htmlspecialchars($order['customer_name'] ?? 'Walk-in'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Cashier:</span>
                    <span><?php echo htmlspecialchars($order['user_name'] ?? 'Admin'); ?></span>
                </div>
                <?php if ($order['table_number']): ?>
                <div class="info-row">
                    <span class="info-label">Table:</span>
                    <span><?php echo htmlspecialchars($order['table_number']); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Items:</span>
                    <span><?php echo $order['item_count']; ?> items</span>
                </div>
            </div>
            
            <div class="order-items">
                <?php
                $query = "SELECT * FROM order_items WHERE order_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$order['id']]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <?php foreach ($items as $item): ?>
                <div class="item">
                    <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                    <span class="item-quantity">x<?php echo $item['quantity']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="order-actions">
                <?php if ($order['order_status'] === 'pending'): ?>
                <button class="btn btn-primary status-btn" data-order-id="<?php echo (int)$order['id']; ?>" data-status="preparing">
                    <i class="fas fa-utensils"></i> Start Preparing
                </button>
                <?php elseif ($order['order_status'] === 'preparing'): ?>
                <button class="btn btn-warning status-btn" data-order-id="<?php echo (int)$order['id']; ?>" data-status="ready">
                    <i class="fas fa-check-circle"></i> Mark Ready
                </button>
                <?php elseif ($order['order_status'] === 'ready'): ?>
                <button class="btn btn-primary status-btn" data-order-id="<?php echo (int)$order['id']; ?>" data-status="completed">
                    <i class="fas fa-check-double"></i> Mark Completed
                </button>
                <?php endif; ?>
                
                <button class="btn btn-secondary status-btn" data-order-id="<?php echo (int)$order['id']; ?>" data-status="cancelled">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <div class="refresh-info">
        <p>🔄 Auto-refreshing every 30 seconds</p>
    </div>

    <script>
        // Kitchen Display JavaScript - Cache Bust: <?php echo time(); ?>
        console.log('Kitchen Display JavaScript loaded at:', new Date().toISOString());
        
        function updateOrderStatus(orderId, status) {
            console.log('updateOrderStatus called with:', orderId, status);
            
            // Validate parameters
            if (!orderId || !status) {
                console.error('Invalid parameters for updateOrderStatus:', orderId, status);
                return;
            }
            
            const statusNames = {
                'preparing': 'Preparing',
                'ready': 'Ready',
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
                        order_id: parseInt(orderId),
                        status: status 
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(`Order status updated to ${statusNames[status]} successfully!`);
                        location.reload();
                    } else {
                        alert('Error updating order status: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating order status: ' + error.message);
                });
            }
        }
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
        
        // Add event listeners for status buttons and search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const statusButtons = document.querySelectorAll('.status-btn');
            statusButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    const status = this.getAttribute('data-status');
                    console.log('Button clicked:', orderId, status);
                    updateOrderStatus(orderId, status);
                });
            });
            
            // Add search functionality
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                let searchTimeout;
                
                // Auto-submit search on Enter key
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.form.submit();
                    }
                });
                
                // Auto-search as user types (with debounce)
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        if (this.value.trim().length >= 2 || this.value.trim().length === 0) {
                            this.form.submit();
                        }
                    }, 500); // 500ms delay
                });
                
                // Focus search input on page load
                searchInput.focus();
            }
        });
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                location.reload();
            }
        });
    </script>

<?php include 'includes/footer.php'; ?> 