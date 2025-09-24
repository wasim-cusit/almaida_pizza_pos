<?php
/**
 * Quick Setup Script for Revenue Reconciliation and Shift Schedule Features
 * 
 * This script will quickly set up the database tables needed for the new features.
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup New Features</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #20bf55; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 5px; }
        .btn:hover { background: #1aa049; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Setup Revenue Reconciliation & Shift Schedule Features</h1>
        
        <?php
        if ($_POST['action'] ?? '' === 'setup') {
            echo "<h2>Setting up database tables...</h2>";
            
            try {
                $db->beginTransaction();
                
                // Create cash_drawers table
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
                    UNIQUE KEY `unique_user_shift` (`user_id`, `shift_date`),
                    KEY `idx_cash_drawers_branch_date` (`branch_id`, `shift_date`),
                    KEY `idx_cash_drawers_user_date` (`user_id`, `shift_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created cash_drawers table</div>";
                
                // Create payment_methods table
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
                echo "<div class='success'>✅ Created payment_methods table</div>";
                
                // Insert default payment methods
                $payment_methods = [
                    ['Cash', 'cash', 1],
                    ['Credit Card', 'card', 2],
                    ['Debit Card', 'card', 3],
                    ['Digital Wallet', 'digital', 4],
                    ['Bank Transfer', 'digital', 5],
                    ['Other', 'other', 6]
                ];
                
                $stmt = $db->prepare("INSERT IGNORE INTO payment_methods (name, type, sort_order) VALUES (?, ?, ?)");
                foreach ($payment_methods as $method) {
                    $stmt->execute($method);
                }
                echo "<div class='success'>✅ Inserted default payment methods</div>";
                
                // Create revenue_reconciliation table
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
                    UNIQUE KEY `unique_drawer_reconciliation` (`cash_drawer_id`),
                    KEY `idx_revenue_reconciliation_branch_date` (`branch_id`, `reconciliation_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created revenue_reconciliation table</div>";
                
                // Create shift_schedules table
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
                    UNIQUE KEY `unique_user_shift_schedule` (`user_id`, `shift_date`, `start_time`),
                    KEY `idx_shift_schedules_branch_date` (`branch_id`, `shift_date`),
                    KEY `idx_shift_schedules_user_date` (`user_id`, `shift_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created shift_schedules table</div>";
                
                // Create shift_attendance table
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
                    UNIQUE KEY `unique_shift_attendance` (`shift_schedule_id`),
                    KEY `idx_shift_attendance_date` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created shift_attendance table</div>";
                
                // Create labor_costs table
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
                    UNIQUE KEY `unique_user_date_labor` (`user_id`, `shift_date`),
                    KEY `idx_labor_costs_branch_date` (`branch_id`, `shift_date`),
                    KEY `idx_labor_costs_user_date` (`user_id`, `shift_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $db->exec($sql);
                echo "<div class='success'>✅ Created labor_costs table</div>";
                
                // Add columns to existing tables
                try {
                    $db->exec("ALTER TABLE `users` ADD COLUMN `hourly_rate` decimal(8,2) DEFAULT 0.00 AFTER `branch_id`");
                    echo "<div class='success'>✅ Added hourly_rate column to users table</div>";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                        echo "<div class='warning'>⚠️ hourly_rate column already exists in users table</div>";
                    } else {
                        throw $e;
                    }
                }
                
                try {
                    $db->exec("ALTER TABLE `users` ADD COLUMN `overtime_rate` decimal(8,2) DEFAULT 0.00 AFTER `hourly_rate`");
                    echo "<div class='success'>✅ Added overtime_rate column to users table</div>";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                        echo "<div class='warning'>⚠️ overtime_rate column already exists in users table</div>";
                    } else {
                        throw $e;
                    }
                }
                
                try {
                    $db->exec("ALTER TABLE `orders` ADD COLUMN `payment_method_id` int(11) DEFAULT NULL AFTER `payment_status`");
                    echo "<div class='success'>✅ Added payment_method_id column to orders table</div>";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                        echo "<div class='warning'>⚠️ payment_method_id column already exists in orders table</div>";
                    } else {
                        throw $e;
                    }
                }
                
                try {
                    $db->exec("ALTER TABLE `orders` ADD COLUMN `cash_drawer_id` int(11) DEFAULT NULL AFTER `payment_method_id`");
                    echo "<div class='success'>✅ Added cash_drawer_id column to orders table</div>";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                        echo "<div class='warning'>⚠️ cash_drawer_id column already exists in orders table</div>";
                    } else {
                        throw $e;
                    }
                }
                
                $db->commit();
                
                echo "<div class='success'>";
                echo "<h3>🎉 Setup Complete!</h3>";
                echo "<p>All database tables have been created successfully. You can now use the Revenue Reconciliation and Shift Schedule features.</p>";
                echo "</div>";
                
                echo "<h3>📋 Next Steps:</h3>";
                echo "<ul>";
                echo "<li>Set hourly rates for your staff members in the user management</li>";
                echo "<li>Start using the Revenue Reconciliation feature for cash drawer management</li>";
                echo "<li>Create shift schedules for your staff</li>";
                echo "<li>Monitor labor costs and reconciliation reports</li>";
                echo "</ul>";
                
                echo "<h3>🔗 Access the Features:</h3>";
                echo "<a href='admin/index.php' class='btn'>Branch Admin Dashboard</a>";
                echo "<a href='super_admin/index.php' class='btn btn-secondary'>Super Admin Dashboard</a>";
                
            } catch (Exception $e) {
                $db->rollBack();
                echo "<div class='error'>";
                echo "<h3>❌ Setup Failed</h3>";
                echo "<p>Error: " . $e->getMessage() . "</p>";
                echo "<p>Please check your database connection and try again.</p>";
                echo "</div>";
            }
            
        } else {
            ?>
            <div class="warning">
                <h3>⚠️ Before You Start</h3>
                <p>This script will create the necessary database tables for the Revenue Reconciliation and Shift Schedule features.</p>
                <p><strong>Make sure to backup your database before proceeding!</strong></p>
            </div>
            
            <h3>📋 What will be created:</h3>
            <ul>
                <li><strong>cash_drawers</strong> - Cash drawer management</li>
                <li><strong>payment_methods</strong> - Payment method definitions</li>
                <li><strong>revenue_reconciliation</strong> - Revenue tracking and reconciliation</li>
                <li><strong>shift_schedules</strong> - Staff shift scheduling</li>
                <li><strong>shift_attendance</strong> - Attendance tracking</li>
                <li><strong>labor_costs</strong> - Labor cost calculations</li>
                <li>Additional columns in existing tables (users, orders)</li>
            </ul>
            
            <form method="post">
                <button type="submit" name="action" value="setup" class="btn" onclick="return confirm('Are you sure you want to proceed? Make sure you have backed up your database!')">
                    🚀 Start Setup
                </button>
            </form>
            <?php
        }
        ?>
    </div>
</body>
</html>
