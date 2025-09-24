<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_all_reconciliations':
            $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $end_date = $_POST['end_date'] ?? date('Y-m-d');
            $branch_id = $_POST['branch_id'] ?? null;
            
            try {
                $where_clause = "WHERE rr.reconciliation_date BETWEEN ? AND ?";
                $params = [$start_date, $end_date];
                
                if ($branch_id) {
                    $where_clause .= " AND rr.branch_id = ?";
                    $params[] = $branch_id;
                }
                
                $query = "SELECT rr.*, b.name as branch_name, u.name as reconciled_by_name, 
                         cd.user_id as cashier_id, u2.name as cashier_name
                         FROM revenue_reconciliation rr
                         LEFT JOIN branches b ON rr.branch_id = b.id
                         LEFT JOIN users u ON rr.reconciled_by = u.id
                         LEFT JOIN cash_drawers cd ON rr.cash_drawer_id = cd.id
                         LEFT JOIN users u2 ON cd.user_id = u2.id
                         $where_clause
                         ORDER BY rr.reconciliation_date DESC, b.name";
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $reconciliations = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'reconciliations' => $reconciliations]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading reconciliations: ' . $e->getMessage()]);
            }
            exit();
            
        case 'force_reconcile':
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
            
        case 'get_reconciliation_summary':
            $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $end_date = $_POST['end_date'] ?? date('Y-m-d');
            
            try {
                $query = "SELECT 
                         b.name as branch_name,
                         COUNT(*) as total_reconciliations,
                         SUM(rr.total_sales) as total_sales,
                         SUM(rr.cash_sales) as total_cash_sales,
                         SUM(ABS(rr.cash_variance)) as total_variance,
                         AVG(ABS(rr.cash_variance)) as avg_variance,
                         SUM(CASE WHEN rr.status = 'reconciled' THEN 1 ELSE 0 END) as reconciled_count,
                         SUM(CASE WHEN rr.status = 'pending' THEN 1 ELSE 0 END) as pending_count
                         FROM revenue_reconciliation rr
                         JOIN branches b ON rr.branch_id = b.id
                         WHERE rr.reconciliation_date BETWEEN ? AND ?
                         GROUP BY rr.branch_id, b.name
                         ORDER BY b.name";
                $stmt = $db->prepare($query);
                $stmt->execute([$start_date, $end_date]);
                $summary = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'summary' => $summary]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading summary: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics
$stats = [];

// Total reconciliations this month
$query = "SELECT COUNT(*) as total FROM revenue_reconciliation WHERE MONTH(reconciliation_date) = MONTH(CURDATE()) AND YEAR(reconciliation_date) = YEAR(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['monthly_reconciliations'] = $stmt->fetch()['total'] ?? 0;

// Pending reconciliations
$query = "SELECT COUNT(*) as pending FROM revenue_reconciliation WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_reconciliations'] = $stmt->fetch()['pending'] ?? 0;

// Total variance this month
$query = "SELECT SUM(ABS(cash_variance)) as variance FROM revenue_reconciliation WHERE MONTH(reconciliation_date) = MONTH(CURDATE()) AND YEAR(reconciliation_date) = YEAR(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['monthly_variance'] = $stmt->fetch()['variance'] ?? 0;

// Total sales this month
$query = "SELECT SUM(total_sales) as sales FROM revenue_reconciliation WHERE MONTH(reconciliation_date) = MONTH(CURDATE()) AND YEAR(reconciliation_date) = YEAR(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['monthly_sales'] = $stmt->fetch()['sales'] ?? 0;

// Get all branches
$query = "SELECT id, name FROM branches ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$branches = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Reconciliation - Super Admin</title>
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
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #20bf55;
        }
        
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
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-reconciled { background: #d1fae5; color: #065f46; }
        .status-discrepancy { background: #fee2e2; color: #991b1b; }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .summary-card h4 {
            margin: 0 0 15px 0;
            color: #374151;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 10px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
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
            <h1><i class="fas fa-cash-register"></i> Revenue Reconciliation - Super Admin</h1>
            <p>Monitor revenue reconciliation across all branches</p>
            <div style="margin-top: 20px;">
                <a href="index.php" class="btn-super btn-info">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #20bf55;"><?php echo $stats['monthly_reconciliations']; ?></div>
                <div class="stat-label">Monthly Reconciliations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #ffc107;"><?php echo $stats['pending_reconciliations']; ?></div>
                <div class="stat-label">Pending Reconciliations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #dc3545;">PKR <?php echo number_format($stats['monthly_variance'], 2); ?></div>
                <div class="stat-label">Monthly Variance</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #17a2b8;">PKR <?php echo number_format($stats['monthly_sales'], 2); ?></div>
                <div class="stat-label">Monthly Sales</div>
            </div>
        </div>

        <!-- Branch Summary -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-bar"></i> Branch Summary</h2>
                <button class="btn-super btn-primary" onclick="loadBranchSummary()">
                    <i class="fas fa-refresh"></i> Refresh Summary
                </button>
            </div>
            <div id="branch-summary-container">
                <p style="text-align: center; color: #666; padding: 40px;">
                    Loading branch summary...
                </p>
            </div>
        </div>

        <!-- Detailed Reconciliation -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i class="fas fa-list-alt"></i> Detailed Reconciliation</h2>
                <button class="btn-super btn-info" onclick="loadReconciliations()">
                    <i class="fas fa-refresh"></i> Load Data
                </button>
            </div>
            
            <!-- Filters -->
            <div class="filter-section">
                <form id="filter-form">
                    <div class="filter-form">
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" id="start-date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" id="end-date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Branch</label>
                            <select id="branch-filter">
                                <option value="">All Branches</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['id']; ?>"><?php echo $branch['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn-super btn-primary">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div id="reconciliations-container">
                <p style="text-align: center; color: #666; padding: 40px;">
                    Loading reconciliation data...
                </p>
            </div>
        </div>
    </div>

    <script>
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadBranchSummary();
            loadReconciliations();
        });

        // Load branch summary
        function loadBranchSummary() {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            
            fetch('revenue_reconciliation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_reconciliation_summary&start_date=${startDate}&end_date=${endDate}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayBranchSummary(data.summary);
                } else {
                    document.getElementById('branch-summary-container').innerHTML = 
                        '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading summary: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading summary:', error);
                document.getElementById('branch-summary-container').innerHTML = 
                    '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading summary. Please try again.</div>';
            });
        }

        // Display branch summary
        function displayBranchSummary(summary) {
            const container = document.getElementById('branch-summary-container');
            
            if (summary.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No summary data found</p>';
                return;
            }

            let html = '<div class="summary-grid">';

            summary.forEach(branch => {
                const reconciliationRate = branch.total_reconciliations > 0 ? 
                    ((branch.reconciled_count / branch.total_reconciliations) * 100).toFixed(1) : 0;
                
                html += `
                    <div class="summary-card">
                        <h4>${branch.branch_name}</h4>
                        <div class="summary-item">
                            <span>Total Reconciliations:</span>
                            <span>${branch.total_reconciliations}</span>
                        </div>
                        <div class="summary-item">
                            <span>Total Sales:</span>
                            <span>PKR ${parseFloat(branch.total_sales).toFixed(2)}</span>
                        </div>
                        <div class="summary-item">
                            <span>Cash Sales:</span>
                            <span>PKR ${parseFloat(branch.total_cash_sales).toFixed(2)}</span>
                        </div>
                        <div class="summary-item">
                            <span>Total Variance:</span>
                            <span style="color: ${parseFloat(branch.total_variance) > 0 ? '#dc3545' : '#28a745'};">
                                PKR ${parseFloat(branch.total_variance).toFixed(2)}
                            </span>
                        </div>
                        <div class="summary-item">
                            <span>Avg Variance:</span>
                            <span style="color: ${parseFloat(branch.avg_variance) > 0 ? '#dc3545' : '#28a745'};">
                                PKR ${parseFloat(branch.avg_variance).toFixed(2)}
                            </span>
                        </div>
                        <div class="summary-item">
                            <span>Reconciliation Rate:</span>
                            <span style="color: ${parseFloat(reconciliationRate) >= 90 ? '#28a745' : '#ffc107'};">
                                ${reconciliationRate}%
                            </span>
                        </div>
                        <div class="summary-item">
                            <span>Pending:</span>
                            <span style="color: ${branch.pending_count > 0 ? '#dc3545' : '#28a745'};">
                                ${branch.pending_count}
                            </span>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        // Load reconciliations
        function loadReconciliations() {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            const branchId = document.getElementById('branch-filter').value;
            
            fetch('revenue_reconciliation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_all_reconciliations&start_date=${startDate}&end_date=${endDate}&branch_id=${branchId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReconciliations(data.reconciliations);
                } else {
                    document.getElementById('reconciliations-container').innerHTML = 
                        '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading reconciliations: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading reconciliations:', error);
                document.getElementById('reconciliations-container').innerHTML = 
                    '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading reconciliations. Please try again.</div>';
            });
        }

        // Display reconciliations
        function displayReconciliations(reconciliations) {
            const container = document.getElementById('reconciliations-container');
            
            if (reconciliations.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No reconciliation data found</p>';
                return;
            }

            let html = `
                <table class="reconciliation-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Branch</th>
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
                const varianceColor = reconciliation.cash_variance >= 0 ? '#28a745' : '#dc3545';
                
                html += `
                    <tr>
                        <td>${new Date(reconciliation.reconciliation_date).toLocaleDateString()}</td>
                        <td>${reconciliation.branch_name || 'Unknown'}</td>
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
                                `<button class="btn-super btn-success" onclick="forceReconcile(${reconciliation.id})">
                                    <i class="fas fa-check"></i> Force Reconcile
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

        // Force reconcile
        function forceReconcile(reconciliationId) {
            if (confirm('Are you sure you want to force reconcile this revenue? This will mark it as reconciled.')) {
                fetch('revenue_reconciliation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=force_reconcile&reconciliation_id=${reconciliationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Revenue reconciled successfully', 'success');
                        loadReconciliations();
                        loadBranchSummary();
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

        // Filter form
        document.getElementById('filter-form').addEventListener('submit', function(e) {
            e.preventDefault();
            loadReconciliations();
            loadBranchSummary();
        });

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
    </script>
</body>
</html>
