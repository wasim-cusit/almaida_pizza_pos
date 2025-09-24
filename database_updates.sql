-- Database Updates for Super Admin Panel
-- Enhanced Stock Management and Multi-Branch Support

-- 1. Create branches table
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `manager_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Add branch_id to users table
ALTER TABLE `users` ADD COLUMN `branch_id` int(11) DEFAULT NULL AFTER `role`;
ALTER TABLE `users` ADD FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

-- 3. Add branch_id to orders table
ALTER TABLE `orders` ADD COLUMN `branch_id` int(11) DEFAULT NULL AFTER `user_id`;
ALTER TABLE `orders` ADD FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

-- 4. Create branch_items table for branch-specific inventory
CREATE TABLE `branch_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `current_stock` int(11) DEFAULT 0,
  `minimum_stock` int(11) DEFAULT 0,
  `maximum_stock` int(11) DEFAULT 0,
  `reorder_point` int(11) DEFAULT 0,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_branch_item` (`branch_id`, `item_id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Create stock_movements table for stock history
CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('in','out','adjustment','transfer') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reference_type` enum('order','purchase','adjustment','transfer','waste') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Create stock_alerts table
CREATE TABLE `stock_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `alert_type` enum('low_stock','out_of_stock','overstock') NOT NULL,
  `current_stock` int(11) NOT NULL,
  `threshold_stock` int(11) NOT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Create stock_transfers table for inter-branch transfers
CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_branch_id` int(11) NOT NULL,
  `to_branch_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('pending','approved','completed','cancelled') DEFAULT 'pending',
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`from_branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`to_branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Add super_admin role to users
ALTER TABLE `users` MODIFY COLUMN `role` enum('super_admin','admin','cashier') DEFAULT 'cashier';

-- 9. Insert default super admin user
INSERT INTO `users` (`name`, `username`, `password`, `role`, `is_active`) VALUES
('Super Admin', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 1);

-- 10. Insert default branch
INSERT INTO `branches` (`name`, `address`, `phone`, `email`, `manager_name`) VALUES
('Main Branch', 'Commercial Market #1 OPF Colony Peshawar', '091-2614488', 'main@almaida.com', 'Branch Manager');

-- 11. Update existing users to have branch_id
UPDATE `users` SET `branch_id` = 1 WHERE `branch_id` IS NULL;

-- 12. Update existing orders to have branch_id
UPDATE `orders` SET `branch_id` = 1 WHERE `branch_id` IS NULL;

-- 13. Create indexes for better performance
CREATE INDEX `idx_branch_items_branch` ON `branch_items` (`branch_id`);
CREATE INDEX `idx_branch_items_item` ON `branch_items` (`item_id`);
CREATE INDEX `idx_stock_movements_branch` ON `stock_movements` (`branch_id`);
CREATE INDEX `idx_stock_movements_item` ON `stock_movements` (`item_id`);
CREATE INDEX `idx_stock_movements_date` ON `stock_movements` (`created_at`);
CREATE INDEX `idx_stock_alerts_branch` ON `stock_alerts` (`branch_id`);
CREATE INDEX `idx_stock_alerts_resolved` ON `stock_alerts` (`is_resolved`);
