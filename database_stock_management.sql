-- Enhanced Stock Management Database Schema
-- Complete workflow: Purchase -> Stock Entry -> Branch Distribution -> Sales

-- 1. Create suppliers table
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Create stock_purchases table
CREATE TABLE `stock_purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `purchase_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','received','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_number` (`purchase_number`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Create stock_purchase_items table
CREATE TABLE `stock_purchase_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`purchase_id`) REFERENCES `stock_purchases` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Create stock_entries table (main warehouse stock)
CREATE TABLE `stock_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_number` varchar(50) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `entry_date` date NOT NULL,
  `entry_type` enum('purchase','transfer_in','adjustment','return') DEFAULT 'purchase',
  `total_items` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry_number` (`entry_number`),
  FOREIGN KEY (`purchase_id`) REFERENCES `stock_purchases` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Create stock_entry_items table
CREATE TABLE `stock_entry_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`entry_id`) REFERENCES `stock_entries` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Create main_warehouse_stock table (central stock)
CREATE TABLE `main_warehouse_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `current_stock` int(11) DEFAULT 0,
  `reserved_stock` int(11) DEFAULT 0,
  `available_stock` int(11) GENERATED ALWAYS AS (`current_stock` - `reserved_stock`) STORED,
  `minimum_stock` int(11) DEFAULT 0,
  `maximum_stock` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_id` (`item_id`),
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Create stock_distributions table (warehouse to branch)
CREATE TABLE `stock_distributions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `distribution_number` varchar(50) NOT NULL,
  `from_warehouse` tinyint(1) DEFAULT 1,
  `to_branch_id` int(11) NOT NULL,
  `distribution_date` date NOT NULL,
  `status` enum('pending','approved','dispatched','received','cancelled') DEFAULT 'pending',
  `total_items` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `dispatched_by` int(11) DEFAULT NULL,
  `received_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `distribution_number` (`distribution_number`),
  FOREIGN KEY (`to_branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`dispatched_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Create stock_distribution_items table
CREATE TABLE `stock_distribution_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `distribution_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `requested_quantity` int(11) NOT NULL,
  `approved_quantity` int(11) DEFAULT 0,
  `dispatched_quantity` int(11) DEFAULT 0,
  `received_quantity` int(11) DEFAULT 0,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`distribution_id`) REFERENCES `stock_distributions` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9. Create notifications table
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `notification_type` enum('stock_alert','purchase_approval','distribution_approval','low_stock','out_of_stock') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 10. Insert default supplier
INSERT INTO `suppliers` (`name`, `contact_person`, `phone`, `email`, `address`) VALUES
('Main Supplier', 'Supplier Manager', '091-1234567', 'supplier@almaida.com', 'Supplier Address');

-- 11. Create indexes for better performance
CREATE INDEX `idx_stock_purchases_supplier` ON `stock_purchases` (`supplier_id`);
CREATE INDEX `idx_stock_purchases_date` ON `stock_purchases` (`purchase_date`);
CREATE INDEX `idx_stock_purchases_status` ON `stock_purchases` (`status`);
CREATE INDEX `idx_stock_entries_date` ON `stock_entries` (`entry_date`);
CREATE INDEX `idx_stock_distributions_branch` ON `stock_distributions` (`to_branch_id`);
CREATE INDEX `idx_stock_distributions_status` ON `stock_distributions` (`status`);
CREATE INDEX `idx_notifications_user` ON `notifications` (`user_id`);
CREATE INDEX `idx_notifications_branch` ON `notifications` (`branch_id`);
CREATE INDEX `idx_notifications_read` ON `notifications` (`is_read`);
