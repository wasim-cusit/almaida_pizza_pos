<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_branches':
            try {
                // Check if branches table exists
                $query = "SHOW TABLES LIKE 'branches'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $table_exists = $stmt->fetch();
                
                if (!$table_exists) {
                    echo json_encode(['success' => true, 'branches' => []]);
                    exit();
                }
                
                // Check if is_active column exists
                $query = "SHOW COLUMNS FROM branches LIKE 'is_active'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $is_active_exists = $stmt->fetch();
                
                if ($is_active_exists) {
                    $query = "SELECT * FROM branches WHERE is_active = 1 ORDER BY name";
                } else {
                    $query = "SELECT * FROM branches ORDER BY name";
                }
                
                $stmt = $db->prepare($query);
                $stmt->execute();
                $branches = $stmt->fetchAll();
                echo json_encode(['success' => true, 'branches' => $branches]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading branches: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_warehouse_stock':
            try {
                // Check if main_warehouse_stock table exists
                $query = "SHOW TABLES LIKE 'main_warehouse_stock'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $table_exists = $stmt->fetch();
                
                if (!$table_exists) {
                    echo json_encode(['success' => true, 'stock' => []]);
                    exit();
                }
                
                $query = "SELECT mws.*, i.name as item_name, c.name as category_name 
                         FROM main_warehouse_stock mws
                         LEFT JOIN items i ON mws.item_id = i.id
                         LEFT JOIN categories c ON i.category_id = c.id
                         WHERE mws.current_stock > 0
                         ORDER BY i.name";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $stock = $stmt->fetchAll();
                echo json_encode(['success' => true, 'stock' => $stock]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading warehouse stock: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_distributions':
            try {
                // Check if stock_distributions table exists
                $query = "SHOW TABLES LIKE 'stock_distributions'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $table_exists = $stmt->fetch();
                
                if (!$table_exists) {
                    echo json_encode(['success' => true, 'distributions' => []]);
                    exit();
                }
                
                $query = "SELECT sd.*, b.name as branch_name, u.name as requested_by_name,
                         COUNT(sdi.id) as item_count
                         FROM stock_distributions sd
                         LEFT JOIN branches b ON sd.to_branch_id = b.id
                         LEFT JOIN users u ON sd.requested_by = u.id
                         LEFT JOIN stock_distribution_items sdi ON sd.id = sdi.distribution_id
                         GROUP BY sd.id, sd.distribution_number, sd.to_branch_id, sd.distribution_date, sd.total_items, sd.status, sd.notes, sd.requested_by, sd.created_at, b.name, u.name
                         ORDER BY sd.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $distributions = $stmt->fetchAll();
                echo json_encode(['success' => true, 'distributions' => $distributions]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading distributions: ' . $e->getMessage()]);
            }
            exit();
            
        case 'create_distribution':
            $to_branch_id = (int)$_POST['to_branch_id'];
            $distribution_date = $_POST['distribution_date'];
            $items = json_decode($_POST['items'], true);
            $notes = sanitize($_POST['notes']);
            
            try {
                // Check if required tables exist
                $query = "SHOW TABLES LIKE 'stock_distributions'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $distributions_table_exists = $stmt->fetch();
                
                $query = "SHOW TABLES LIKE 'stock_distribution_items'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $items_table_exists = $stmt->fetch();
                
                if (!$distributions_table_exists || !$items_table_exists) {
                    echo json_encode(['success' => false, 'message' => 'Required database tables do not exist. Please run database migration first.']);
                    exit();
                }
                
                if (empty($items)) {
                    echo json_encode(['success' => false, 'message' => 'Please add at least one item to the distribution.']);
                    exit();
                }
                
                $db->beginTransaction();
                
                // Generate distribution number
                $distribution_number = 'DIS' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Calculate total items
                $total_items = array_sum(array_column($items, 'quantity'));
                
                // Create distribution record
                $query = "INSERT INTO stock_distributions (distribution_number, to_branch_id, distribution_date, total_items, notes, requested_by) 
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$distribution_number, $to_branch_id, $distribution_date, $total_items, $notes, $_SESSION['user_id']]);
                
                $distribution_id = $db->lastInsertId();
                
                // Insert distribution items
                $query = "INSERT INTO stock_distribution_items (distribution_id, item_id, requested_quantity, unit_cost, total_cost) 
                         VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                foreach ($items as $item) {
                    $total_cost = $item['quantity'] * $item['unit_cost'];
                    $stmt->execute([$distribution_id, $item['item_id'], $item['quantity'], $item['unit_cost'], $total_cost]);
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Distribution created successfully', 'distribution_id' => $distribution_id]);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error creating distribution: ' . $e->getMessage()]);
            }
            exit();
            
        case 'approve_distribution':
            $distribution_id = (int)$_POST['distribution_id'];
            
            try {
                // Check if stock_distributions table exists
                $query = "SHOW TABLES LIKE 'stock_distributions'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $table_exists = $stmt->fetch();
                
                if (!$table_exists) {
                    echo json_encode(['success' => false, 'message' => 'Stock distributions table does not exist.']);
                    exit();
                }
                
                // Check if approved_by column exists
                $query = "SHOW COLUMNS FROM stock_distributions LIKE 'approved_by'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $approved_by_exists = $stmt->fetch();
                
                $db->beginTransaction();
                
                // Update distribution status with or without approved_by column
                if ($approved_by_exists) {
                    $query = "UPDATE stock_distributions SET status = 'approved', approved_by = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$_SESSION['user_id'], $distribution_id]);
                } else {
                    $query = "UPDATE stock_distributions SET status = 'approved' WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$distribution_id]);
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Distribution approved successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error approving distribution: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_reports':
            try {
                $report_type = $_POST['report_type'] ?? 'summary';
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                $branch_id = $_POST['branch_id'] ?? '';
                
                // Check if stock_distributions table exists
                $query = "SHOW TABLES LIKE 'stock_distributions'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $table_exists = $stmt->fetch();
                
                if (!$table_exists) {
                    echo json_encode(['success' => true, 'reports' => []]);
                    exit();
                }
                
                $reports = [];
                
                switch ($report_type) {
                    case 'summary':
                        $reports = getSummaryReport($db, $start_date, $end_date, $branch_id);
                        break;
                    case 'branch_activity':
                        $reports = getBranchActivityReport($db, $start_date, $end_date);
                        break;
                    case 'status_breakdown':
                        $reports = getStatusBreakdownReport($db, $start_date, $end_date);
                        break;
                    case 'monthly_trends':
                        $reports = getMonthlyTrendsReport($db, $start_date, $end_date);
                        break;
                    case 'top_items':
                        $reports = getTopItemsReport($db, $start_date, $end_date);
                        break;
                }
                
                echo json_encode(['success' => true, 'reports' => $reports, 'report_type' => $report_type]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error generating reports: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Report generation functions
function getSummaryReport($db, $start_date, $end_date, $branch_id) {
    $where_conditions = [];
    $params = [];
    
    if ($start_date) {
        $where_conditions[] = "DATE(sd.distribution_date) >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $where_conditions[] = "DATE(sd.distribution_date) <= ?";
        $params[] = $end_date;
    }
    if ($branch_id) {
        $where_conditions[] = "sd.to_branch_id = ?";
        $params[] = $branch_id;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $query = "SELECT 
                COUNT(*) as total_distributions,
                SUM(CASE WHEN sd.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN sd.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN sd.status = 'dispatched' THEN 1 ELSE 0 END) as dispatched_count,
                SUM(CASE WHEN sd.status = 'received' THEN 1 ELSE 0 END) as received_count,
                SUM(sd.total_items) as total_items_distributed,
                AVG(sd.total_items) as avg_items_per_distribution
              FROM stock_distributions sd
              $where_clause";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch();
}

function getBranchActivityReport($db, $start_date, $end_date) {
    $where_conditions = [];
    $params = [];
    
    if ($start_date) {
        $where_conditions[] = "DATE(sd.distribution_date) >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $where_conditions[] = "DATE(sd.distribution_date) <= ?";
        $params[] = $end_date;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $query = "SELECT 
                b.name as branch_name,
                COUNT(sd.id) as distribution_count,
                SUM(sd.total_items) as total_items,
                AVG(sd.total_items) as avg_items,
                MAX(sd.distribution_date) as last_distribution
              FROM stock_distributions sd
              LEFT JOIN branches b ON sd.to_branch_id = b.id
              $where_clause
              GROUP BY sd.to_branch_id, b.name
              ORDER BY distribution_count DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getStatusBreakdownReport($db, $start_date, $end_date) {
    $where_conditions = [];
    $params = [];
    
    if ($start_date) {
        $where_conditions[] = "DATE(sd.distribution_date) >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $where_conditions[] = "DATE(sd.distribution_date) <= ?";
        $params[] = $end_date;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $query = "SELECT 
                sd.status,
                COUNT(*) as count,
                SUM(sd.total_items) as total_items,
                ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM stock_distributions $where_clause)), 2) as percentage
              FROM stock_distributions sd
              $where_clause
              GROUP BY sd.status
              ORDER BY count DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getMonthlyTrendsReport($db, $start_date, $end_date) {
    $where_conditions = [];
    $params = [];
    
    if ($start_date) {
        $where_conditions[] = "DATE(sd.distribution_date) >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $where_conditions[] = "DATE(sd.distribution_date) <= ?";
        $params[] = $end_date;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $query = "SELECT 
                DATE_FORMAT(sd.distribution_date, '%Y-%m') as month,
                COUNT(*) as distribution_count,
                SUM(sd.total_items) as total_items,
                AVG(sd.total_items) as avg_items
              FROM stock_distributions sd
              $where_clause
              GROUP BY DATE_FORMAT(sd.distribution_date, '%Y-%m')
              ORDER BY month DESC
              LIMIT 12";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getTopItemsReport($db, $start_date, $end_date) {
    $where_conditions = [];
    $params = [];
    
    if ($start_date) {
        $where_conditions[] = "DATE(sd.distribution_date) >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $where_conditions[] = "DATE(sd.distribution_date) <= ?";
        $params[] = $end_date;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $query = "SELECT 
                i.name as item_name,
                c.name as category_name,
                SUM(sdi.requested_quantity) as total_quantity,
                COUNT(DISTINCT sd.id) as distribution_count,
                AVG(sdi.requested_quantity) as avg_quantity_per_distribution
              FROM stock_distribution_items sdi
              JOIN stock_distributions sd ON sdi.distribution_id = sd.id
              LEFT JOIN items i ON sdi.item_id = i.id
              LEFT JOIN categories c ON i.category_id = c.id
              $where_clause
              GROUP BY sdi.item_id, i.name, c.name
              ORDER BY total_quantity DESC
              LIMIT 20";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get statistics with error handling
try {
    // Check if stock_distributions table exists
    $query = "SHOW TABLES LIKE 'stock_distributions'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        $query = "SELECT COUNT(*) as total_distributions FROM stock_distributions";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $total_distributions = $stmt->fetch()['total_distributions'];

        $query = "SELECT COUNT(*) as pending_distributions FROM stock_distributions WHERE status = 'pending'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $pending_distributions = $stmt->fetch()['pending_distributions'];

        $query = "SELECT COUNT(*) as approved_distributions FROM stock_distributions WHERE status = 'approved'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $approved_distributions = $stmt->fetch()['approved_distributions'];

        $query = "SELECT SUM(total_items) as total_items_distributed FROM stock_distributions WHERE status = 'approved'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $total_items_distributed = $stmt->fetch()['total_items_distributed'] ?? 0;
    } else {
        // Set default values if table doesn't exist
        $total_distributions = 0;
        $pending_distributions = 0;
        $approved_distributions = 0;
        $total_items_distributed = 0;
    }
} catch (Exception $e) {
    // Set default values on error
    $total_distributions = 0;
    $pending_distributions = 0;
    $approved_distributions = 0;
    $total_items_distributed = 0;
}

$page_title = "Stock Distributions";
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-truck"></i> Stock Distributions
    </h1>
    <p class="page-subtitle">Distribute stock from warehouse to branches</p>
</div>

<!-- Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_distributions; ?></div>
        <div>Total Distributions</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f59e0b, #f59e0b); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $pending_distributions; ?></div>
        <div>Pending Approval</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #20bf55, #16a34a); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $approved_distributions; ?></div>
        <div>Approved</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #3b82f6, #3b82f6); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_items_distributed; ?></div>
        <div>Items Distributed</div>
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
        <button class="btn btn-primary" onclick="showCreateDistributionModal()">
            <i class="fas fa-plus"></i> New Distribution
        </button>
        <button class="btn btn-info" onclick="loadDistributions()">
            <i class="fas fa-refresh"></i> Refresh List
        </button>
        <button class="btn btn-success" onclick="exportDistributions()">
            <i class="fas fa-download"></i> Export Data
        </button>
        <button class="btn btn-warning" onclick="showReports()">
            <i class="fas fa-chart-bar"></i> View Reports
        </button>
    </div>
</div>

<!-- Distributions List -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-list"></i> Stock Distributions
        </h2>
        <div style="display: flex; gap: 10px;">
            <select class="form-control" style="width: auto;" id="status-filter">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="dispatched">Dispatched</option>
                <option value="received">Received</option>
            </select>
            <input type="date" class="form-control" style="width: auto;" id="date-filter">
        </div>
    </div>
    <div id="distributions-container">
        <div style="text-align: center; color: #666; padding: 40px;">
            <i class="fas fa-spinner fa-spin"></i> Loading distributions...
        </div>
    </div>
</div>

<!-- Create Distribution Modal -->
<div id="create-distribution-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="background: white; margin: 2% auto; padding: 30px; border-radius: 15px; width: 95%; max-width: 1000px; position: relative; max-height: 90vh; overflow-y: auto;">
        <span onclick="closeModal('create-distribution-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
        <h3>Create Stock Distribution</h3>
        
        <form id="distribution-form">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>To Branch</label>
                    <select class="form-control" id="branch-select" required>
                        <option value="">Select branch...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Distribution Date</label>
                    <input type="date" class="form-control" id="distribution-date" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea class="form-control" id="distribution-notes" rows="3"></textarea>
            </div>
            
            <h4>Available Warehouse Stock</h4>
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;" id="warehouse-stock-container">
                <p style="text-align: center; color: #666;">Loading warehouse stock...</p>
            </div>
            
            <h4>Distribution Items</h4>
            <div id="items-container">
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 10px; align-items: center; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; margin-bottom: 10px;">
                    <select class="form-control item-select" required>
                        <option value="">Select item...</option>
                    </select>
                    <input type="number" class="form-control quantity-input" placeholder="Qty" min="1" required>
                    <input type="number" class="form-control unit-cost-input" placeholder="Unit Cost" step="0.01" min="0" required>
                    <input type="number" class="form-control total-cost-input" placeholder="Total" readonly>
                    <button type="button" class="btn btn-danger" onclick="removeItem(this)" style="display: none;">Remove</button>
                </div>
            </div>
            
            <button type="button" class="btn btn-info" onclick="addItem()" style="margin-top: 10px;">
                <i class="fas fa-plus"></i> Add Item
            </button>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('create-distribution-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Distribution</button>
            </div>
        </form>
    </div>
</div>

<!-- Reports Modal -->
<div id="reports-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="background: white; margin: 2% auto; padding: 30px; border-radius: 15px; width: 95%; max-width: 1200px; position: relative; max-height: 90vh; overflow-y: auto;">
        <span onclick="closeModal('reports-modal')" style="position: absolute; right: 20px; top: 20px; font-size: 28px; cursor: pointer; color: #aaa;">&times;</span>
        
        <h3 style="margin: 0 0 20px 0; color: #333; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-chart-bar"></i> Distribution Reports
        </h3>
        
        <!-- Report Filters -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
            <div class="form-group">
                <label>Report Type</label>
                <select class="form-control" id="report-type">
                    <option value="summary">Summary Report</option>
                    <option value="branch_activity">Branch Activity</option>
                    <option value="status_breakdown">Status Breakdown</option>
                    <option value="monthly_trends">Monthly Trends</option>
                    <option value="top_items">Top Items</option>
                </select>
            </div>
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" class="form-control" id="report-start-date">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" class="form-control" id="report-end-date">
            </div>
            <div class="form-group">
                <label>Branch (Optional)</label>
                <select class="form-control" id="report-branch">
                    <option value="">All Branches</option>
                </select>
            </div>
            <div class="form-group" style="display: flex; align-items: end;">
                <button class="btn btn-primary" onclick="generateReport()" style="width: 100%;">
                    <i class="fas fa-chart-line"></i> Generate Report
                </button>
            </div>
        </div>
        
        <!-- Report Content -->
        <div id="report-content">
            <div style="text-align: center; color: #666; padding: 40px;">
                <i class="fas fa-chart-bar" style="font-size: 3em; margin-bottom: 20px; opacity: 0.3;"></i>
                <p>Select a report type and click "Generate Report" to view analytics</p>
            </div>
        </div>
    </div>
</div>

<script>
    // Global variables
    let branches = [];
    let warehouseStock = [];
    let items = [];

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadBranches();
        loadWarehouseStock();
        loadDistributions();
        
        // Set today's date as default
        document.getElementById('distribution-date').value = new Date().toISOString().split('T')[0];
    });

    // Load branches
    function loadBranches() {
        fetch('stock_distributions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_branches'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                branches = data.branches;
                const select = document.getElementById('branch-select');
                select.innerHTML = '<option value="">Select branch...</option>';
                data.branches.forEach(branch => {
                    const option = document.createElement('option');
                    option.value = branch.id;
                    option.textContent = branch.name;
                    select.appendChild(option);
                });
            } else {
                console.error('Error loading branches:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading branches:', error);
        });
    }

    // Load warehouse stock
    function loadWarehouseStock() {
        fetch('stock_distributions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_warehouse_stock'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                warehouseStock = data.stock;
                displayWarehouseStock(data.stock);
            } else {
                console.error('Error loading warehouse stock:', data.message);
                const container = document.getElementById('warehouse-stock-container');
                container.innerHTML = '<p style="text-align: center; color: #ef4444;">Error loading warehouse stock: ' + data.message + '</p>';
            }
        })
        .catch(error => {
            console.error('Error loading warehouse stock:', error);
            const container = document.getElementById('warehouse-stock-container');
            container.innerHTML = '<p style="text-align: center; color: #ef4444;">Error loading warehouse stock. Please try again.</p>';
        });
    }

    // Display warehouse stock
    function displayWarehouseStock(stock) {
        const container = document.getElementById('warehouse-stock-container');
        
        if (stock.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #666;">No stock available in warehouse</p>';
            return;
        }

        let html = '';
        stock.forEach(item => {
            html += `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; margin-bottom: 10px;">
                    <div>
                        <strong>${item.item_name}</strong> (${item.category_name})
                        <br><small>Available: ${item.current_stock} units</small>
                    </div>
                    <button class="btn btn-info" onclick="addStockItem(${item.item_id}, '${item.item_name}', ${item.current_stock})">
                        <i class="fas fa-plus"></i> Add to Distribution
                    </button>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Add stock item to distribution
    function addStockItem(itemId, itemName, availableStock) {
        const container = document.getElementById('items-container');
        const newRow = document.createElement('div');
        newRow.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 10px; align-items: center; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; margin-bottom: 10px;';
        newRow.innerHTML = `
            <select class="form-control item-select" required>
                <option value="${itemId}">${itemName}</option>
            </select>
            <input type="number" class="form-control quantity-input" placeholder="Qty" min="1" max="${availableStock}" required>
            <input type="number" class="form-control unit-cost-input" placeholder="Unit Cost" step="0.01" min="0" required>
            <input type="number" class="form-control total-cost-input" placeholder="Total" readonly>
            <button type="button" class="btn btn-danger" onclick="removeItem(this)">Remove</button>
        `;
        container.appendChild(newRow);
        setupItemRowEvents(newRow);
    }

    // Load distributions
    function loadDistributions() {
        const container = document.getElementById('distributions-container');
        container.innerHTML = '<div style="text-align: center; color: #666; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading distributions...</div>';
        
        fetch('stock_distributions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_distributions'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayDistributions(data.distributions);
            } else {
                container.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading distributions: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            console.error('Error loading distributions:', error);
            container.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading distributions. Please try again.</div>';
        });
    }

    // Display distributions
    function displayDistributions(distributions) {
        const container = document.getElementById('distributions-container');
        
        if (distributions.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No distributions found</p>';
            return;
        }

        let html = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Distribution #</th>
                        <th>To Branch</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
        `;

        distributions.forEach(distribution => {
            const statusClass = `status-${distribution.status}`;
            const statusText = distribution.status.charAt(0).toUpperCase() + distribution.status.slice(1);
            
            html += `
                <tr>
                    <td><strong>${distribution.distribution_number}</strong></td>
                    <td>${distribution.branch_name}</td>
                    <td>${new Date(distribution.distribution_date).toLocaleDateString()}</td>
                    <td>${distribution.item_count}</td>
                    <td><span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: ${getStatusColor(distribution.status)}; color: white;">${statusText}</span></td>
                    <td>${distribution.requested_by_name}</td>
                    <td>
                        <button class="btn btn-info" onclick="viewDistribution(${distribution.id})">
                            <i class="fas fa-eye"></i> View
                        </button>
                        ${distribution.status === 'pending' ? 
                            `<button class="btn btn-success" onclick="approveDistribution(${distribution.id})">
                                <i class="fas fa-check"></i> Approve
                            </button>` : ''
                        }
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    // Get status color
    function getStatusColor(status) {
        switch(status) {
            case 'pending': return '#f59e0b';
            case 'approved': return '#20bf55';
            case 'dispatched': return '#3b82f6';
            case 'received': return '#20bf55';
            default: return '#6c757d';
        }
    }

    // Show create distribution modal
    function showCreateDistributionModal() {
        document.getElementById('create-distribution-modal').style.display = 'block';
    }

    // Add item row
    function addItem() {
        const container = document.getElementById('items-container');
        const newRow = document.createElement('div');
        newRow.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 10px; align-items: center; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px; margin-bottom: 10px;';
        newRow.innerHTML = `
            <select class="form-control item-select" required>
                <option value="">Select item...</option>
            </select>
            <input type="number" class="form-control quantity-input" placeholder="Qty" min="1" required>
            <input type="number" class="form-control unit-cost-input" placeholder="Unit Cost" step="0.01" min="0" required>
            <input type="number" class="form-control total-cost-input" placeholder="Total" readonly>
            <button type="button" class="btn btn-danger" onclick="removeItem(this)">Remove</button>
        `;
        container.appendChild(newRow);
        setupItemRowEvents(newRow);
    }

    // Remove item row
    function removeItem(button) {
        button.parentElement.remove();
    }

    // Setup events for item row
    function setupItemRowEvents(row) {
        const quantityInput = row.querySelector('.quantity-input');
        const unitCostInput = row.querySelector('.unit-cost-input');
        const totalCostInput = row.querySelector('.total-cost-input');
        
        quantityInput.addEventListener('input', calculateRowTotal);
        unitCostInput.addEventListener('input', calculateRowTotal);
        
        function calculateRowTotal() {
            const quantity = parseFloat(quantityInput.value) || 0;
            const unitCost = parseFloat(unitCostInput.value) || 0;
            const total = quantity * unitCost;
            totalCostInput.value = total.toFixed(2);
        }
    }

    // Create distribution
    function createDistribution() {
        const branchId = document.getElementById('branch-select').value;
        const distributionDate = document.getElementById('distribution-date').value;
        const notes = document.getElementById('distribution-notes').value;
        
        // Collect items
        const items = [];
        document.querySelectorAll('#items-container > div').forEach(row => {
            const itemSelect = row.querySelector('.item-select');
            const quantity = row.querySelector('.quantity-input').value;
            const unitCost = row.querySelector('.unit-cost-input').value;
            
            if (itemSelect.value && quantity && unitCost) {
                items.push({
                    item_id: itemSelect.value,
                    quantity: parseInt(quantity),
                    unit_cost: parseFloat(unitCost)
                });
            }
        });
        
        if (items.length === 0) {
            showNotification('Please add at least one item', 'error');
            return;
        }
        
        // Show loading state
        const submitBtn = document.querySelector('#distribution-form button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Creating...';
        submitBtn.disabled = true;
        
        // Submit distribution
        fetch('stock_distributions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=create_distribution&to_branch_id=${branchId}&distribution_date=${distributionDate}&items=${encodeURIComponent(JSON.stringify(items))}&notes=${encodeURIComponent(notes)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Distribution created successfully', 'success');
                closeModal('create-distribution-modal');
                loadDistributions();
                resetDistributionForm();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error creating distribution:', error);
            showNotification('Error creating distribution. Please try again.', 'error');
        })
        .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    }

    // Approve distribution
    function approveDistribution(distributionId) {
        if (confirm('Are you sure you want to approve this distribution?')) {
            fetch('stock_distributions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=approve_distribution&distribution_id=${distributionId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Distribution approved successfully', 'success');
                    loadDistributions();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error approving distribution:', error);
                showNotification('Error approving distribution. Please try again.', 'error');
            });
        }
    }

    // View distribution details
    function viewDistribution(distributionId) {
        showNotification('View distribution details functionality - Coming soon!', 'info');
    }
    
    // Reset distribution form
    function resetDistributionForm() {
        document.getElementById('distribution-form').reset();
        document.getElementById('distribution-date').value = new Date().toISOString().split('T')[0];
        
        // Clear items container except first row
        const container = document.getElementById('items-container');
        const firstRow = container.querySelector('div:first-child');
        container.innerHTML = '';
        if (firstRow) {
            container.appendChild(firstRow);
        }
        
        // Reset warehouse stock display
        const warehouseContainer = document.getElementById('warehouse-stock-container');
        warehouseContainer.innerHTML = '<p style="text-align: center; color: #666;">Loading warehouse stock...</p>';
        loadWarehouseStock();
    }

    // Modal functions
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('[id$="-modal"]');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

    // Export distributions
    function exportDistributions() {
        fetch('stock_distributions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_distributions'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                exportToCSV(data.distributions, 'distributions');
                showNotification('Distribution data exported successfully', 'success');
            } else {
                showNotification('Error exporting data', 'error');
            }
        })
        .catch(error => {
            console.error('Error exporting distributions:', error);
            showNotification('Error exporting data', 'error');
        });
    }

    // Show reports modal
    function showReports() {
        document.getElementById('reports-modal').style.display = 'block';
        loadBranchesForReports();
        setDefaultDates();
    }
    
    // Load branches for reports
    function loadBranchesForReports() {
        const select = document.getElementById('report-branch');
        select.innerHTML = '<option value="">All Branches</option>';
        
        branches.forEach(branch => {
            const option = document.createElement('option');
            option.value = branch.id;
            option.textContent = branch.name;
            select.appendChild(option);
        });
    }
    
    // Set default date range (last 30 days)
    function setDefaultDates() {
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
        
        document.getElementById('report-start-date').value = thirtyDaysAgo.toISOString().split('T')[0];
        document.getElementById('report-end-date').value = today.toISOString().split('T')[0];
    }
    
    // Generate report
    function generateReport() {
        const reportType = document.getElementById('report-type').value;
        const startDate = document.getElementById('report-start-date').value;
        const endDate = document.getElementById('report-end-date').value;
        const branchId = document.getElementById('report-branch').value;
        
        const content = document.getElementById('report-content');
        content.innerHTML = '<div style="text-align: center; color: #666; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Generating report...</div>';
        
        fetch('stock_distributions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_reports&report_type=${reportType}&start_date=${startDate}&end_date=${endDate}&branch_id=${branchId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayReport(data.reports, data.report_type);
            } else {
                content.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;">Error generating report: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            console.error('Error generating report:', error);
            content.innerHTML = '<div style="text-align: center; color: #ef4444; padding: 40px;">Error generating report. Please try again.</div>';
        });
    }
    
    // Display report based on type
    function displayReport(reports, reportType) {
        const content = document.getElementById('report-content');
        
        switch (reportType) {
            case 'summary':
                displaySummaryReport(reports, content);
                break;
            case 'branch_activity':
                displayBranchActivityReport(reports, content);
                break;
            case 'status_breakdown':
                displayStatusBreakdownReport(reports, content);
                break;
            case 'monthly_trends':
                displayMonthlyTrendsReport(reports, content);
                break;
            case 'top_items':
                displayTopItemsReport(reports, content);
                break;
        }
    }
    
    // Display summary report
    function displaySummaryReport(data, container) {
        const html = `
            <div style="margin-bottom: 30px;">
                <h4 style="color: #333; margin-bottom: 20px;">
                    <i class="fas fa-chart-pie"></i> Distribution Summary
                </h4>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 2em; font-weight: bold; color: #667eea;">${data.total_distributions || 0}</div>
                        <div style="color: #666;">Total Distributions</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 2em; font-weight: bold; color: #f59e0b;">${data.pending_count || 0}</div>
                        <div style="color: #666;">Pending</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 2em; font-weight: bold; color: #20bf55;">${data.approved_count || 0}</div>
                        <div style="color: #666;">Approved</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 2em; font-weight: bold; color: #3b82f6;">${data.dispatched_count || 0}</div>
                        <div style="color: #666;">Dispatched</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 2em; font-weight: bold; color: #10b981;">${data.received_count || 0}</div>
                        <div style="color: #666;">Received</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 2em; font-weight: bold; color: #8b5cf6;">${data.total_items_distributed || 0}</div>
                        <div style="color: #666;">Total Items</div>
                    </div>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
                    <h5 style="margin: 0 0 15px 0; color: #333;">Key Metrics</h5>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <strong>Average Items per Distribution:</strong> ${parseFloat(data.avg_items_per_distribution || 0).toFixed(2)}
                        </div>
                        <div>
                            <strong>Completion Rate:</strong> ${data.total_distributions > 0 ? ((data.received_count / data.total_distributions) * 100).toFixed(1) : 0}%
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.innerHTML = html;
    }
    
    // Display branch activity report
    function displayBranchActivityReport(data, container) {
        if (data.length === 0) {
            container.innerHTML = '<div style="text-align: center; color: #666; padding: 40px;">No branch activity data found</div>';
            return;
        }
        
        let html = `
            <div style="margin-bottom: 30px;">
                <h4 style="color: #333; margin-bottom: 20px;">
                    <i class="fas fa-building"></i> Branch Activity Report
                </h4>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Branch Name</th>
                                <th>Distributions</th>
                                <th>Total Items</th>
                                <th>Avg Items</th>
                                <th>Last Distribution</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        data.forEach(branch => {
            html += `
                <tr>
                    <td><strong>${branch.branch_name || 'Unknown Branch'}</strong></td>
                    <td>${branch.distribution_count}</td>
                    <td>${branch.total_items}</td>
                    <td>${parseFloat(branch.avg_items).toFixed(2)}</td>
                    <td>${branch.last_distribution ? new Date(branch.last_distribution).toLocaleDateString() : 'N/A'}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div></div>';
        container.innerHTML = html;
    }
    
    // Display status breakdown report
    function displayStatusBreakdownReport(data, container) {
        if (data.length === 0) {
            container.innerHTML = '<div style="text-align: center; color: #666; padding: 40px;">No status data found</div>';
            return;
        }
        
        let html = `
            <div style="margin-bottom: 30px;">
                <h4 style="color: #333; margin-bottom: 20px;">
                    <i class="fas fa-chart-pie"></i> Status Breakdown
                </h4>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                                <th>Total Items</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        data.forEach(status => {
            const statusColor = getStatusColor(status.status);
            html += `
                <tr>
                    <td><span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: ${statusColor}; color: white;">${status.status.charAt(0).toUpperCase() + status.status.slice(1)}</span></td>
                    <td>${status.count}</td>
                    <td>${status.total_items}</td>
                    <td>${status.percentage}%</td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div></div>';
        container.innerHTML = html;
    }
    
    // Display monthly trends report
    function displayMonthlyTrendsReport(data, container) {
        if (data.length === 0) {
            container.innerHTML = '<div style="text-align: center; color: #666; padding: 40px;">No trend data found</div>';
            return;
        }
        
        let html = `
            <div style="margin-bottom: 30px;">
                <h4 style="color: #333; margin-bottom: 20px;">
                    <i class="fas fa-chart-line"></i> Monthly Trends
                </h4>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Distributions</th>
                                <th>Total Items</th>
                                <th>Avg Items</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        data.forEach(month => {
            html += `
                <tr>
                    <td><strong>${month.month}</strong></td>
                    <td>${month.distribution_count}</td>
                    <td>${month.total_items}</td>
                    <td>${parseFloat(month.avg_items).toFixed(2)}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div></div>';
        container.innerHTML = html;
    }
    
    // Display top items report
    function displayTopItemsReport(data, container) {
        if (data.length === 0) {
            container.innerHTML = '<div style="text-align: center; color: #666; padding: 40px;">No item data found</div>';
            return;
        }
        
        let html = `
            <div style="margin-bottom: 30px;">
                <h4 style="color: #333; margin-bottom: 20px;">
                    <i class="fas fa-star"></i> Top Distributed Items
                </h4>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Total Quantity</th>
                                <th>Distributions</th>
                                <th>Avg per Distribution</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        data.forEach((item, index) => {
            html += `
                <tr>
                    <td><strong>${item.item_name || 'Unknown Item'}</strong></td>
                    <td>${item.category_name || 'Uncategorized'}</td>
                    <td>${item.total_quantity}</td>
                    <td>${item.distribution_count}</td>
                    <td>${parseFloat(item.avg_quantity_per_distribution).toFixed(2)}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div></div>';
        container.innerHTML = html;
    }
    
    // Export to CSV
    function exportToCSV(data, filename) {
        if (data.length === 0) {
            showNotification('No data to export', 'warning');
            return;
        }
        
        const headers = Object.keys(data[0]);
        const csvContent = [
            headers.join(','),
            ...data.map(row => headers.map(header => `"${row[header] || ''}"`).join(','))
        ].join('\n');
        
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${filename}_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }
    
    // Professional notification system
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
            display: flex;
            align-items: center;
            gap: 10px;
        `;
        
        // Set colors based on type
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };
        
        notification.style.backgroundColor = colors[type] || colors.info;
        
        // Add icon
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        
        notification.innerHTML = `
            <i class="${icons[type] || icons.info}" style="font-size: 18px;"></i>
            <span>${message}</span>
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

    // Handle form submission
    document.getElementById('distribution-form').addEventListener('submit', function(e) {
        e.preventDefault();
        createDistribution();
    });
</script>

<?php include 'includes/footer.php'; ?>
