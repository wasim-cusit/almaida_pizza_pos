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
                $query = "SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $branches = $stmt->fetchAll();
                echo json_encode(['success' => true, 'branches' => $branches]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading branches: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_categories':
            try {
                $query = "SELECT id, name FROM categories ORDER BY name ASC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $categories = $stmt->fetchAll();
                echo json_encode(['success' => true, 'categories' => $categories]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading categories: ' . $e->getMessage()]);
            }
            exit();
            
        case 'generate_report':
            try {
                $reportType = $_POST['report_type'];
                $branchId = $_POST['branch_id'] ?? '';
                $categoryId = $_POST['category_id'] ?? '';
                $dateFrom = $_POST['date_from'] ?? '';
                $dateTo = $_POST['date_to'] ?? '';
                
                $reportData = generateStockReport($db, $reportType, $branchId, $categoryId, $dateFrom, $dateTo);
                echo json_encode(['success' => true, 'data' => $reportData]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error generating report: ' . $e->getMessage()]);
            }
            exit();
            
        case 'export_report':
            try {
                $reportType = $_POST['report_type'];
                $branchId = $_POST['branch_id'] ?? '';
                $categoryId = $_POST['category_id'] ?? '';
                $dateFrom = $_POST['date_from'] ?? '';
                $dateTo = $_POST['date_to'] ?? '';
                
                $reportData = generateStockReport($db, $reportType, $branchId, $categoryId, $dateFrom, $dateTo);
                $csvContent = generateCSV($reportData, $reportType);
                
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="stock_report_' . $reportType . '_' . date('Y-m-d') . '.csv"');
                echo $csvContent;
                exit();
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error exporting report: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Function to generate stock reports
function generateStockReport($db, $reportType, $branchId = '', $categoryId = '', $dateFrom = '', $dateTo = '') {
    switch($reportType) {
        case 'stock_levels':
            return getStockLevelsReport($db, $branchId, $categoryId);
        case 'movements':
            return getStockMovementsReport($db, $branchId, $categoryId, $dateFrom, $dateTo);
        case 'alerts':
            return getStockAlertsReport($db, $branchId);
        case 'valuation':
            return getStockValuationReport($db, $branchId, $categoryId);
        case 'summary':
            return getStockSummaryReport($db);
        default:
            return getStockSummaryReport($db);
    }
}

// Get stock levels report
function getStockLevelsReport($db, $branchId = '', $categoryId = '') {
    $whereConditions = [];
    $params = [];
    
    if ($branchId) {
        $whereConditions[] = "b.id = ?";
        $params[] = $branchId;
    }
    
    if ($categoryId) {
        $whereConditions[] = "i.category_id = ?";
        $params[] = $categoryId;
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    $query = "SELECT 
                i.name as item_name,
                b.name as branch_name,
                bi.current_stock,
                bi.minimum_stock,
                bi.maximum_stock,
                CASE 
                    WHEN bi.current_stock = 0 THEN 'Out of Stock'
                    WHEN bi.current_stock <= bi.minimum_stock THEN 'Low Stock'
                    WHEN bi.current_stock >= bi.maximum_stock THEN 'Overstock'
                    ELSE 'Good'
                END as status
              FROM branch_items bi
              JOIN items i ON bi.item_id = i.id
              JOIN branches b ON bi.branch_id = b.id
              {$whereClause}
              ORDER BY b.name, i.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get stock movements report
function getStockMovementsReport($db, $branchId = '', $categoryId = '', $dateFrom = '', $dateTo = '') {
    $whereConditions = [];
    $params = [];
    
    if ($branchId) {
        $whereConditions[] = "sm.branch_id = ?";
        $params[] = $branchId;
    }
    
    if ($categoryId) {
        $whereConditions[] = "i.category_id = ?";
        $params[] = $categoryId;
    }
    
    if ($dateFrom) {
        $whereConditions[] = "DATE(sm.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $whereConditions[] = "DATE(sm.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    $query = "SELECT 
                sm.created_at as date,
                i.name as item_name,
                b.name as branch_name,
                sm.movement_type,
                sm.quantity,
                sm.previous_stock,
                sm.new_stock,
                sm.reference_type,
                sm.reference_id,
                u.name as user_name
              FROM stock_movements sm
              JOIN items i ON sm.item_id = i.id
              JOIN branches b ON sm.branch_id = b.id
              JOIN users u ON sm.user_id = u.id
              {$whereClause}
              ORDER BY sm.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get stock alerts report
function getStockAlertsReport($db, $branchId = '') {
    $whereClause = $branchId ? "WHERE sa.branch_id = ?" : "";
    $params = $branchId ? [$branchId] : [];
    
    $query = "SELECT 
                sa.alert_type,
                i.name as item_name,
                b.name as branch_name,
                sa.current_stock,
                sa.threshold_stock,
                sa.created_at,
                sa.is_resolved,
                u.name as resolved_by
              FROM stock_alerts sa
              JOIN items i ON sa.item_id = i.id
              JOIN branches b ON sa.branch_id = b.id
              LEFT JOIN users u ON sa.resolved_by = u.id
              {$whereClause}
              ORDER BY sa.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get stock valuation report
function getStockValuationReport($db, $branchId = '', $categoryId = '') {
    $whereConditions = [];
    $params = [];
    
    if ($branchId) {
        $whereConditions[] = "b.id = ?";
        $params[] = $branchId;
    }
    
    if ($categoryId) {
        $whereConditions[] = "i.category_id = ?";
        $params[] = $categoryId;
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    
    $query = "SELECT 
                i.name as item_name,
                b.name as branch_name,
                bi.current_stock as quantity,
                AVG(sei.unit_cost) as avg_unit_cost,
                (bi.current_stock * AVG(sei.unit_cost)) as total_value
              FROM branch_items bi
              JOIN items i ON bi.item_id = i.id
              JOIN branches b ON bi.branch_id = b.id
              LEFT JOIN stock_entry_items sei ON i.id = sei.item_id
              {$whereClause}
              GROUP BY bi.id, i.id, b.id
              ORDER BY total_value DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get stock summary report
function getStockSummaryReport($db) {
    // Total items
    $query = "SELECT COUNT(*) as total_items FROM items";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $totalItems = $stmt->fetch()['total_items'];
    
    // Low stock items
    $query = "SELECT COUNT(*) as low_stock FROM branch_items WHERE current_stock <= minimum_stock AND current_stock > 0";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $lowStock = $stmt->fetch()['low_stock'];
    
    // Out of stock items
    $query = "SELECT COUNT(*) as out_of_stock FROM branch_items WHERE current_stock = 0";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $outOfStock = $stmt->fetch()['out_of_stock'];
    
    // Total value
    $query = "SELECT SUM(bi.current_stock * COALESCE(sei.unit_cost, 0)) as total_value 
              FROM branch_items bi
              LEFT JOIN stock_entry_items sei ON bi.item_id = sei.item_id";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $totalValue = $stmt->fetch()['total_value'] ?? 0;
    
    return [
        'total_items' => $totalItems,
        'low_stock' => $lowStock,
        'out_of_stock' => $outOfStock,
        'total_value' => $totalValue
    ];
}

// Generate CSV content
function generateCSV($data, $reportType) {
    if (empty($data)) {
        return "No data available\n";
    }
    
    $headers = array_keys($data[0]);
    $csv = implode(',', $headers) . "\n";
    
    foreach ($data as $row) {
        $csv .= implode(',', array_map(function($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $row)) . "\n";
    }
    
    return $csv;
}

// Get statistics from proper stock management tables
$query = "SELECT COUNT(*) as total_items FROM items";
$stmt = $db->prepare($query);
$stmt->execute();
$total_items = $stmt->fetch()['total_items'];

// Get low stock items from main warehouse
$query = "SELECT COUNT(*) as low_stock_items FROM main_warehouse_stock mws
          JOIN items i ON mws.item_id = i.id 
          WHERE mws.current_stock <= mws.minimum_stock AND mws.current_stock > 0";
$stmt = $db->prepare($query);
$stmt->execute();
$low_stock_items = $stmt->fetch()['low_stock_items'];

// Get out of stock items from main warehouse
$query = "SELECT COUNT(*) as out_of_stock_items FROM main_warehouse_stock mws
          JOIN items i ON mws.item_id = i.id 
          WHERE mws.current_stock = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$out_of_stock_items = $stmt->fetch()['out_of_stock_items'];

// Get total stock value from main warehouse
$query = "SELECT SUM(mws.current_stock * sei.unit_cost) as total_value 
          FROM main_warehouse_stock mws
          JOIN stock_entry_items sei ON mws.item_id = sei.item_id
          WHERE mws.current_stock > 0";
$stmt = $db->prepare($query);
$stmt->execute();
$total_value = $stmt->fetch()['total_value'] ?? 0;

$page_title = "Stock Reports";
include 'includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-chart-bar"></i> Stock Reports
    </h1>
    <p class="page-subtitle">Inventory analytics and reporting</p>
</div>

<!-- Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $total_items; ?></div>
        <div>Total Items</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f59e0b, #f59e0b); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $low_stock_items; ?></div>
        <div>Low Stock Items</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;"><?php echo $out_of_stock_items; ?></div>
        <div>Out of Stock</div>
    </div>
    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #20bf55, #16a34a); color: white; border-radius: 12px;">
        <div style="font-size: 2em; font-weight: bold;">$<?php echo number_format($total_value, 2); ?></div>
        <div>Total Value</div>
    </div>
</div>

<!-- Report Filters -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-filter"></i> Report Filters
        </h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div class="form-group">
            <label>Branch</label>
            <select class="form-control" id="branch-filter">
                <option value="">All Branches</option>
            </select>
        </div>
        <div class="form-group">
            <label>Category</label>
            <select class="form-control" id="category-filter">
                <option value="">All Categories</option>
            </select>
        </div>
        <div class="form-group">
            <label>Date From</label>
            <input type="date" class="form-control" id="date-from">
        </div>
        <div class="form-group">
            <label>Date To</label>
            <input type="date" class="form-control" id="date-to">
        </div>
        <div class="form-group">
            <label>Report Type</label>
            <select class="form-control" id="report-type">
                <option value="stock_levels">Stock Levels</option>
                <option value="movements">Stock Movements</option>
                <option value="alerts">Stock Alerts</option>
                <option value="valuation">Stock Valuation</option>
            </select>
        </div>
        <div class="form-group" style="display: flex; align-items: end;">
            <button class="btn btn-primary" onclick="generateReport()" style="width: 100%;">
                <i class="fas fa-chart-line"></i> Generate Report
            </button>
        </div>
    </div>
</div>

<!-- Report Results -->
<div class="card" id="report-results" style="display: none;">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-chart-pie"></i> Report Results
        </h2>
        <div style="display: flex; gap: 10px;">
            <button class="btn btn-success" onclick="exportReport()">
                <i class="fas fa-download"></i> Export
            </button>
            <button class="btn btn-info" onclick="printReport()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    <div id="report-content">
        <!-- Report content will be loaded here -->
    </div>
</div>

<!-- Quick Reports -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-bolt"></i> Quick Reports
        </h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        <button class="btn btn-primary" onclick="generateQuickReport('low_stock')" style="padding: 20px; text-align: center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
            <div>Low Stock Report</div>
        </button>
        
        <button class="btn btn-warning" onclick="generateQuickReport('out_of_stock')" style="padding: 20px; text-align: center;">
            <i class="fas fa-times-circle" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
            <div>Out of Stock Report</div>
        </button>
        
        <button class="btn btn-info" onclick="generateQuickReport('movements')" style="padding: 20px; text-align: center;">
            <i class="fas fa-exchange-alt" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
            <div>Stock Movements</div>
        </button>
        
        <button class="btn btn-success" onclick="generateQuickReport('valuation')" style="padding: 20px; text-align: center;">
            <i class="fas fa-dollar-sign" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
            <div>Stock Valuation</div>
        </button>
        
        <button class="btn btn-secondary" onclick="generateQuickReport('summary')" style="padding: 20px; text-align: center;">
            <i class="fas fa-chart-bar" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
            <div>Summary Report</div>
        </button>
        
        <button class="btn btn-danger" onclick="generateQuickReport('alerts')" style="padding: 20px; text-align: center;">
            <i class="fas fa-bell" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
            <div>Alert Report</div>
        </button>
    </div>
</div>

<!-- Charts Section -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-chart-pie"></i> Visual Analytics
        </h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; text-align: center;">
            <h4>Stock Levels by Branch</h4>
            <div id="stock-levels-chart" style="height: 200px; display: flex; align-items: center; justify-content: center; color: #666;">
                <i class="fas fa-chart-pie" style="font-size: 3em;"></i>
            </div>
        </div>
        
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; text-align: center;">
            <h4>Category Distribution</h4>
            <div id="category-chart" style="height: 200px; display: flex; align-items: center; justify-content: center; color: #666;">
                <i class="fas fa-chart-bar" style="font-size: 3em;"></i>
            </div>
        </div>
        
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; text-align: center;">
            <h4>Stock Movements Trend</h4>
            <div id="movements-chart" style="height: 200px; display: flex; align-items: center; justify-content: center; color: #666;">
                <i class="fas fa-chart-line" style="font-size: 3em;"></i>
            </div>
        </div>
        
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; text-align: center;">
            <h4>Alert Summary</h4>
            <div id="alerts-chart" style="height: 200px; display: flex; align-items: center; justify-content: center; color: #666;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3em;"></i>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadBranches();
        loadCategories();
        setDefaultDates();
    });

    // Load branches for filter
    function loadBranches() {
        fetch('stock_reports.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_branches'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('branch-filter');
                select.innerHTML = '<option value="">All Branches</option>';
                data.branches.forEach(branch => {
                    select.innerHTML += `<option value="${branch.id}">${branch.name}</option>`;
                });
            }
        })
        .catch(error => {
            console.error('Error loading branches:', error);
        });
    }

    // Load categories for filter
    function loadCategories() {
        fetch('stock_reports.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_categories'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('category-filter');
                select.innerHTML = '<option value="">All Categories</option>';
                data.categories.forEach(category => {
                    select.innerHTML += `<option value="${category.id}">${category.name}</option>`;
                });
            }
        })
        .catch(error => {
            console.error('Error loading categories:', error);
        });
    }

    // Set default dates
    function setDefaultDates() {
        const today = new Date();
        const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
        
        document.getElementById('date-from').value = lastMonth.toISOString().split('T')[0];
        document.getElementById('date-to').value = today.toISOString().split('T')[0];
    }

    // Generate report
    function generateReport() {
        const branchId = document.getElementById('branch-filter').value;
        const categoryId = document.getElementById('category-filter').value;
        const dateFrom = document.getElementById('date-from').value;
        const dateTo = document.getElementById('date-to').value;
        const reportType = document.getElementById('report-type').value;
        
        // Show loading
        document.getElementById('report-results').style.display = 'block';
        document.getElementById('report-content').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Generating report...</div>';
        
        // Make AJAX request to generate report
        const formData = new FormData();
        formData.append('action', 'generate_report');
        formData.append('report_type', reportType);
        formData.append('branch_id', branchId);
        formData.append('category_id', categoryId);
        formData.append('date_from', dateFrom);
        formData.append('date_to', dateTo);
        
        fetch('stock_reports.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRealReport(data.data, reportType);
            } else {
                document.getElementById('report-content').innerHTML = `
                    <div style="text-align: center; color: #dc3545; padding: 40px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3em; margin-bottom: 20px;"></i>
                        <h3>Error Generating Report</h3>
                        <p>${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error generating report:', error);
            document.getElementById('report-content').innerHTML = `
                <div style="text-align: center; color: #dc3545; padding: 40px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3em; margin-bottom: 20px;"></i>
                    <h3>Error Generating Report</h3>
                    <p>Failed to generate report. Please try again.</p>
                </div>
            `;
        });
    }

    // Display real report data
    function displayRealReport(data, reportType) {
        const content = document.getElementById('report-content');
        
        if (!data || data.length === 0) {
            content.innerHTML = `
                <div style="text-align: center; color: #666; padding: 40px;">
                    <i class="fas fa-info-circle" style="font-size: 3em; margin-bottom: 20px;"></i>
                    <h3>No Data Available</h3>
                    <p>No data found for the selected criteria.</p>
                </div>
            `;
            return;
        }
        
        switch(reportType) {
            case 'stock_levels':
                content.innerHTML = generateStockLevelsTable(data);
                break;
            case 'movements':
                content.innerHTML = generateMovementsTable(data);
                break;
            case 'alerts':
                content.innerHTML = generateAlertsTable(data);
                break;
            case 'valuation':
                content.innerHTML = generateValuationTable(data);
                break;
            case 'summary':
                content.innerHTML = generateSummaryDisplay(data);
                break;
            default:
                content.innerHTML = generateSummaryDisplay(data);
        }
    }

    // Display report (legacy function for compatibility)
    function displayReport(reportType) {
        // This function is kept for backward compatibility
        generateReport();
    }

    // Generate stock levels report
    function generateStockLevelsReport() {
        return `
            <table class="table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Branch</th>
                        <th>Current Stock</th>
                        <th>Min Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Pizza Dough</td>
                        <td>Main Branch</td>
                        <td>50</td>
                        <td>20</td>
                        <td><span style="padding: 4px 8px; border-radius: 12px; background: #d1fae5; color: #065f46;">Good</span></td>
                    </tr>
                    <tr>
                        <td>Tomato Sauce</td>
                        <td>Main Branch</td>
                        <td>5</td>
                        <td>15</td>
                        <td><span style="padding: 4px 8px; border-radius: 12px; background: #fef3c7; color: #92400e;">Low</span></td>
                    </tr>
                </tbody>
            </table>
        `;
    }

    // Generate movements report
    function generateMovementsReport() {
        return `
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Branch</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>2024-01-15</td>
                        <td>Pizza Dough</td>
                        <td>Main Branch</td>
                        <td>In</td>
                        <td>+100</td>
                        <td>Purchase #PO-001</td>
                    </tr>
                    <tr>
                        <td>2024-01-15</td>
                        <td>Tomato Sauce</td>
                        <td>Main Branch</td>
                        <td>Out</td>
                        <td>-5</td>
                        <td>Order #ORD-001</td>
                    </tr>
                </tbody>
            </table>
        `;
    }

    // Generate alerts report
    function generateAlertsReport() {
        return `
            <table class="table">
                <thead>
                    <tr>
                        <th>Alert Type</th>
                        <th>Item</th>
                        <th>Branch</th>
                        <th>Current Stock</th>
                        <th>Threshold</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span style="padding: 4px 8px; border-radius: 12px; background: #fef3c7; color: #92400e;">Low Stock</span></td>
                        <td>Tomato Sauce</td>
                        <td>Main Branch</td>
                        <td>5</td>
                        <td>15</td>
                        <td>2024-01-15</td>
                    </tr>
                </tbody>
            </table>
        `;
    }

    // Generate valuation report
    function generateValuationReport() {
        return `
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Branch</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Pizza Dough</td>
                        <td>Main Branch</td>
                        <td>50</td>
                        <td>$2.50</td>
                        <td>$125.00</td>
                    </tr>
                    <tr>
                        <td>Tomato Sauce</td>
                        <td>Main Branch</td>
                        <td>5</td>
                        <td>$1.20</td>
                        <td>$6.00</td>
                    </tr>
                </tbody>
            </table>
        `;
    }

    // Generate summary report
    function generateSummaryReport() {
        return `
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px;">
                    <div style="font-size: 2em; font-weight: bold;">150</div>
                    <div>Total Items</div>
                </div>
                <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f59e0b, #f59e0b); color: white; border-radius: 12px;">
                    <div style="font-size: 2em; font-weight: bold;">12</div>
                    <div>Low Stock</div>
                </div>
                <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border-radius: 12px;">
                    <div style="font-size: 2em; font-weight: bold;">3</div>
                    <div>Out of Stock</div>
                </div>
                <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #20bf55, #16a34a); color: white; border-radius: 12px;">
                    <div style="font-size: 2em; font-weight: bold;">$2,450</div>
                    <div>Total Value</div>
                </div>
            </div>
        `;
    }

    // Generate quick report
    function generateQuickReport(type) {
        document.getElementById('report-results').style.display = 'block';
        document.getElementById('report-content').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Generating ' + type.replace('_', ' ') + ' report...</div>';
        
        // Map quick report types to actual report types
        const reportTypeMap = {
            'low_stock': 'stock_levels',
            'out_of_stock': 'stock_levels',
            'movements': 'movements',
            'valuation': 'valuation',
            'summary': 'summary',
            'alerts': 'alerts'
        };
        
        const reportType = reportTypeMap[type] || 'summary';
        
        // Make AJAX request to generate report
        const formData = new FormData();
        formData.append('action', 'generate_report');
        formData.append('report_type', reportType);
        formData.append('branch_id', '');
        formData.append('category_id', '');
        formData.append('date_from', '');
        formData.append('date_to', '');
        
        fetch('stock_reports.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRealReport(data.data, reportType);
            } else {
                document.getElementById('report-content').innerHTML = `
                    <div style="text-align: center; color: #dc3545; padding: 40px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3em; margin-bottom: 20px;"></i>
                        <h3>Error Generating Report</h3>
                        <p>${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error generating quick report:', error);
            document.getElementById('report-content').innerHTML = `
                <div style="text-align: center; color: #dc3545; padding: 40px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3em; margin-bottom: 20px;"></i>
                    <h3>Error Generating Report</h3>
                    <p>Failed to generate report. Please try again.</p>
                </div>
            `;
        });
    }

    // Generate table functions for real data
    function generateStockLevelsTable(data) {
        let html = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Branch</th>
                        <th>Current Stock</th>
                        <th>Min Stock</th>
                        <th>Max Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        data.forEach(row => {
            const statusClass = row.status === 'Good' ? 'success' : 
                               row.status === 'Low Stock' ? 'warning' : 
                               row.status === 'Out of Stock' ? 'danger' : 'info';
            const statusBg = row.status === 'Good' ? '#d1fae5' : 
                            row.status === 'Low Stock' ? '#fef3c7' : 
                            row.status === 'Out of Stock' ? '#fee2e2' : '#e0e7ff';
            const statusColor = row.status === 'Good' ? '#065f46' : 
                               row.status === 'Low Stock' ? '#92400e' : 
                               row.status === 'Out of Stock' ? '#991b1b' : '#3730a3';
            
            html += `
                <tr>
                    <td><strong>${row.item_name}</strong></td>
                    <td>${row.branch_name}</td>
                    <td>${row.current_stock}</td>
                    <td>${row.minimum_stock}</td>
                    <td>${row.maximum_stock}</td>
                    <td><span style="padding: 4px 8px; border-radius: 12px; background: ${statusBg}; color: ${statusColor}; font-weight: 600;">${row.status}</span></td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        return html;
    }

    function generateMovementsTable(data) {
        let html = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Branch</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Previous Stock</th>
                        <th>New Stock</th>
                        <th>Reference</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        data.forEach(row => {
            const quantityColor = row.movement_type === 'in' ? '#10b981' : '#ef4444';
            html += `
                <tr>
                    <td>${new Date(row.date).toLocaleDateString()}</td>
                    <td><strong>${row.item_name}</strong></td>
                    <td>${row.branch_name}</td>
                    <td><span style="padding: 4px 8px; border-radius: 12px; background: ${row.movement_type === 'in' ? '#d1fae5' : '#fee2e2'}; color: ${row.movement_type === 'in' ? '#065f46' : '#991b1b'}; font-weight: 600;">${row.movement_type.toUpperCase()}</span></td>
                    <td style="color: ${quantityColor}; font-weight: bold;">${row.movement_type === 'in' ? '+' : ''}${row.quantity}</td>
                    <td>${row.previous_stock}</td>
                    <td>${row.new_stock}</td>
                    <td>${row.reference_type ? row.reference_type + ' #' + row.reference_id : '-'}</td>
                    <td>${row.user_name}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        return html;
    }

    function generateAlertsTable(data) {
        let html = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Alert Type</th>
                        <th>Item</th>
                        <th>Branch</th>
                        <th>Current Stock</th>
                        <th>Threshold</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Resolved By</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        data.forEach(row => {
            const alertBg = row.alert_type === 'low_stock' ? '#fef3c7' : 
                           row.alert_type === 'out_of_stock' ? '#fee2e2' : '#f3f4f6';
            const alertColor = row.alert_type === 'low_stock' ? '#92400e' : 
                              row.alert_type === 'out_of_stock' ? '#991b1b' : '#374151';
            const statusBg = row.is_resolved ? '#d1fae5' : '#fee2e2';
            const statusColor = row.is_resolved ? '#065f46' : '#991b1b';
            
            html += `
                <tr>
                    <td><span style="padding: 4px 8px; border-radius: 12px; background: ${alertBg}; color: ${alertColor}; font-weight: 600;">${row.alert_type.replace('_', ' ').toUpperCase()}</span></td>
                    <td><strong>${row.item_name}</strong></td>
                    <td>${row.branch_name}</td>
                    <td>${row.current_stock}</td>
                    <td>${row.threshold_stock}</td>
                    <td>${new Date(row.created_at).toLocaleDateString()}</td>
                    <td><span style="padding: 4px 8px; border-radius: 12px; background: ${statusBg}; color: ${statusColor}; font-weight: 600;">${row.is_resolved ? 'RESOLVED' : 'ACTIVE'}</span></td>
                    <td>${row.resolved_by || '-'}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        return html;
    }

    function generateValuationTable(data) {
        let html = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Branch</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Total Value</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        data.forEach(row => {
            html += `
                <tr>
                    <td><strong>${row.item_name}</strong></td>
                    <td>${row.branch_name}</td>
                    <td>${row.quantity}</td>
                    <td>$${parseFloat(row.avg_unit_cost || 0).toFixed(2)}</td>
                    <td style="font-weight: bold; color: #10b981;">$${parseFloat(row.total_value || 0).toFixed(2)}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        return html;
    }

    function generateSummaryDisplay(data) {
        return `
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px;">
                    <div style="font-size: 2em; font-weight: bold;">${data.total_items}</div>
                    <div>Total Items</div>
                </div>
                <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f59e0b, #f59e0b); color: white; border-radius: 12px;">
                    <div style="font-size: 2em; font-weight: bold;">${data.low_stock}</div>
                    <div>Low Stock</div>
                </div>
                <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border-radius: 12px;">
                    <div style="font-size: 2em; font-weight: bold;">${data.out_of_stock}</div>
                    <div>Out of Stock</div>
                </div>
                <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #20bf55, #16a34a); color: white; border-radius: 12px;">
                    <div style="font-size: 2em; font-weight: bold;">$${parseFloat(data.total_value || 0).toFixed(2)}</div>
                    <div>Total Value</div>
                </div>
            </div>
        `;
    }

    // Export report
    function exportReport() {
        const branchId = document.getElementById('branch-filter').value;
        const categoryId = document.getElementById('category-filter').value;
        const dateFrom = document.getElementById('date-from').value;
        const dateTo = document.getElementById('date-to').value;
        const reportType = document.getElementById('report-type').value;
        
        const formData = new FormData();
        formData.append('action', 'export_report');
        formData.append('report_type', reportType);
        formData.append('branch_id', branchId);
        formData.append('category_id', categoryId);
        formData.append('date_from', dateFrom);
        formData.append('date_to', dateTo);
        
        fetch('stock_reports.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                return response.blob();
            }
            throw new Error('Export failed');
        })
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `stock_report_${reportType}_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        })
        .catch(error => {
            console.error('Export error:', error);
            alert('Error exporting report. Please try again.');
        });
    }

    // Print report
    function printReport() {
        window.print();
    }
</script>

<?php include 'includes/footer.php'; ?>
