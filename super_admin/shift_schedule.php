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
        case 'get_all_schedules':
            $start_date = $_POST['start_date'] ?? date('Y-m-d');
            $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
            $branch_id = $_POST['branch_id'] ?? null;
            
            try {
                $where_clause = "WHERE ss.shift_date BETWEEN ? AND ?";
                $params = [$start_date, $end_date];
                
                if ($branch_id) {
                    $where_clause .= " AND ss.branch_id = ?";
                    $params[] = $branch_id;
                }
                
                $query = "SELECT ss.*, b.name as branch_name, u.name as user_name, u2.name as created_by_name,
                         sa.actual_start_time, sa.actual_end_time, sa.total_hours, sa.status as attendance_status
                         FROM shift_schedules ss
                         JOIN branches b ON ss.branch_id = b.id
                         JOIN users u ON ss.user_id = u.id
                         LEFT JOIN users u2 ON ss.created_by = u2.id
                         LEFT JOIN shift_attendance sa ON ss.id = sa.shift_schedule_id
                         $where_clause
                         ORDER BY ss.shift_date, ss.start_time";
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $schedules = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'schedules' => $schedules]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading schedules: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_schedule_summary':
            $start_date = $_POST['start_date'] ?? date('Y-m-d');
            $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
            
            try {
                $query = "SELECT 
                         b.name as branch_name,
                         COUNT(*) as total_schedules,
                         SUM(CASE WHEN ss.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_count,
                         SUM(CASE WHEN ss.status = 'started' THEN 1 ELSE 0 END) as started_count,
                         SUM(CASE WHEN ss.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                         SUM(CASE WHEN ss.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                         AVG(sa.total_hours) as avg_hours_worked
                         FROM shift_schedules ss
                         JOIN branches b ON ss.branch_id = b.id
                         LEFT JOIN shift_attendance sa ON ss.id = sa.shift_schedule_id
                         WHERE ss.shift_date BETWEEN ? AND ?
                         GROUP BY ss.branch_id, b.name
                         ORDER BY b.name";
                $stmt = $db->prepare($query);
                $stmt->execute([$start_date, $end_date]);
                $summary = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'summary' => $summary]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading summary: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_labor_costs':
            $start_date = $_POST['start_date'] ?? date('Y-m-d');
            $end_date = $_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
            $branch_id = $_POST['branch_id'] ?? null;
            
            try {
                $where_clause = "WHERE lc.shift_date BETWEEN ? AND ?";
                $params = [$start_date, $end_date];
                
                if ($branch_id) {
                    $where_clause .= " AND lc.branch_id = ?";
                    $params[] = $branch_id;
                }
                
                $query = "SELECT lc.*, b.name as branch_name, u.name as user_name
                         FROM labor_costs lc
                         JOIN branches b ON lc.branch_id = b.id
                         JOIN users u ON lc.user_id = u.id
                         $where_clause
                         ORDER BY lc.shift_date DESC, b.name";
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $labor_costs = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'labor_costs' => $labor_costs]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error loading labor costs: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Get statistics
$stats = [];

// Total schedules this week
$query = "SELECT COUNT(*) as total FROM shift_schedules WHERE shift_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['weekly_schedules'] = $stmt->fetch()['total'] ?? 0;

// Active shifts today
$query = "SELECT COUNT(*) as active FROM shift_schedules WHERE shift_date = CURDATE() AND status = 'started'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['active_shifts'] = $stmt->fetch()['active'] ?? 0;

// Completed shifts this week
$query = "SELECT COUNT(*) as completed FROM shift_schedules WHERE shift_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['completed_shifts'] = $stmt->fetch()['completed'] ?? 0;

// Total labor cost this month
$query = "SELECT SUM(total_labor_cost) as cost FROM labor_costs WHERE MONTH(shift_date) = MONTH(CURDATE()) AND YEAR(shift_date) = YEAR(CURDATE())";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['monthly_labor_cost'] = $stmt->fetch()['cost'] ?? 0;

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
    <title>Shift Schedule - Super Admin</title>
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
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .schedule-table th,
        .schedule-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .schedule-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .schedule-table tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-scheduled { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-started { background: #d1fae5; color: #065f46; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
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
        
        .tab-container {
            margin-bottom: 20px;
        }
        
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab-button {
            padding: 12px 24px;
            border: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-weight: 600;
            background: #f8f9fa;
            color: #666;
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            background: #20bf55;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
            <h1><i class="fas fa-calendar-alt"></i> Shift Schedule - Super Admin</h1>
            <p>Monitor shift schedules and labor costs across all branches</p>
            <div style="margin-top: 20px;">
                <a href="index.php" class="btn-super btn-info">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" style="color: #20bf55;"><?php echo $stats['weekly_schedules']; ?></div>
                <div class="stat-label">This Week's Schedules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #ffc107;"><?php echo $stats['active_shifts']; ?></div>
                <div class="stat-label">Active Shifts Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #28a745;"><?php echo $stats['completed_shifts']; ?></div>
                <div class="stat-label">Completed This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #dc3545;">PKR <?php echo number_format($stats['monthly_labor_cost'], 2); ?></div>
                <div class="stat-label">Monthly Labor Cost</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-container">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="switchTab('schedules')">
                    <i class="fas fa-calendar-week"></i> Schedules
                </button>
                <button class="tab-button" onclick="switchTab('summary')">
                    <i class="fas fa-chart-bar"></i> Branch Summary
                </button>
                <button class="tab-button" onclick="switchTab('labor')">
                    <i class="fas fa-money-bill-wave"></i> Labor Costs
                </button>
            </div>

            <!-- Schedules Tab -->
            <div id="schedules-tab" class="tab-content active">
                <div class="admin-section">
                    <div class="section-header">
                        <h2><i class="fas fa-list-alt"></i> Shift Schedules</h2>
                        <button class="btn-super btn-info" onclick="loadSchedules()">
                            <i class="fas fa-refresh"></i> Load Data
                        </button>
                    </div>
                    
                    <!-- Filters -->
                    <div class="filter-section">
                        <form id="schedule-filter-form">
                            <div class="filter-form">
                                <div class="form-group">
                                    <label>Start Date</label>
                                    <input type="date" id="schedule-start-date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>End Date</label>
                                    <input type="date" id="schedule-end-date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Branch</label>
                                    <select id="schedule-branch-filter">
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
                    
                    <div id="schedules-container">
                        <p style="text-align: center; color: #666; padding: 40px;">
                            Loading schedules...
                        </p>
                    </div>
                </div>
            </div>

            <!-- Summary Tab -->
            <div id="summary-tab" class="tab-content">
                <div class="admin-section">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-bar"></i> Branch Summary</h2>
                        <button class="btn-super btn-primary" onclick="loadScheduleSummary()">
                            <i class="fas fa-refresh"></i> Refresh Summary
                        </button>
                    </div>
                    <div id="schedule-summary-container">
                        <p style="text-align: center; color: #666; padding: 40px;">
                            Loading schedule summary...
                        </p>
                    </div>
                </div>
            </div>

            <!-- Labor Costs Tab -->
            <div id="labor-tab" class="tab-content">
                <div class="admin-section">
                    <div class="section-header">
                        <h2><i class="fas fa-money-bill-wave"></i> Labor Costs</h2>
                        <button class="btn-super btn-info" onclick="loadLaborCosts()">
                            <i class="fas fa-refresh"></i> Load Data
                        </button>
                    </div>
                    
                    <!-- Filters -->
                    <div class="filter-section">
                        <form id="labor-filter-form">
                            <div class="filter-form">
                                <div class="form-group">
                                    <label>Start Date</label>
                                    <input type="date" id="labor-start-date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>End Date</label>
                                    <input type="date" id="labor-end-date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Branch</label>
                                    <select id="labor-branch-filter">
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
                    
                    <div id="labor-costs-container">
                        <p style="text-align: center; color: #666; padding: 40px;">
                            Loading labor costs...
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            loadSchedules();
        });

        // Tab switching
        function switchTab(tabName) {
            // Remove active class from all tabs and buttons
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Add active class to selected tab and button
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Load data for the selected tab
            if (tabName === 'summary') {
                loadScheduleSummary();
            } else if (tabName === 'labor') {
                loadLaborCosts();
            }
        }

        // Load schedules
        function loadSchedules() {
            const startDate = document.getElementById('schedule-start-date').value;
            const endDate = document.getElementById('schedule-end-date').value;
            const branchId = document.getElementById('schedule-branch-filter').value;
            
            fetch('shift_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_all_schedules&start_date=${startDate}&end_date=${endDate}&branch_id=${branchId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySchedules(data.schedules);
                } else {
                    document.getElementById('schedules-container').innerHTML = 
                        '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading schedules: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading schedules:', error);
                document.getElementById('schedules-container').innerHTML = 
                    '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading schedules. Please try again.</div>';
            });
        }

        // Display schedules
        function displaySchedules(schedules) {
            const container = document.getElementById('schedules-container');
            
            if (schedules.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No schedules found for the selected period</p>';
                return;
            }

            let html = `
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Branch</th>
                            <th>Staff</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Shift Type</th>
                            <th>Status</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            schedules.forEach(schedule => {
                const statusClass = `status-${schedule.status}`;
                const statusText = schedule.status.charAt(0).toUpperCase() + schedule.status.slice(1);
                
                let attendanceInfo = '';
                if (schedule.actual_start_time) {
                    attendanceInfo = `
                        <div style="font-size: 12px;">
                            <div>Started: ${new Date(schedule.actual_start_time).toLocaleTimeString()}</div>
                            ${schedule.actual_end_time ? `<div>Ended: ${new Date(schedule.actual_end_time).toLocaleTimeString()}</div>` : ''}
                            ${schedule.total_hours ? `<div>Hours: ${parseFloat(schedule.total_hours).toFixed(2)}</div>` : ''}
                        </div>
                    `;
                } else {
                    attendanceInfo = '<span style="color: #666;">No attendance</span>';
                }
                
                html += `
                    <tr>
                        <td>${new Date(schedule.shift_date).toLocaleDateString()}</td>
                        <td>${schedule.branch_name}</td>
                        <td>${schedule.user_name}</td>
                        <td>${schedule.start_time}</td>
                        <td>${schedule.end_time}</td>
                        <td>${schedule.shift_type.replace('_', ' ').toUpperCase()}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${attendanceInfo}</td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Load schedule summary
        function loadScheduleSummary() {
            const startDate = document.getElementById('schedule-start-date').value;
            const endDate = document.getElementById('schedule-end-date').value;
            
            fetch('shift_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_schedule_summary&start_date=${startDate}&end_date=${endDate}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayScheduleSummary(data.summary);
                } else {
                    document.getElementById('schedule-summary-container').innerHTML = 
                        '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading summary: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading summary:', error);
                document.getElementById('schedule-summary-container').innerHTML = 
                    '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading summary. Please try again.</div>';
            });
        }

        // Display schedule summary
        function displayScheduleSummary(summary) {
            const container = document.getElementById('schedule-summary-container');
            
            if (summary.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No summary data found</p>';
                return;
            }

            let html = '<div class="summary-grid">';

            summary.forEach(branch => {
                const completionRate = branch.total_schedules > 0 ? 
                    ((branch.completed_count / branch.total_schedules) * 100).toFixed(1) : 0;
                
                html += `
                    <div class="summary-card">
                        <h4>${branch.branch_name}</h4>
                        <div class="summary-item">
                            <span>Total Schedules:</span>
                            <span>${branch.total_schedules}</span>
                        </div>
                        <div class="summary-item">
                            <span>Scheduled:</span>
                            <span>${branch.scheduled_count}</span>
                        </div>
                        <div class="summary-item">
                            <span>Started:</span>
                            <span>${branch.started_count}</span>
                        </div>
                        <div class="summary-item">
                            <span>Completed:</span>
                            <span style="color: #28a745;">${branch.completed_count}</span>
                        </div>
                        <div class="summary-item">
                            <span>Cancelled:</span>
                            <span style="color: #dc3545;">${branch.cancelled_count}</span>
                        </div>
                        <div class="summary-item">
                            <span>Avg Hours:</span>
                            <span>${parseFloat(branch.avg_hours_worked || 0).toFixed(2)}</span>
                        </div>
                        <div class="summary-item">
                            <span>Completion Rate:</span>
                            <span style="color: ${parseFloat(completionRate) >= 80 ? '#28a745' : '#ffc107'};">
                                ${completionRate}%
                            </span>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        // Load labor costs
        function loadLaborCosts() {
            const startDate = document.getElementById('labor-start-date').value;
            const endDate = document.getElementById('labor-end-date').value;
            const branchId = document.getElementById('labor-branch-filter').value;
            
            fetch('shift_schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_labor_costs&start_date=${startDate}&end_date=${endDate}&branch_id=${branchId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayLaborCosts(data.labor_costs);
                } else {
                    document.getElementById('labor-costs-container').innerHTML = 
                        '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading labor costs: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading labor costs:', error);
                document.getElementById('labor-costs-container').innerHTML = 
                    '<div style="text-align: center; color: #ef4444; padding: 40px;">Error loading labor costs. Please try again.</div>';
            });
        }

        // Display labor costs
        function displayLaborCosts(laborCosts) {
            const container = document.getElementById('labor-costs-container');
            
            if (laborCosts.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 40px;">No labor cost data found</p>';
                return;
            }

            let html = `
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Branch</th>
                            <th>Staff</th>
                            <th>Hours Worked</th>
                            <th>Hourly Rate</th>
                            <th>Overtime Hours</th>
                            <th>Overtime Rate</th>
                            <th>Total Cost</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            laborCosts.forEach(cost => {
                const statusClass = `status-${cost.status}`;
                const statusText = cost.status.charAt(0).toUpperCase() + cost.status.slice(1);
                
                html += `
                    <tr>
                        <td>${new Date(cost.shift_date).toLocaleDateString()}</td>
                        <td>${cost.branch_name}</td>
                        <td>${cost.user_name}</td>
                        <td>${parseFloat(cost.hours_worked).toFixed(2)}</td>
                        <td>PKR ${parseFloat(cost.hourly_rate).toFixed(2)}</td>
                        <td>${parseFloat(cost.overtime_hours).toFixed(2)}</td>
                        <td>PKR ${parseFloat(cost.overtime_rate).toFixed(2)}</td>
                        <td style="font-weight: bold;">PKR ${parseFloat(cost.total_labor_cost).toFixed(2)}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Filter forms
        document.getElementById('schedule-filter-form').addEventListener('submit', function(e) {
            e.preventDefault();
            loadSchedules();
        });

        document.getElementById('labor-filter-form').addEventListener('submit', function(e) {
            e.preventDefault();
            loadLaborCosts();
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
