-- Database Update: Add received functionality to stock distributions
-- This script adds the missing columns and functionality for proper stock receiving workflow

-- Add received_at column to stock_distributions table if it doesn't exist
ALTER TABLE `stock_distributions` 
ADD COLUMN IF NOT EXISTS `received_at` timestamp NULL DEFAULT NULL AFTER `received_by`;

-- Add missing columns to stock_distribution_items table if they don't exist
ALTER TABLE `stock_distribution_items` 
ADD COLUMN IF NOT EXISTS `approved_quantity` int(11) DEFAULT 0 AFTER `requested_quantity`,
ADD COLUMN IF NOT EXISTS `dispatched_quantity` int(11) DEFAULT 0 AFTER `approved_quantity`,
ADD COLUMN IF NOT EXISTS `received_quantity` int(11) DEFAULT 0 AFTER `dispatched_quantity`;

-- Create stock_movements table if it doesn't exist (for tracking stock movements)
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) DEFAULT NULL,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('in','out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) DEFAULT 0,
  `new_stock` int(11) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stock_movements_branch` (`branch_id`),
  KEY `idx_stock_movements_item` (`item_id`),
  KEY `idx_stock_movements_user` (`user_id`),
  KEY `idx_stock_movements_date` (`created_at`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create warehouse_movements table if it doesn't exist (for warehouse stock tracking)
CREATE TABLE IF NOT EXISTS `warehouse_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('in','out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) DEFAULT 0,
  `new_stock` int(11) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_warehouse_movements_item` (`item_id`),
  KEY `idx_warehouse_movements_user` (`user_id`),
  KEY `idx_warehouse_movements_date` (`created_at`),
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Update existing stock_distribution_items to set approved_quantity = requested_quantity for existing records
UPDATE `stock_distribution_items` 
SET `approved_quantity` = `requested_quantity` 
WHERE `approved_quantity` = 0 AND `requested_quantity` > 0;

-- Update existing stock_distribution_items to set dispatched_quantity = approved_quantity for existing records
UPDATE `stock_distribution_items` 
SET `dispatched_quantity` = `approved_quantity` 
WHERE `dispatched_quantity` = 0 AND `approved_quantity` > 0;

-- Update existing stock_distribution_items to set received_quantity = dispatched_quantity for existing records
UPDATE `stock_distribution_items` 
SET `received_quantity` = `dispatched_quantity` 
WHERE `received_quantity` = 0 AND `dispatched_quantity` > 0;
