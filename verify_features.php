<?php
/**
 * Feature Verification Script
 * This script verifies that all Revenue Reconciliation and Shift Schedule features are properly set up
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Feature Verification</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #20bf55; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 5px; }
        .btn:hover { background: #1aa049; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .test-section h3 { margin: 0 0 15px 0; color: #333; }
        .test-result { padding: 8px 12px; margin: 5px 0; border-radius: 4px; display: flex; align-items: center; }
        .test-pass { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .test-fail { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .test-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .icon { margin-right: 10px; font-weight: bold; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #dee2e6; }
        .stat-number { font-size: 2em; font-weight: bold; margin-bottom: 5px; }
        .stat-label { color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Feature Verification</h1>
        <p>This script verifies that all Revenue Reconciliation and Shift Schedule features are properly set up and working.</p>

        <?php
        $verification_results = [];
        $total_tests = 0;
        $passed_tests = 0;
        $failed_tests = 0;
        $warnings = 0;

        function addTestResult(&$results, &$total, &$passed, &$failed, &$warnings, $test_name, $status, $message = '') {
            $total++;
            $results[] = ['name' => $test_name, 'status' => $status, 'message' => $message];
            if ($status === 'pass') $passed++;
            elseif ($status === 'fail') $failed++;
            elseif ($status === 'warning') $warnings++;
        }

        // Test 1: Database Connection
        echo "<div class='test-section'>";
        echo "<h3>🔌 Database Connection</h3>";
        try {
            $db->query("SELECT 1");
            addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Database Connection", "pass", "Connected successfully");
            echo "<div class='test-result test-pass'><span class='icon'>✅</span> Database connection successful</div>";
        } catch (Exception $e) {
            addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Database Connection", "fail", $e->getMessage());
            echo "<div class='test-result test-fail'><span class='icon'>❌</span> Database connection failed: " . $e->getMessage() . "</div>";
        }
        echo "</div>";

        // Test 2: Required Tables
        echo "<div class='test-section'>";
        echo "<h3>📊 Database Tables</h3>";
        $required_tables = [
            'cash_drawers' => 'Cash drawer management',
            'payment_methods' => 'Payment method definitions',
            'revenue_reconciliation' => 'Revenue tracking and reconciliation',
            'shift_schedules' => 'Staff shift scheduling',
            'shift_attendance' => 'Attendance tracking',
            'shift_templates' => 'Schedule templates',
            'shift_template_assignments' => 'Template assignments',
            'labor_costs' => 'Labor cost calculations'
        ];

        foreach ($required_tables as $table => $description) {
            try {
                $stmt = $db->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Table: $table", "pass", "$count records");
                echo "<div class='test-result test-pass'><span class='icon'>✅</span> $description ($table): $count records</div>";
            } catch (Exception $e) {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Table: $table", "fail", $e->getMessage());
                echo "<div class='test-result test-fail'><span class='icon'>❌</span> $description ($table): " . $e->getMessage() . "</div>";
            }
        }
        echo "</div>";

        // Test 3: Enhanced Columns
        echo "<div class='test-section'>";
        echo "<h3>🔧 Enhanced Columns</h3>";
        
        // Test orders table enhancements
        try {
            $stmt = $db->query("SHOW COLUMNS FROM orders LIKE 'payment_method_id'");
            if ($stmt->fetch()) {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Orders: payment_method_id", "pass", "Column exists");
                echo "<div class='test-result test-pass'><span class='icon'>✅</span> Orders table has payment_method_id column</div>";
            } else {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Orders: payment_method_id", "fail", "Column missing");
                echo "<div class='test-result test-fail'><span class='icon'>❌</span> Orders table missing payment_method_id column</div>";
            }
        } catch (Exception $e) {
            addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Orders: payment_method_id", "fail", $e->getMessage());
            echo "<div class='test-result test-fail'><span class='icon'>❌</span> Orders payment_method_id column test failed: " . $e->getMessage() . "</div>";
        }

        try {
            $stmt = $db->query("SHOW COLUMNS FROM orders LIKE 'cash_drawer_id'");
            if ($stmt->fetch()) {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Orders: cash_drawer_id", "pass", "Column exists");
                echo "<div class='test-result test-pass'><span class='icon'>✅</span> Orders table has cash_drawer_id column</div>";
            } else {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Orders: cash_drawer_id", "fail", "Column missing");
                echo "<div class='test-result test-fail'><span class='icon'>❌</span> Orders table missing cash_drawer_id column</div>";
            }
        } catch (Exception $e) {
            addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Orders: cash_drawer_id", "fail", $e->getMessage());
            echo "<div class='test-result test-fail'><span class='icon'>❌</span> Orders cash_drawer_id column test failed: " . $e->getMessage() . "</div>";
        }

        // Test users table enhancements
        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'hourly_rate'");
            if ($stmt->fetch()) {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Users: hourly_rate", "pass", "Column exists");
                echo "<div class='test-result test-pass'><span class='icon'>✅</span> Users table has hourly_rate column</div>";
            } else {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Users: hourly_rate", "fail", "Column missing");
                echo "<div class='test-result test-fail'><span class='icon'>❌</span> Users table missing hourly_rate column</div>";
            }
        } catch (Exception $e) {
            addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Users: hourly_rate", "fail", $e->getMessage());
            echo "<div class='test-result test-fail'><span class='icon'>❌</span> Users hourly_rate column test failed: " . $e->getMessage() . "</div>";
        }

        try {
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'overtime_rate'");
            if ($stmt->fetch()) {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Users: overtime_rate", "pass", "Column exists");
                echo "<div class='test-result test-pass'><span class='icon'>✅</span> Users table has overtime_rate column</div>";
            } else {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Users: overtime_rate", "fail", "Column missing");
                echo "<div class='test-result test-fail'><span class='icon'>❌</span> Users table missing overtime_rate column</div>";
            }
        } catch (Exception $e) {
            addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Users: overtime_rate", "fail", $e->getMessage());
            echo "<div class='test-result test-fail'><span class='icon'>❌</span> Users overtime_rate column test failed: " . $e->getMessage() . "</div>";
        }
        echo "</div>";

        // Test 4: Default Data
        echo "<div class='test-section'>";
        echo "<h3>📋 Default Data</h3>";
        
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM payment_methods");
            $count = $stmt->fetchColumn();
            if ($count >= 6) {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Payment Methods Data", "pass", "$count methods loaded");
                echo "<div class='test-result test-pass'><span class='icon'>✅</span> Payment methods loaded: $count methods</div>";
            } else {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Payment Methods Data", "warning", "Only $count methods loaded (expected 6)");
                echo "<div class='test-result test-warning'><span class='icon'>⚠️</span> Payment methods: Only $count loaded (expected 6)</div>";
            }
        } catch (Exception $e) {
            addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "Payment Methods Data", "fail", $e->getMessage());
            echo "<div class='test-result test-fail'><span class='icon'>❌</span> Payment methods test failed: " . $e->getMessage() . "</div>";
        }
        echo "</div>";

        // Test 5: File Access
        echo "<div class='test-section'>";
        echo "<h3>📁 File Access</h3>";
        
        $required_files = [
            'admin/revenue_reconciliation.php' => 'Branch Admin Revenue Reconciliation',
            'admin/shift_schedule.php' => 'Branch Admin Shift Schedule',
            'super_admin/revenue_reconciliation.php' => 'Super Admin Revenue Reconciliation',
            'super_admin/shift_schedule.php' => 'Super Admin Shift Schedule'
        ];

        foreach ($required_files as $file => $description) {
            if (file_exists($file)) {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "File: $file", "pass", "File exists");
                echo "<div class='test-result test-pass'><span class='icon'>✅</span> $description: File exists</div>";
            } else {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "File: $file", "fail", "File missing");
                echo "<div class='test-result test-fail'><span class='icon'>❌</span> $description: File missing</div>";
            }
        }
        echo "</div>";

        // Test 6: Database Views
        echo "<div class='test-section'>";
        echo "<h3>📊 Database Views</h3>";
        
        $views = [
            'daily_revenue_summary' => 'Daily revenue summary view',
            'shift_summary' => 'Shift summary view',
            'labor_cost_summary' => 'Labor cost summary view'
        ];

        foreach ($views as $view => $description) {
            try {
                $stmt = $db->query("SELECT COUNT(*) FROM $view");
                $count = $stmt->fetchColumn();
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "View: $view", "pass", "$count records");
                echo "<div class='test-result test-pass'><span class='icon'>✅</span> $description: $count records</div>";
            } catch (Exception $e) {
                addTestResult($verification_results, $total_tests, $passed_tests, $failed_tests, $warnings, "View: $view", "fail", $e->getMessage());
                echo "<div class='test-result test-fail'><span class='icon'>❌</span> $description: " . $e->getMessage() . "</div>";
            }
        }
        echo "</div>";

        // Summary Statistics
        echo "<div class='stats'>";
        echo "<div class='stat-card'>";
        echo "<div class='stat-number' style='color: #28a745;'>$passed_tests</div>";
        echo "<div class='stat-label'>Tests Passed</div>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<div class='stat-number' style='color: #dc3545;'>$failed_tests</div>";
        echo "<div class='stat-label'>Tests Failed</div>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<div class='stat-number' style='color: #ffc107;'>$warnings</div>";
        echo "<div class='stat-label'>Warnings</div>";
        echo "</div>";
        
        echo "<div class='stat-card'>";
        echo "<div class='stat-number' style='color: #17a2b8;'>$total_tests</div>";
        echo "<div class='stat-label'>Total Tests</div>";
        echo "</div>";
        echo "</div>";

        // Final Summary
        if ($failed_tests === 0) {
            echo "<div class='success'>";
            echo "<h3>🎉 All Features Ready!</h3>";
            echo "<p>All tests passed! Your Revenue Reconciliation and Shift Schedule features are properly set up and ready to use.</p>";
            echo "</div>";
            
            echo "<h4>🔗 Access Your Features:</h4>";
            echo "<a href='admin/index.php' class='btn'>Branch Admin Dashboard</a>";
            echo "<a href='super_admin/index.php' class='btn btn-secondary'>Super Admin Dashboard</a>";
            echo "<a href='admin/revenue_reconciliation.php' class='btn'>Revenue Reconciliation</a>";
            echo "<a href='admin/shift_schedule.php' class='btn'>Shift Schedule</a>";
            
        } elseif ($failed_tests <= 2) {
            echo "<div class='warning'>";
            echo "<h3>⚠️ Minor Issues Detected</h3>";
            echo "<p>Most features are working, but there are a few minor issues that should be addressed.</p>";
            echo "</div>";
            
            echo "<h4>🔧 Recommended Actions:</h4>";
            echo "<ul>";
            echo "<li>Run the <a href='complete_setup.php'>Complete Setup</a> script to fix any missing components</li>";
            echo "<li>Check the failed tests above for specific issues</li>";
            echo "</ul>";
            
        } else {
            echo "<div class='error'>";
            echo "<h3>❌ Setup Issues Detected</h3>";
            echo "<p>Several components are missing or not properly configured. Please run the setup script.</p>";
            echo "</div>";
            
            echo "<h4>🔧 Required Actions:</h4>";
            echo "<ul>";
            echo "<li>Run the <a href='complete_setup.php'>Complete Setup</a> script</li>";
            echo "<li>Check your database connection and permissions</li>";
            echo "<li>Verify all required files are uploaded</li>";
            echo "</ul>";
        }

        if ($warnings > 0) {
            echo "<div class='info'>";
            echo "<h4>ℹ️ Warnings ($warnings)</h4>";
            echo "<p>These are non-critical issues that don't prevent the features from working.</p>";
            echo "</div>";
        }
        ?>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h4>🔄 Re-run Verification</h4>
            <p>Click the button below to run the verification again:</p>
            <a href="verify_features.php" class="btn">Re-run Verification</a>
            <a href="complete_setup.php" class="btn btn-secondary">Run Setup</a>
        </div>
    </div>
</body>
</html>
