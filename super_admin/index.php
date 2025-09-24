<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

// Get dashboard statistics
$query = "SELECT COUNT(*) as total_branches FROM branches WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$total_branches = $stmt->fetch()['total_branches'];

$query = "SELECT COUNT(*) as total_purchases FROM stock_purchases";
$stmt = $db->prepare($query);
$stmt->execute();
$total_purchases = $stmt->fetch()['total_purchases'];

$query = "SELECT COUNT(*) as pending_purchases FROM stock_purchases WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_purchases = $stmt->fetch()['pending_purchases'];

$query = "SELECT COUNT(*) as total_distributions FROM stock_distributions";
$stmt = $db->prepare($query);
$stmt->execute();
$total_distributions = $stmt->fetch()['total_distributions'];

$query = "SELECT COUNT(*) as pending_distributions FROM stock_distributions WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_distributions = $stmt->fetch()['pending_distributions'];

$query = "SELECT COUNT(*) as unread_notifications FROM notifications WHERE is_read = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$unread_notifications = $stmt->fetch()['unread_notifications'];

$query = "SELECT COUNT(*) as low_stock_alerts FROM stock_alerts WHERE is_resolved = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$low_stock_alerts = $stmt->fetch()['low_stock_alerts'];

// Get recent activities
$query = "SELECT 'purchase' as type, purchase_number as number, created_at, 'New Purchase Order' as description 
          FROM stock_purchases 
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          UNION ALL
          SELECT 'distribution' as type, distribution_number as number, created_at, 'Stock Distribution' as description 
          FROM stock_distributions 
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          ORDER BY created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_activities = $stmt->fetchAll();

$page_title = "Dashboard";
include 'includes/header.php';
?>

<!-- Statistics -->
<div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; margin-bottom: 30px;" class="stats-grid">
    <div class="card" style="padding: 15px; text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2em; color: white; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                <i class="fas fa-building"></i>
            </div>
        </div>
        <div style="font-size: 2em; font-weight: 700; margin-bottom: 5px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo $total_branches; ?></div>
        <div style="color: var(--light-text); font-size: 0.8em; opacity: 0.8;">Active Branches</div>
    </div>
    
    <div class="card" style="padding: 15px; text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2em; color: white; background: linear-gradient(135deg, var(--success-color), #16a34a);">
                <i class="fas fa-shopping-cart"></i>
            </div>
        </div>
        <div style="font-size: 2em; font-weight: 700; margin-bottom: 5px; background: linear-gradient(135deg, var(--success-color), #16a34a); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo $total_purchases; ?></div>
        <div style="color: var(--light-text); font-size: 0.8em; opacity: 0.8;">Total Purchases</div>
    </div>
    
    <div class="card" style="padding: 15px; text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2em; color: white; background: linear-gradient(135deg, var(--warning-color), #f59e0b);">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div style="font-size: 2em; font-weight: 700; margin-bottom: 5px; background: linear-gradient(135deg, var(--warning-color), #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo $pending_purchases; ?></div>
        <div style="color: var(--light-text); font-size: 0.8em; opacity: 0.8;">Pending Purchases</div>
    </div>
    
    <div class="card" style="padding: 15px; text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2em; color: white; background: linear-gradient(135deg, var(--info-color), #3b82f6);">
                <i class="fas fa-truck"></i>
            </div>
        </div>
        <div style="font-size: 2em; font-weight: 700; margin-bottom: 5px; background: linear-gradient(135deg, var(--info-color), #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo $total_distributions; ?></div>
        <div style="color: var(--light-text); font-size: 0.8em; opacity: 0.8;">Stock Distributions</div>
    </div>
    
    <div class="card" style="padding: 15px; text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2em; color: white; background: linear-gradient(135deg, var(--danger-color), #dc2626);">
                <i class="fas fa-bell"></i>
            </div>
        </div>
        <div style="font-size: 2em; font-weight: 700; margin-bottom: 5px; background: linear-gradient(135deg, var(--danger-color), #dc2626); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo $unread_notifications; ?></div>
        <div style="color: var(--light-text); font-size: 0.8em; opacity: 0.8;">Unread Notifications</div>
    </div>
    
    <div class="card" style="padding: 15px; text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2em; color: white; background: linear-gradient(135deg, #f59e0b, #f59e0b);">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
        <div style="font-size: 2em; font-weight: 700; margin-bottom: 5px; background: linear-gradient(135deg, #f59e0b, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo $low_stock_alerts; ?></div>
        <div style="color: var(--light-text); font-size: 0.8em; opacity: 0.8;">Stock Alerts</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-bolt"></i> Quick Actions
        </h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="stock_management.php" class="action-card" style="background: var(--light-bg); border: 1px solid var(--light-border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: var(--light-text);">
            <i class="fas fa-boxes" style="font-size: 2em; margin-bottom: 10px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
            <h3 style="font-size: 1em; font-weight: 600;">Stock Management</h3>
        </a>
        
        <a href="stock_purchases.php" class="action-card" style="background: var(--light-bg); border: 1px solid var(--light-border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: var(--light-text);">
            <i class="fas fa-shopping-cart" style="font-size: 2em; margin-bottom: 10px; background: linear-gradient(135deg, var(--success-color), #16a34a); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
            <h3 style="font-size: 1em; font-weight: 600;">Stock Purchases</h3>
        </a>
        
        <a href="stock_distributions.php" class="action-card" style="background: var(--light-bg); border: 1px solid var(--light-border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: var(--light-text);">
            <i class="fas fa-truck" style="font-size: 2em; margin-bottom: 10px; background: linear-gradient(135deg, var(--info-color), #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
            <h3 style="font-size: 1em; font-weight: 600;">Stock Distribution</h3>
        </a>
        
        <a href="manage_branches.php" class="action-card" style="background: var(--light-bg); border: 1px solid var(--light-border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: var(--light-text);">
            <i class="fas fa-building" style="font-size: 2em; margin-bottom: 10px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
            <h3 style="font-size: 1em; font-weight: 600;">Manage Branches</h3>
        </a>
        
        <a href="notifications.php" class="action-card" style="background: var(--light-bg); border: 1px solid var(--light-border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: var(--light-text);">
            <i class="fas fa-bell" style="font-size: 2em; margin-bottom: 10px; background: linear-gradient(135deg, var(--danger-color), #dc2626); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
            <h3 style="font-size: 1em; font-weight: 600;">Notifications</h3>
        </a>
        
        <a href="stock_reports.php" class="action-card" style="background: var(--light-bg); border: 1px solid var(--light-border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: var(--light-text);">
            <i class="fas fa-chart-bar" style="font-size: 2em; margin-bottom: 10px; background: linear-gradient(135deg, var(--info-color), #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
            <h3 style="font-size: 1em; font-weight: 600;">Stock Reports</h3>
        </a>
        
        <a href="suppliers.php" class="action-card" style="background: var(--light-bg); border: 1px solid var(--light-border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: var(--light-text);">
            <i class="fas fa-truck-loading" style="font-size: 2em; margin-bottom: 10px; background: linear-gradient(135deg, var(--warning-color), #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
            <h3 style="font-size: 1em; font-weight: 600;">Manage Suppliers</h3>
        </a>
        
        <a href="warehouse_stock.php" class="action-card" style="background: var(--light-bg); border: 1px solid var(--light-border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: var(--light-text);">
            <i class="fas fa-warehouse" style="font-size: 2em; margin-bottom: 10px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
            <h3 style="font-size: 1em; font-weight: 600;">Warehouse Stock</h3>
        </a>
        
        <a href="revenue_reconciliation.php" class="action-card" style="background: var(--light-bg); border: 1px solid var(--light-border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: var(--light-text);">
            <i class="fas fa-cash-register" style="font-size: 2em; margin-bottom: 10px; background: linear-gradient(135deg, #20bf55, #01baef); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
            <h3 style="font-size: 1em; font-weight: 600;">Revenue Reconciliation</h3>
        </a>
        
        <a href="shift_schedule.php" class="action-card" style="background: var(--light-bg); border: 1px solid var(--light-border); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s ease; text-decoration: none; color: var(--light-text);">
            <i class="fas fa-calendar-alt" style="font-size: 2em; margin-bottom: 10px; background: linear-gradient(135deg, #ffc107, #fd7e14); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
            <h3 style="font-size: 1em; font-weight: 600;">Shift Schedule</h3>
        </a>
    </div>
</div>

<!-- Recent Activities -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-history"></i> Recent Activities
        </h2>
    </div>
    <?php if (empty($recent_activities)): ?>
        <div style="text-align: center; padding: 40px; color: var(--light-text); opacity: 0.7;">
            <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: 20px;"></i>
            <p>No recent activities</p>
        </div>
    <?php else: ?>
        <?php foreach ($recent_activities as $activity): ?>
            <div style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid var(--light-border); transition: all 0.3s ease;">
                <div style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 1.2em; color: white; background: <?php echo $activity['type'] === 'purchase' ? 'linear-gradient(135deg, var(--success-color), #16a34a)' : 'linear-gradient(135deg, var(--info-color), #3b82f6)'; ?>;">
                    <i class="fas fa-<?php echo $activity['type'] === 'purchase' ? 'shopping-cart' : 'truck'; ?>"></i>
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 5px; color: var(--light-text);"><?php echo htmlspecialchars($activity['description']); ?> - <?php echo htmlspecialchars($activity['number']); ?></div>
                    <div style="font-size: 0.9em; opacity: 0.7; color: var(--light-text);"><?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
    /* Responsive design for statistics */
    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(4, 1fr) !important;
        }
    }
    
    @media (max-width: 900px) {
        .stats-grid {
            grid-template-columns: repeat(3, 1fr) !important;
        }
    }
    
    @media (max-width: 600px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 10px !important;
        }
        
        .stats-grid .card {
            padding: 10px !important;
        }
        
        .stats-grid .card div[style*="font-size: 2em"] {
            font-size: 1.5em !important;
        }
        
        .stats-grid .card div[style*="font-size: 0.8em"] {
            font-size: 0.7em !important;
        }
    }
    
    @media (max-width: 400px) {
        .stats-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<script>
    // Auto-refresh dashboard every 30 seconds
    setInterval(() => {
        location.reload();
    }, 30000);
</script>

<?php include 'includes/footer.php'; ?>
