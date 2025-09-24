<?php
/**
 * Complete Setup Script for Revenue Reconciliation and Shift Schedule Features
 * This script runs each SQL statement individually with proper error handling
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Complete Feature Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #20bf55; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 5px; border: none; cursor: pointer; }
        .btn:hover { background: #1aa049; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .step { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .step h4 { margin: 0 0 10px 0; color: #333; }
        .progress { width: 100%; background: #f0f0f0; border-radius: 10px; margin: 10px 0; }
        .progress-bar { height: 20px; background: #20bf55; border-radius: 10px; transition: width 0.3s ease; }
        .test-result { padding: 5px 10px; margin: 5px 0; border-radius: 3px; }
        .test-pass { background: #d4edda; color: #155724; }
        .test-fail { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Complete Feature Setup</h1>
        <p>This script will set up all database tables and features for Revenue Reconciliation and Shift Schedule Management.</p>

        <?php
        if ($_POST['action'] ?? '' === 'setup') {
            echo "<h2>Starting Complete Setup...</h2>";
            
            $setup_steps = [
                'Creating cash_drawers table',
                'Creating payment_methods table',
                'Adding payment_method_id column to orders',
                'Adding cash_drawer_id column to orders',
                'Creating revenue_reconciliation table',
                'Creating shift_schedules table',
                'Creating shift_attendance table',
                'Creating shift_templates table',
                'Creating shift_template_assignments table',
                'Creating labor_costs table',
                'Adding hourly_rate column to users',
                'Adding overtime_rate column to users',
                'Inserting default payment methods',
                'Creating performance indexes',
                'Creating reporting views',
                'Testing all tables'
            ];
            
            $completed_steps = 0;
            $total_steps = count($setup_steps);
            $errors = [];
            $warnings = [];
            
            try {
                echo "<div class='progress'><div class='progress-bar' style='width: 0%'></div></div>";
                
                // Step 1: Create cash_drawers table
                echo "<div class='step'><h4>Step 1: Creating cash_drawers table</h4>";
                $sql = "CREATE TABLE IF NOT EXISTS `cash_drawers` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `branch_id` int(11) NOT NULL,
                    `user_id` int(11) NOT NULL,
                    `shift_date` date NOT NULL,
                    `opening_cash` decimal(10,2) DEFAULT 0.00,
                    `closing_cash` decimal(10,2) DEFAULT NULL,
                    `expected_cash` decimal(10,2) DEFAULT NULL,
                    `actual_cash` decimal(10,2) DEFAULT NULL,
                    `variance` decimal(10,2) DEFAULT NULL,
                    `status` enum('open','closed','reconciled') DEFAULT 'open',
                    `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `closed_at` timestamp NULL DEFAULT NULL,
                    `reconciled_at` timestamp NULL DEFAULT NULL,
                    `reconciled_by` int(11) DEFAULT NULL,
                    `notes` text DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_user_shift` (`user_id`, `shift_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created cash_drawers table successfully</div>";
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 2: Create payment_methods table
                echo "<div class='step'><h4>Step 2: Creating payment_methods table</h4>";
                $sql = "CREATE TABLE IF NOT EXISTS `payment_methods` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(50) NOT NULL,
                    `type` enum('cash','card','digital','other') NOT NULL,
                    `is_active` tinyint(1) DEFAULT 1,
                    `sort_order` int(11) DEFAULT 0,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created payment_methods table successfully</div>";
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 3: Add payment_method_id column to orders
                echo "<div class='step'><h4>Step 3: Adding payment_method_id column to orders table</h4>";
                try {
                    $db->exec("ALTER TABLE `orders` ADD COLUMN `payment_method_id` int(11) DEFAULT NULL AFTER `payment_status`");
                    echo "<div class='success'>✅ Added payment_method_id column to orders table</div>";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                        echo "<div class='warning'>⚠️ payment_method_id column already exists in orders table</div>";
                        $warnings[] = "payment_method_id column already exists";
                    } else {
                        throw $e;
                    }
                }
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 4: Add cash_drawer_id column to orders
                echo "<div class='step'><h4>Step 4: Adding cash_drawer_id column to orders table</h4>";
                try {
                    $db->exec("ALTER TABLE `orders` ADD COLUMN `cash_drawer_id` int(11) DEFAULT NULL AFTER `payment_method_id`");
                    echo "<div class='success'>✅ Added cash_drawer_id column to orders table</div>";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                        echo "<div class='warning'>⚠️ cash_drawer_id column already exists in orders table</div>";
                        $warnings[] = "cash_drawer_id column already exists";
                    } else {
                        throw $e;
                    }
                }
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 5: Create revenue_reconciliation table
                echo "<div class='step'><h4>Step 5: Creating revenue_reconciliation table</h4>";
                $sql = "CREATE TABLE IF NOT EXISTS `revenue_reconciliation` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `branch_id` int(11) NOT NULL,
                    `cash_drawer_id` int(11) NOT NULL,
                    `reconciliation_date` date NOT NULL,
                    `total_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `cash_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `card_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `digital_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `other_sales` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `opening_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `expected_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `actual_cash` decimal(10,2) DEFAULT NULL,
                    `cash_variance` decimal(10,2) DEFAULT NULL,
                    `status` enum('pending','reconciled','discrepancy') DEFAULT 'pending',
                    `reconciled_by` int(11) DEFAULT NULL,
                    `reconciled_at` timestamp NULL DEFAULT NULL,
                    `notes` text DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_drawer_reconciliation` (`cash_drawer_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created revenue_reconciliation table successfully</div>";
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 6: Create shift_schedules table
                echo "<div class='step'><h4>Step 6: Creating shift_schedules table</h4>";
                $sql = "CREATE TABLE IF NOT EXISTS `shift_schedules` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `branch_id` int(11) NOT NULL,
                    `user_id` int(11) NOT NULL,
                    `shift_date` date NOT NULL,
                    `start_time` time NOT NULL,
                    `end_time` time NOT NULL,
                    `shift_type` enum('morning','afternoon','evening','night','full_day') DEFAULT 'full_day',
                    `status` enum('scheduled','confirmed','started','completed','cancelled') DEFAULT 'scheduled',
                    `notes` text DEFAULT NULL,
                    `created_by` int(11) NOT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_user_shift_schedule` (`user_id`, `shift_date`, `start_time`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created shift_schedules table successfully</div>";
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 7: Create shift_attendance table
                echo "<div class='step'><h4>Step 7: Creating shift_attendance table</h4>";
                $sql = "CREATE TABLE IF NOT EXISTS `shift_attendance` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `shift_schedule_id` int(11) NOT NULL,
                    `user_id` int(11) NOT NULL,
                    `actual_start_time` timestamp NULL DEFAULT NULL,
                    `actual_end_time` timestamp NULL DEFAULT NULL,
                    `break_start` timestamp NULL DEFAULT NULL,
                    `break_end` timestamp NULL DEFAULT NULL,
                    `total_hours` decimal(4,2) DEFAULT 0.00,
                    `status` enum('present','absent','late','early_departure') DEFAULT 'present',
                    `notes` text DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_shift_attendance` (`shift_schedule_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created shift_attendance table successfully</div>";
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 8: Create shift_templates table
                echo "<div class='step'><h4>Step 8: Creating shift_templates table</h4>";
                $sql = "CREATE TABLE IF NOT EXISTS `shift_templates` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `branch_id` int(11) NOT NULL,
                    `template_name` varchar(100) NOT NULL,
                    `description` text DEFAULT NULL,
                    `is_active` tinyint(1) DEFAULT 1,
                    `created_by` int(11) NOT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created shift_templates table successfully</div>";
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 9: Create shift_template_assignments table
                echo "<div class='step'><h4>Step 9: Creating shift_template_assignments table</h4>";
                $sql = "CREATE TABLE IF NOT EXISTS `shift_template_assignments` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `template_id` int(11) NOT NULL,
                    `user_id` int(11) NOT NULL,
                    `day_of_week` enum('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NOT NULL,
                    `start_time` time NOT NULL,
                    `end_time` time NOT NULL,
                    `shift_type` enum('morning','afternoon','evening','night','full_day') DEFAULT 'full_day',
                    `is_active` tinyint(1) DEFAULT 1,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_template_user_day` (`template_id`, `user_id`, `day_of_week`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created shift_template_assignments table successfully</div>";
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 10: Create labor_costs table
                echo "<div class='step'><h4>Step 10: Creating labor_costs table</h4>";
                $sql = "CREATE TABLE IF NOT EXISTS `labor_costs` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `branch_id` int(11) NOT NULL,
                    `user_id` int(11) NOT NULL,
                    `shift_date` date NOT NULL,
                    `hours_worked` decimal(4,2) NOT NULL,
                    `hourly_rate` decimal(8,2) NOT NULL,
                    `total_cost` decimal(10,2) NOT NULL,
                    `overtime_hours` decimal(4,2) DEFAULT 0.00,
                    `overtime_rate` decimal(8,2) DEFAULT 0.00,
                    `overtime_cost` decimal(10,2) DEFAULT 0.00,
                    `total_labor_cost` decimal(10,2) NOT NULL,
                    `status` enum('pending','approved','paid') DEFAULT 'pending',
                    `approved_by` int(11) DEFAULT NULL,
                    `approved_at` timestamp NULL DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_user_date_labor` (`user_id`, `shift_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created labor_costs table successfully</div>";
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 11: Add hourly_rate column to users
                echo "<div class='step'><h4>Step 11: Adding hourly_rate column to users table</h4>";
                try {
                    $db->exec("ALTER TABLE `users` ADD COLUMN `hourly_rate` decimal(8,2) DEFAULT 0.00 AFTER `branch_id`");
                    echo "<div class='success'>✅ Added hourly_rate column to users table</div>";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                        echo "<div class='warning'>⚠️ hourly_rate column already exists in users table</div>";
                        $warnings[] = "hourly_rate column already exists";
                    } else {
                        throw $e;
                    }
                }
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 12: Add overtime_rate column to users
                echo "<div class='step'><h4>Step 12: Adding overtime_rate column to users table</h4>";
                try {
                    $db->exec("ALTER TABLE `users` ADD COLUMN `overtime_rate` decimal(8,2) DEFAULT 0.00 AFTER `hourly_rate`");
                    echo "<div class='success'>✅ Added overtime_rate column to users table</div>";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                        echo "<div class='warning'>⚠️ overtime_rate column already exists in users table</div>";
                        $warnings[] = "overtime_rate column already exists";
                    } else {
                        throw $e;
                    }
                }
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 13: Insert default payment methods
                echo "<div class='step'><h4>Step 13: Inserting default payment methods</h4>";
                $payment_methods = [
                    ['Cash', 'cash', 1],
                    ['Credit Card', 'card', 2],
                    ['Debit Card', 'card', 3],
                    ['Digital Wallet', 'digital', 4],
                    ['Bank Transfer', 'digital', 5],
                    ['Other', 'other', 6]
                ];
                
                $stmt = $db->prepare("INSERT IGNORE INTO payment_methods (name, type, sort_order) VALUES (?, ?, ?)");
                $inserted = 0;
                foreach ($payment_methods as $method) {
                    $stmt->execute($method);
                    if ($stmt->rowCount() > 0) $inserted++;
                }
                echo "<div class='success'>✅ Inserted $inserted default payment methods</div>";
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 14: Create performance indexes
                echo "<div class='step'><h4>Step 14: Creating performance indexes</h4>";
                $indexes = [
                    "CREATE INDEX IF NOT EXISTS `idx_cash_drawers_branch_date` ON `cash_drawers` (`branch_id`, `shift_date`)",
                    "CREATE INDEX IF NOT EXISTS `idx_cash_drawers_user_date` ON `cash_drawers` (`user_id`, `shift_date`)",
                    "CREATE INDEX IF NOT EXISTS `idx_revenue_reconciliation_branch_date` ON `revenue_reconciliation` (`branch_id`, `reconciliation_date`)",
                    "CREATE INDEX IF NOT EXISTS `idx_shift_schedules_branch_date` ON `shift_schedules` (`branch_id`, `shift_date`)",
                    "CREATE INDEX IF NOT EXISTS `idx_shift_schedules_user_date` ON `shift_schedules` (`user_id`, `shift_date`)",
                    "CREATE INDEX IF NOT EXISTS `idx_shift_attendance_date` ON `shift_attendance` (`created_at`)",
                    "CREATE INDEX IF NOT EXISTS `idx_labor_costs_branch_date` ON `labor_costs` (`branch_id`, `shift_date`)",
                    "CREATE INDEX IF NOT EXISTS `idx_labor_costs_user_date` ON `labor_costs` (`user_id`, `shift_date`)"
                ];
                
                $index_created = 0;
                foreach ($indexes as $index_sql) {
                    try {
                        $db->exec($index_sql);
                        $index_created++;
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                            $index_created++; // Count as success since index already exists
                        }
                    }
                }
                echo "<div class='success'>✅ Created $index_created performance indexes</div>";
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 15: Create reporting views
                echo "<div class='step'><h4>Step 15: Creating reporting views</h4>";
                $views = [
                    "CREATE OR REPLACE VIEW `daily_revenue_summary` AS
                     SELECT 
                         rr.branch_id,
                         b.name as branch_name,
                         rr.reconciliation_date,
                         rr.total_sales,
                         rr.cash_sales,
                         rr.card_sales,
                         rr.digital_sales,
                         rr.other_sales,
                         rr.cash_variance,
                         rr.status,
                         u.name as reconciled_by_name
                     FROM revenue_reconciliation rr
                     JOIN branches b ON rr.branch_id = b.id
                     LEFT JOIN users u ON rr.reconciled_by = u.id",
                     
                    "CREATE OR REPLACE VIEW `shift_summary` AS
                     SELECT 
                         ss.branch_id,
                         b.name as branch_name,
                         ss.shift_date,
                         ss.start_time,
                         ss.end_time,
                         ss.shift_type,
                         ss.status,
                         u.name as user_name,
                         sa.actual_start_time,
                         sa.actual_end_time,
                         sa.total_hours,
                         sa.status as attendance_status
                     FROM shift_schedules ss
                     JOIN branches b ON ss.branch_id = b.id
                     JOIN users u ON ss.user_id = u.id
                     LEFT JOIN shift_attendance sa ON ss.id = sa.shift_schedule_id",
                     
                    "CREATE OR REPLACE VIEW `labor_cost_summary` AS
                     SELECT 
                         lc.branch_id,
                         b.name as branch_name,
                         lc.shift_date,
                         u.name as user_name,
                         lc.hours_worked,
                         lc.hourly_rate,
                         lc.total_cost,
                         lc.overtime_hours,
                         lc.overtime_cost,
                         lc.total_labor_cost,
                         lc.status
                     FROM labor_costs lc
                     JOIN branches b ON lc.branch_id = b.id
                     JOIN users u ON lc.user_id = u.id"
                ];
                
                $views_created = 0;
                foreach ($views as $view_sql) {
                    try {
                        $db->exec($view_sql);
                        $views_created++;
                    } catch (PDOException $e) {
                        echo "<div class='warning'>⚠️ View creation warning: " . $e->getMessage() . "</div>";
                        $warnings[] = "View creation: " . $e->getMessage();
                    }
                }
                echo "<div class='success'>✅ Created $views_created reporting views</div>";
                $completed_steps++;
                echo "<div class='progress'><div class='progress-bar' style='width: " . ($completed_steps/$total_steps*100) . "%'></div></div>";
                echo "</div>";
                
                // Step 16: Test all tables
                echo "<div class='step'><h4>Step 16: Testing all tables and features</h4>";
                $tables_to_test = [
                    'cash_drawers' => 'Cash drawer management',
                    'payment_methods' => 'Payment method definitions',
                    'revenue_reconciliation' => 'Revenue tracking and reconciliation',
                    'shift_schedules' => 'Staff shift scheduling',
                    'shift_attendance' => 'Attendance tracking',
                    'shift_templates' => 'Schedule templates',
                    'shift_template_assignments' => 'Template assignments',
                    'labor_costs' => 'Labor cost calculations'
                ];
                
                $test_results = [];
                foreach ($tables_to_test as $table => $description) {
                    try {
                        $stmt = $db->query("SELECT COUNT(*) FROM $table");
                        $count = $stmt->fetchColumn();
                        $test_results[] = "<div class='test-result test-pass'>✅ $description ($table): $count records</div>";
                    } catch (PDOException $e) {
                        $test_results[] = "<div class='test-result test-fail'>❌ $description ($table): " . $e->getMessage() . "</div>";
                        $errors[] = "Table test failed for $table";
                    }
                }
                
                foreach ($test_results as $result) {
                    echo $result;
                }
                
                // Test columns in existing tables
                echo "<h5>Testing enhanced columns:</h5>";
                try {
                    $stmt = $db->query("SELECT COUNT(*) FROM orders WHERE payment_method_id IS NULL");
                    echo "<div class='test-result test-pass'>✅ Orders table has payment_method_id column</div>";
                } catch (PDOException $e) {
                    echo "<div class='test-result test-fail'>❌ Orders payment_method_id column: " . $e->getMessage() . "</div>";
                    $errors[] = "Orders payment_method_id column missing";
                }
                
                try {
                    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE hourly_rate IS NULL");
                    echo "<div class='test-result test-pass'>✅ Users table has hourly_rate column</div>";
                } catch (PDOException $e) {
                    echo "<div class='test-result test-fail'>❌ Users hourly_rate column: " . $e->getMessage() . "</div>";
                    $errors[] = "Users hourly_rate column missing";
                }
                
                echo "</div>";
                
                // Final summary
                echo "<div class='step'>";
                echo "<h3>🎉 Setup Complete!</h3>";
                
                if (empty($errors)) {
                    echo "<div class='success'>";
                    echo "<h4>✅ All features are ready to use!</h4>";
                    echo "<p><strong>Completed Steps:</strong> $completed_steps / $total_steps</p>";
                    if (!empty($warnings)) {
                        echo "<p><strong>Warnings:</strong> " . count($warnings) . " (non-critical)</p>";
                    }
                    echo "</div>";
                    
                    echo "<h4>📋 Next Steps:</h4>";
                    echo "<ul>";
                    echo "<li>Set hourly rates for your staff members in user management</li>";
                    echo "<li>Start using the Revenue Reconciliation feature for cash drawer management</li>";
                    echo "<li>Create shift schedules for your staff</li>";
                    echo "<li>Monitor labor costs and reconciliation reports</li>";
                    echo "</ul>";
                    
                    echo "<h4>🔗 Access the Features:</h4>";
                    echo "<a href='admin/index.php' class='btn'>Branch Admin Dashboard</a>";
                    echo "<a href='super_admin/index.php' class='btn btn-secondary'>Super Admin Dashboard</a>";
                    echo "<a href='admin/revenue_reconciliation.php' class='btn'>Revenue Reconciliation</a>";
                    echo "<a href='admin/shift_schedule.php' class='btn'>Shift Schedule</a>";
                    
                } else {
                    echo "<div class='error'>";
                    echo "<h4>❌ Setup completed with errors</h4>";
                    echo "<p><strong>Errors:</strong> " . count($errors) . "</p>";
                    echo "<p><strong>Warnings:</strong> " . count($warnings) . "</p>";
                    echo "</div>";
                    
                    echo "<h4>Errors encountered:</h4>";
                    echo "<ul>";
                    foreach ($errors as $error) {
                        echo "<li>$error</li>";
                    }
                    echo "</ul>";
                }
                echo "</div>";
                
            } catch (Exception $e) {
                echo "<div class='error'>";
                echo "<h3>❌ Setup Failed</h3>";
                echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
                echo "<p>Please check your database connection and try again.</p>";
                echo "</div>";
            }
            
        } else {
            ?>
            <div class="info">
                <h3>ℹ️ About This Setup</h3>
                <p>This comprehensive setup script will create all necessary database tables and features for:</p>
                <ul>
                    <li><strong>Revenue Reconciliation System</strong> - Cash drawer management and revenue tracking</li>
                    <li><strong>Shift Schedule Management</strong> - Staff scheduling, attendance tracking, and labor cost management</li>
                </ul>
            </div>
            
            <div class="warning">
                <h3>⚠️ Important Notes</h3>
                <ul>
                    <li><strong>Backup your database</strong> before proceeding</li>
                    <li>This script is safe to run multiple times</li>
                    <li>Existing tables and columns will be skipped</li>
                    <li>Default payment methods will be inserted</li>
                </ul>
            </div>
            
            <h3>📋 What will be created:</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4>New Tables:</h4>
                    <ul>
                        <li>cash_drawers</li>
                        <li>payment_methods</li>
                        <li>revenue_reconciliation</li>
                        <li>shift_schedules</li>
                        <li>shift_attendance</li>
                        <li>shift_templates</li>
                        <li>shift_template_assignments</li>
                        <li>labor_costs</li>
                    </ul>
                </div>
                <div>
                    <h4>Enhanced Tables:</h4>
                    <ul>
                        <li>orders (payment_method_id, cash_drawer_id)</li>
                        <li>users (hourly_rate, overtime_rate)</li>
                    </ul>
                    <h4>Views & Indexes:</h4>
                    <ul>
                        <li>Performance indexes</li>
                        <li>Reporting views</li>
                    </ul>
                </div>
            </div>
            
            <form method="post">
                <button type="submit" name="action" value="setup" class="btn" onclick="return confirm('Are you sure you want to proceed with the complete setup? Make sure you have backed up your database!')">
                    🚀 Start Complete Setup
                </button>
                <a href="setup_new_features.php" class="btn btn-secondary">Use Simple Setup Instead</a>
            </form>
            <?php
        }
        ?>
    </div>
</body>
</html>
