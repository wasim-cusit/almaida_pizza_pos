-- Database Migration: Branch Integration for POS System
-- This script adds branch support to the existing POS system

-- Add branch_id column to users table
ALTER TABLE `users` ADD COLUMN `branch_id` int(11) DEFAULT NULL AFTER `role`;
ALTER TABLE `users` ADD COLUMN `email` varchar(100) DEFAULT NULL AFTER `branch_id`;

-- Add foreign key constraint for branch_id
ALTER TABLE `users` ADD CONSTRAINT `fk_users_branch_id` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

-- Add index for branch_id
ALTER TABLE `users` ADD KEY `idx_users_branch_id` (`branch_id`);

-- Update existing users to have NULL branch_id (they are global users)
-- This is already the default, so no UPDATE needed

-- Add super_admin role to the enum
ALTER TABLE `users` MODIFY COLUMN `role` enum('super_admin','admin','cashier') DEFAULT 'cashier';

-- Create branches table if it doesn't exist
CREATE TABLE IF NOT EXISTS `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `manager_phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create main_warehouse_stock table if it doesn't exist
CREATE TABLE IF NOT EXISTS `main_warehouse_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `current_stock` int(11) DEFAULT 0,
  `minimum_stock` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item_warehouse` (`item_id`),
  KEY `idx_warehouse_stock_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create branch_items table if it doesn't exist
CREATE TABLE IF NOT EXISTS `branch_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `current_stock` int(11) DEFAULT 0,
  `minimum_stock` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_branch_item` (`branch_id`, `item_id`),
  KEY `idx_branch_items_branch_id` (`branch_id`),
  KEY `idx_branch_items_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create notifications table if it doesn't exist
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','error','success') DEFAULT 'info',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `is_read` tinyint(1) DEFAULT 0,
  `user_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_id` (`user_id`),
  KEY `idx_notifications_branch_id` (`branch_id`),
  KEY `idx_notifications_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign key constraints
ALTER TABLE `main_warehouse_stock` ADD CONSTRAINT `fk_warehouse_stock_item_id` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;
ALTER TABLE `branch_items` ADD CONSTRAINT `fk_branch_items_branch_id` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;
ALTER TABLE `branch_items` ADD CONSTRAINT `fk_branch_items_item_id` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;
ALTER TABLE `notifications` ADD CONSTRAINT `fk_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `notifications` ADD CONSTRAINT `fk_notifications_branch_id` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

-- Insert default super admin user if it doesn't exist
INSERT IGNORE INTO `users` (`name`, `username`, `password`, `role`, `is_active`, `created_at`) 
VALUES ('Super Admin', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 1, NOW());

-- Insert a default branch for existing users
INSERT IGNORE INTO `branches` (`name`, `address`, `phone`, `email`, `manager_name`, `manager_phone`, `is_active`) 
VALUES ('Main Branch', 'Main Location', '000-000-0000', 'main@company.com', 'Main Manager', '000-000-0000', 1);

-- Update existing users to belong to the main branch
UPDATE `users` SET `branch_id` = 1 WHERE `branch_id` IS NULL AND `role` IN ('admin', 'cashier');
