-- Database Schema for Revenue Reconciliation and Shift Schedule Management
-- Fixed version that handles existing tables and indexes properly

-- 1. Create cash_drawers table for cash management
CREATE TABLE IF NOT EXISTS `cash_drawers` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Create payment_methods table
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `type` enum('cash','card','digital','other') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Add payment_method_id to orders table if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'orders' 
     AND table_schema = DATABASE()
     AND column_name = 'payment_method_id') = 0,
    'ALTER TABLE `orders` ADD COLUMN `payment_method_id` int(11) DEFAULT NULL AFTER `payment_status`',
    'SELECT "Column payment_method_id already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Add cash_drawer_id to orders table if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'orders' 
     AND table_schema = DATABASE()
     AND column_name = 'cash_drawer_id') = 0,
    'ALTER TABLE `orders` ADD COLUMN `cash_drawer_id` int(11) DEFAULT NULL AFTER `payment_method_id`',
    'SELECT "Column cash_drawer_id already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Create revenue_reconciliation table
CREATE TABLE IF NOT EXISTS `revenue_reconciliation` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Create shift_schedules table
CREATE TABLE IF NOT EXISTS `shift_schedules` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Create shift_attendance table
CREATE TABLE IF NOT EXISTS `shift_attendance` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Create shift_templates table for recurring schedules
CREATE TABLE IF NOT EXISTS `shift_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9. Create shift_template_assignments table
CREATE TABLE IF NOT EXISTS `shift_template_assignments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 10. Create labor_costs table for payroll tracking
CREATE TABLE IF NOT EXISTS `labor_costs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 11. Add hourly_rate to users table if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'users' 
     AND table_schema = DATABASE()
     AND column_name = 'hourly_rate') = 0,
    'ALTER TABLE `users` ADD COLUMN `hourly_rate` decimal(8,2) DEFAULT 0.00 AFTER `branch_id`',
    'SELECT "Column hourly_rate already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 12. Add overtime_rate to users table if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'users' 
     AND table_schema = DATABASE()
     AND column_name = 'overtime_rate') = 0,
    'ALTER TABLE `users` ADD COLUMN `overtime_rate` decimal(8,2) DEFAULT 0.00 AFTER `hourly_rate`',
    'SELECT "Column overtime_rate already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 13. Insert default payment methods
INSERT IGNORE INTO `payment_methods` (`name`, `type`, `sort_order`) VALUES
('Cash', 'cash', 1),
('Credit Card', 'card', 2),
('Debit Card', 'card', 3),
('Digital Wallet', 'digital', 4),
('Bank Transfer', 'digital', 5),
('Other', 'other', 6);

-- 14. Create indexes for better performance (only if they don't exist)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'cash_drawers' 
     AND index_name = 'idx_cash_drawers_branch_date') = 0,
    'CREATE INDEX `idx_cash_drawers_branch_date` ON `cash_drawers` (`branch_id`, `shift_date`)',
    'SELECT "Index idx_cash_drawers_branch_date already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'cash_drawers' 
     AND index_name = 'idx_cash_drawers_user_date') = 0,
    'CREATE INDEX `idx_cash_drawers_user_date` ON `cash_drawers` (`user_id`, `shift_date`)',
    'SELECT "Index idx_cash_drawers_user_date already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'revenue_reconciliation' 
     AND index_name = 'idx_revenue_reconciliation_branch_date') = 0,
    'CREATE INDEX `idx_revenue_reconciliation_branch_date` ON `revenue_reconciliation` (`branch_id`, `reconciliation_date`)',
    'SELECT "Index idx_revenue_reconciliation_branch_date already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'shift_schedules' 
     AND index_name = 'idx_shift_schedules_branch_date') = 0,
    'CREATE INDEX `idx_shift_schedules_branch_date` ON `shift_schedules` (`branch_id`, `shift_date`)',
    'SELECT "Index idx_shift_schedules_branch_date already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'shift_schedules' 
     AND index_name = 'idx_shift_schedules_user_date') = 0,
    'CREATE INDEX `idx_shift_schedules_user_date` ON `shift_schedules` (`user_id`, `shift_date`)',
    'SELECT "Index idx_shift_schedules_user_date already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'shift_attendance' 
     AND index_name = 'idx_shift_attendance_date') = 0,
    'CREATE INDEX `idx_shift_attendance_date` ON `shift_attendance` (`created_at`)',
    'SELECT "Index idx_shift_attendance_date already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'labor_costs' 
     AND index_name = 'idx_labor_costs_branch_date') = 0,
    'CREATE INDEX `idx_labor_costs_branch_date` ON `labor_costs` (`branch_id`, `shift_date`)',
    'SELECT "Index idx_labor_costs_branch_date already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'labor_costs' 
     AND index_name = 'idx_labor_costs_user_date') = 0,
    'CREATE INDEX `idx_labor_costs_user_date` ON `labor_costs` (`user_id`, `shift_date`)',
    'SELECT "Index idx_labor_costs_user_date already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 15. Create views for easier reporting
CREATE OR REPLACE VIEW `daily_revenue_summary` AS
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
LEFT JOIN users u ON rr.reconciled_by = u.id;

CREATE OR REPLACE VIEW `shift_summary` AS
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
LEFT JOIN shift_attendance sa ON ss.id = sa.shift_schedule_id;

CREATE OR REPLACE VIEW `labor_cost_summary` AS
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
JOIN users u ON lc.user_id = u.id;
