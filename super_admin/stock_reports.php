<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is super admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

// Get statistics
$query = "SELECT COUNT(*) as total_items FROM items WHERE is_active = 1";
$stmt = $db->prepare($query);
$stmt->execute();
$total_items = $stmt->fetch()['total_items'];

$query = "SELECT COUNT(*) as low_stock_items FROM branch_items bi 
          JOIN items i ON bi.item_id = i.id 
          WHERE bi.current_stock <= bi.minimum_stock AND bi.current_stock > 0";
$stmt = $db->prepare($query);
$stmt->execute();
$low_stock_items = $stmt->fetch()['low_stock_items'];

$query = "SELECT COUNT(*) as out_of_stock_items FROM branch_items bi 
          JOIN items i ON bi.item_id = i.id 
          WHERE bi.current_stock = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$out_of_stock_items = $stmt->fetch()['out_of_stock_items'];

$query = "SELECT SUM(bi.current_stock * i.selling_price) as total_value FROM branch_items bi 
          JOIN items i ON bi.item_id = i.id";
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
        // Simulate loading branches
        const select = document.getElementById('branch-filter');
        select.innerHTML = `
            <option value="">All Branches</option>
            <option value="1">Main Branch</option>
            <option value="2">Downtown Branch</option>
            <option value="3">Mall Branch</option>
        `;
    }

    // Load categories for filter
    function loadCategories() {
        // Simulate loading categories
        const select = document.getElementById('category-filter');
        select.innerHTML = `
            <option value="">All Categories</option>
            <option value="1">Food Items</option>
            <option value="2">Beverages</option>
            <option value="3">Supplies</option>
        `;
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
        
        // Simulate report generation
        setTimeout(() => {
            displayReport(reportType);
        }, 2000);
    }

    // Display report
    function displayReport(reportType) {
        const content = document.getElementById('report-content');
        
        switch(reportType) {
            case 'stock_levels':
                content.innerHTML = generateStockLevelsReport();
                break;
            case 'movements':
                content.innerHTML = generateMovementsReport();
                break;
            case 'alerts':
                content.innerHTML = generateAlertsReport();
                break;
            case 'valuation':
                content.innerHTML = generateValuationReport();
                break;
            default:
                content.innerHTML = generateSummaryReport();
        }
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
        document.getElementById('report-content').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Generating ' + type + ' report...</div>';
        
        setTimeout(() => {
            switch(type) {
                case 'low_stock':
                    document.getElementById('report-content').innerHTML = generateStockLevelsReport();
                    break;
                case 'out_of_stock':
                    document.getElementById('report-content').innerHTML = generateStockLevelsReport();
                    break;
                case 'movements':
                    document.getElementById('report-content').innerHTML = generateMovementsReport();
                    break;
                case 'valuation':
                    document.getElementById('report-content').innerHTML = generateValuationReport();
                    break;
                case 'summary':
                    document.getElementById('report-content').innerHTML = generateSummaryReport();
                    break;
                case 'alerts':
                    document.getElementById('report-content').innerHTML = generateAlertsReport();
                    break;
            }
        }, 1000);
    }

    // Export report
    function exportReport() {
        alert('Export functionality - Coming soon!');
    }

    // Print report
    function printReport() {
        window.print();
    }
</script>

<?php include 'includes/footer.php'; ?>
