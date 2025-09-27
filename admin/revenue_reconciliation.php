<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$page_title = "Revenue Reconciliation";

// Get current user's branch
$branch_id = $_SESSION['branch_id'] ?? null;
if (!$branch_id) {
    die('No branch assigned to your account. Please contact super admin.');
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'open_cash_drawer':
            $opening_cash = (float)$_POST['opening_cash'];
            
            try {
                $db->beginTransaction();
                
                // Check if drawer is already open for today
                $query = "SELECT id FROM cash_drawers WHERE user_id = ? AND shift_date = CURDATE() AND status = 'open'";
                $stmt = $db->prepare($query);
                $stmt->execute([$_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    throw new Exception('Cash drawer is already open for today');
                }
                
                // Create new cash drawer record
                $query = "INSERT INTO cash_drawers (branch_id, user_id, shift_date, opening_cash, status) VALUES (?, ?, CURDATE(), ?, 'open')";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id, $_SESSION['user_id'], $opening_cash]);
                
                $drawer_id = $db->lastInsertId();
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Cash drawer opened successfully', 'drawer_id' => $drawer_id]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error opening cash drawer: ' . $e->getMessage()]);
            }
            exit();
            
        case 'close_cash_drawer':
            $drawer_id = (int)$_POST['drawer_id'];
            $actual_cash = (float)$_POST['actual_cash'];
            $notes = sanitize($_POST['notes'] ?? '');
            
            try {
                $db->beginTransaction();
                
                // Get drawer details
                $query = "SELECT * FROM cash_drawers WHERE id = ? AND user_id = ? AND status = 'open'";
                $stmt = $db->prepare($query);
                $stmt->execute([$drawer_id, $_SESSION['user_id']]);
                $drawer = $stmt->fetch();
                
                if (!$drawer) {
                    throw new Exception('Cash drawer not found or already closed');
                }
                
                // Calculate expected cash
                $query = "SELECT SUM(total_amount) as total_sales 
                         FROM orders 
                         WHERE cash_drawer_id = ? AND payment_method_id = 1";
                $stmt = $db->prepare($query);
                $stmt->execute([$drawer_id]);
                $cash_sales = $stmt->fetch()['total_sales'] ?? 0;
                
                $expected_cash = $drawer['opening_cash'] + $cash_sales;
                $variance = $actual_cash - $expected_cash;
                
                // Update cash drawer
                $query = "UPDATE cash_drawers SET 
                         closing_cash = ?, 
                         expected_cash = ?, 
                         actual_cash = ?, 
                         variance = ?, 
                         status = 'closed', 
                         closed_at = NOW(),
                         notes = ?
                         WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$actual_cash, $expected_cash, $actual_cash, $variance, $notes, $drawer_id]);
                
                // Create revenue reconciliation record
                $query = "INSERT INTO revenue_reconciliation 
                         (branch_id, cash_drawer_id, reconciliation_date, total_sales, cash_sales, 
                          card_sales, digital_sales, other_sales, opening_cash, expected_cash, 
                          actual_cash, cash_variance, status, notes) 
                         VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
                
                // Get sales breakdown by payment method
                $query_sales = "SELECT 
                               pm.type,
                               COALESCE(SUM(o.total_amount), 0) as total
                               FROM payment_methods pm
                               LEFT JOIN orders o ON pm.id = o.payment_method_id AND o.cash_drawer_id = ?
                               GROUP BY pm.type";
                $stmt = $db->prepare($query_sales);
                $stmt->execute([$drawer_id]);
                $sales_breakdown = [];
                while ($row = $stmt->fetch()) {
                    $sales_breakdown[$row['type']] = $row['total'];
                }
                
                $total_sales = array_sum($sales_breakdown);
                $cash_sales = $sales_breakdown['cash'] ?? 0;
                $card_sales = $sales_breakdown['card'] ?? 0;
                $digital_sales = $sales_breakdown['digital'] ?? 0;
                $other_sales = $sales_breakdown['other'] ?? 0;
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $branch_id, $drawer_id, $total_sales, $cash_sales, $card_sales, 
                    $digital_sales, $other_sales, $drawer['opening_cash'], $expected_cash, 
                    $actual_cash, $variance, $notes
                ]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Cash drawer closed successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error closing cash drawer: ' . $e->getMessage()]);
            }
            exit();
            
        case 'reconcile_revenue':
            $reconciliation_id = (int)$_POST['reconciliation_id'];
            
            try {
                $query = "UPDATE revenue_reconciliation SET 
                         status = 'reconciled', 
                         reconciled_by = ?, 
                         reconciled_at = NOW() 
                         WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$_SESSION['user_id'], $reconciliation_id]);
                
                echo json_encode(['success' => true, 'message' => 'Revenue reconciled successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error reconciling revenue: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_drawer_status':
            try {
                $query = "SELECT cd.*, u.name as user_name 
                         FROM cash_drawers cd
                         JOIN users u ON cd.user_id = u.id
                         WHERE cd.branch_id = ? AND cd.shift_date = CURDATE()
                         ORDER BY cd.opened_at DESC";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id]);
                $drawers = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'drawers' => $drawers]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error getting drawer status: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_reconciliation_data':
            $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
            $end_date = $_POST['end_date'] ?? date('Y-m-d');
            
            try {
                $query = "SELECT rr.*, u.name as reconciled_by_name, cd.user_id as cashier_id, u2.name as cashier_name
                         FROM revenue_reconciliation rr
                         LEFT JOIN users u ON rr.reconciled_by = u.id
                         LEFT JOIN cash_drawers cd ON rr.cash_drawer_id = cd.id
                         LEFT JOIN users u2 ON cd.user_id = u2.id
                         WHERE rr.branch_id = ? AND rr.reconciliation_date BETWEEN ? AND ?
                         ORDER BY rr.reconciliation_date DESC";
                $stmt = $db->prepare($query);
                $stmt->execute([$branch_id, $start_date, $end_date]);
                $reconciliations = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'reconciliations' => $reconciliations]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error getting reconciliation data: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics
$stats = [];

// Today's drawer status
$query = "SELECT COUNT(*) as open_drawers FROM cash_drawers WHERE branch_id = ? AND shift_date = CURDATE() AND status = 'open'";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$stats['open_drawers'] = $stmt->fetch()['open_drawers'] ?? 0;

// Pending reconciliations
$query = "SELECT COUNT(*) as pending FROM revenue_reconciliation WHERE branch_id = ? AND status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$stats['pending_reconciliations'] = $stmt->fetch()['pending'] ?? 0;

// Today's variance
$query = "SELECT SUM(ABS(cash_variance)) as total_variance FROM revenue_reconciliation WHERE branch_id = ? AND reconciliation_date = CURDATE() AND status = 'reconciled'";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$stats['today_variance'] = $stmt->fetch()['total_variance'] ?? 0;

// Current user's drawer
$query = "SELECT * FROM cash_drawers WHERE user_id = ? AND shift_date = CURDATE() AND status = 'open'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$current_drawer = $stmt->fetch();

// Get branch name
$query = "SELECT name FROM branches WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$branch_id]);
$branch_name = $stmt->fetch()['name'] ?? 'Unknown Branch';

include 'includes/header.php';
?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            overflow: auto !important;
            height: auto !important;
            min-height: 100vh;
            background: #f8fafc;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #20bf55 0%, #01baef 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
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
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .admin-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .btn-super {
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
        }
        
        .btn-primary { background: #20bf55; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        
        .btn-super:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 30px;
            border-radius: 15px;
            width: 95%;
            max-width: 600px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #20bf55;
        }
        
        .drawer-status {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .status-open { border-left: 4px solid #28a745; }
        .status-closed { border-left: 4px solid #dc3545; }
        
        .reconciliation-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .reconciliation-table th,
        .reconciliation-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .reconciliation-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .reconciliation-table tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-open { background: #d1fae5; color: #065f46; }
        .status-closed { background: #fee2e2; color: #991b1b; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-reconciled { background: #d1fae5; color: #065f46; }
        .status-discrepancy { background: #fee2e2; color: #991b1b; }
        
        .notification {
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
        }
        
        .notification-success { background: #10b981; }
        .notification-error { background: #ef4444; }
        .notification-warning { background: #f59e0b; }
        .notification-info { background: #3b82f6; }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <h1><i class="fas fa-cash-register"></i> Revenue Reconciliation</h1>
            <p>Cash drawer management and revenue tracking for <?php echo $branch_name; ?></p>
            <div style="margin-top: 20px;">
                <a href="index.php" class="btn-super btn-info">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #20bf55;"><?php echo $stats['open_drawers']; ?></div>
                <div class="stat-label">Open Drawers Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #ffc107;"><?php echo $stats['pending_reconciliations']; ?></div>
                <div class="stat-label">Pending Reconciliations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #dc3545;">PKR <?php echo number_format($stats['today_variance'], 2); ?></div>
                <div class="stat-label">Today's Variance</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #17a2b8;"><?php echo $current_drawer ? 'Open' : 'Closed'; ?></div>
                <div class="stat-label">Your Drawer Status</div>
            </div>
        </div>

        <!-- Current Drawer Status -->
        <?php if ($current_drawer): ?>
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-cash-register"></i> Your Cash Drawer</h2>
                <button class="btn-super btn-danger" onclick="showCloseDrawerModal()">
                    <i class="fas fa-lock"></i> Close Drawer
                </button>
            </div>
            <div class="drawer-status status-open">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <strong>Opening Cash:</strong><br>
                        PKR <?php echo number_format($current_drawer['opening_cash'], 2); ?>
                    </div>
                    <div>
                        <strong>Opened At:</strong><br>
                        <?php echo date('Y-m-d H:i:s', strtotime($current_drawer['opened_at'])); ?>
                    </div>
                    <div>
                        <strong>Status:</strong><br>
                        <span class="status-badge status-open">Open</span>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-cash-register"></i> Cash Drawer Management</h2>
                <button class="btn-super btn-success" onclick="showOpenDrawerModal()">
                    <i class="fas fa-unlock"></i> Open Drawer
                </button>
            </div>
            <p style="text-align: center; color: #666; padding: 20px;">
                Your cash drawer is currently closed. Open it to start your shift.
            </p>
        </div>
        <?php endif; ?>

        <!-- Revenue Reconciliation -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-line"></i> Revenue Reconciliation</h2>
                <div style="display: flex; gap: 10px;">
                    <input type="date" id="start-date" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                    <input type="date" id="end-date" value="<?php echo date('Y-m-d'); ?>">
                    <button class="btn-super btn-info" onclick="loadReconciliationData()">
                        <i class="fas fa-refresh"></i> Load Data
                    </button>
                </div>
            </div>
            <div id="reconciliation-container">
                <p style="text-align: center; color: #666; padding: 40px;">
                    Loading reconciliation data...
                </p>
            </div>
        </div>
    </div>

    <!-- Open Drawer Modal -->
    <div id="open-drawer-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('open-drawer-modal')">&times;</span>
            <h3>Open Cash Drawer</h3>
            
            <form id="open-drawer-form">
                <div class="form-group">
                    <label>Opening Cash Amount (PKR)</label>
                    <input type="number" id="opening-cash" step="0.01" min="0" required>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn-super btn-secondary" onclick="closeModal('open-drawer-modal')">Cancel</button>
                    <button type="submit" class="btn-super btn-success">Open Drawer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Close Drawer Modal -->
    <div id="close-drawer-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('close-drawer-modal')">&times;</span>
            <h3>Close Cash Drawer</h3>
            
            <form id="close-drawer-form">
                <div class="form-group">
                    <label>Actual Cash in Drawer (PKR)</label>
                    <input type="number" id="actual-cash" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea id="close-notes" rows="3" placeholder="Any notes about the closing..."></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn-super btn-secondary" onclick="closeModal('close-drawer-modal')">Cancel</button>
                    <button type="submit" class="btn-super btn-danger">Close Drawer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentDrawerId = <?php echo $current_drawer ? $current_drawer['id'] : 'null'; ?>;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadReconciliationData();
        });

        // Show open drawer modal
        function showOpenDrawerModal() {
            document.getElementById('open-drawer-modal').style.display = 'block';
        }

        // Show close drawer modal
        function showCloseDrawerModal() {
            document.getElementById('close-drawer-modal').style.display = 'block';
        }

        // Open cash drawer
        document.getElementById('open-drawer-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const openingCash = document.getElementById('opening-cash').value;
            
            fetch('revenue_reconciliation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=open_cash_drawer&opening_cash=${openingCash}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Cash drawer opened successfully', 'success');
                    closeModal('open-drawer-modal');
                    location.reload();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error opening drawer:', error);
                showNotification('Error opening cash drawer', 'error');
            });
        });

        // Close cash drawer
        document.getElementById('close-drawer-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const actualCash = document.getElementById('actual-cash').value;
            const notes = document.getElementById('close-notes').value;
            
            fetch('revenue_reconciliation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=close_cash_drawer&drawer_id=${currentDrawerId}&actual_cash=${actualCash}&notes=${encodeURIComponent(notes)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Cash drawer closed successfully', 'success');
                    closeModal('close-drawer-modal');
                    location.reload();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error closing drawer:', error);
                showNotification('Error closing cash drawer', 'error');
            });
        });

        // Load reconciliation data
        function loadReconciliationData() {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            
            fetch('revenue_reconciliation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_reconciliation_data&start_date=${startDate}&end_date=${endDate}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReconciliationData(data.reconciliations);
                } else {
                    document.getElementById('reconciliation-container').innerHTML = 
                        '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading data: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading reconciliation data:', error);
                document.getElementById('reconciliation-container').innerHTML = 
                    '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading data. Please try again.</div>';
            });
        }

        // Display reconciliation data
        function displayReconciliationData(reconciliations) {
            const container = document.getElementById('reconciliation-container');
            
            if (reconciliations.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No reconciliation data found</p>';
                return;
            }

            let html = `
                <table class="reconciliation-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Cashier</th>
                            <th>Total Sales</th>
                            <th>Cash Sales</th>
                            <th>Expected Cash</th>
                            <th>Actual Cash</th>
                            <th>Variance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            reconciliations.forEach(reconciliation => {
                const statusClass = `status-${reconciliation.status}`;
                const statusText = reconciliation.status.charAt(0).toUpperCase() + reconciliation.status.slice(1);
                const varianceClass = reconciliation.cash_variance >= 0 ? 'positive' : 'negative';
                const varianceColor = reconciliation.cash_variance >= 0 ? '#28a745' : '#dc3545';
                
                html += `
                    <tr>
                        <td>${new Date(reconciliation.reconciliation_date).toLocaleDateString()}</td>
                        <td>${reconciliation.cashier_name || 'Unknown'}</td>
                        <td>PKR ${parseFloat(reconciliation.total_sales).toFixed(2)}</td>
                        <td>PKR ${parseFloat(reconciliation.cash_sales).toFixed(2)}</td>
                        <td>PKR ${parseFloat(reconciliation.expected_cash).toFixed(2)}</td>
                        <td>PKR ${parseFloat(reconciliation.actual_cash || 0).toFixed(2)}</td>
                        <td style="color: ${varianceColor}; font-weight: bold;">
                            PKR ${parseFloat(reconciliation.cash_variance || 0).toFixed(2)}
                        </td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>
                            ${reconciliation.status === 'pending' ? 
                                `<button class="btn-super btn-success" onclick="reconcileRevenue(${reconciliation.id})">
                                    <i class="fas fa-check"></i> Reconcile
                                </button>` : 
                                `<span style="color: #666;">Reconciled by ${reconciliation.reconciled_by_name || 'Unknown'}</span>`
                            }
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Reconcile revenue
        function reconcileRevenue(reconciliationId) {
            if (confirm('Are you sure you want to mark this reconciliation as complete?')) {
                fetch('revenue_reconciliation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=reconcile_revenue&reconciliation_id=${reconciliationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Revenue reconciled successfully', 'success');
                        loadReconciliationData();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error reconciling revenue:', error);
                    showNotification('Error reconciling revenue', 'error');
                });
            }
        }

        // Modal functions
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Show notification
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => notification.remove());
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
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
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (notification.parentElement) {
                            notification.remove();
                        }
                    }, 300);
                }
            }, 5000);
        }
<?php include 'includes/footer.php'; ?>
