-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 24, 2025 at 09:34 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u515862593_usfood`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(100) DEFAULT 'default-icon.png',
  `image` varchar(255) DEFAULT 'default-category.jpg',
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `icon`, `image`, `display_order`, `is_active`, `created_at`) VALUES
(1, 'PIZZA', 'fas fa-pizza-slice', 'default-category.jpg', 1, 1, '2025-08-04 18:56:06'),
(2, 'BURGERS', 'fas fa-hamburger', 'default-category.jpg', 2, 1, '2025-08-04 18:56:06'),
(3, 'FRIED CHICKEN', 'fas fa-drumstick-bite', 'default-category.jpg', 3, 1, '2025-08-04 18:56:06'),
(4, 'WINGS', 'fas fa-feather-alt', 'default-category.jpg', 4, 1, '2025-08-04 18:56:06'),
(5, 'SOUP', 'fas fa-utensils', 'default-category.jpg', 5, 1, '2025-08-04 18:56:06'),
(6, 'CHINESE', 'fas fa-bowl-food', 'default-category.jpg', 6, 1, '2025-08-04 18:56:06'),
(7, 'COLD DRINKS', 'fas fa-glass-whiskey', 'default-category.jpg', 7, 1, '2025-08-04 18:56:06'),
(8, 'HOT DRINKS', 'fas fa-coffee', 'default-category.jpg', 8, 1, '2025-08-04 18:56:06'),
(9, 'SHAWARMA', 'fas fa-bread-slice', 'default-category.jpg', 9, 1, '2025-08-04 18:56:06'),
(10, 'FRIES', 'fas fa-french-fries', 'default-category.jpg', 10, 1, '2025-08-04 18:56:06'),
(11, 'SHAKES', 'fas fa-ice-cream', 'default-category.jpg', 11, 1, '2025-08-04 18:56:06'),
(12, 'SANDWICH', 'fas fa-sandwich', 'default-category.jpg', 12, 1, '2025-08-04 18:56:06'),
(13, 'NUGGETS', 'fas fa-cube', 'default-category.jpg', 13, 1, '2025-08-04 19:35:12'),
(14, 'CHOWMEIN', 'fas fa-utensils', 'default-category.jpg', 14, 1, '2025-08-04 19:46:43'),
(15, 'PASTA', 'fas fa-spaghetti-monster-flying', 'default-category.jpg', 15, 1, '2025-08-04 19:48:46'),
(16, 'SHAKES', 'fas fa-glass-whiskey', 'default-category.jpg', 16, 1, '2025-08-04 20:07:13'),
(17, 'DELIVERY', 'fas fa-truck', 'default-category.jpg', 17, 1, '2025-08-04 20:13:51');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `contact`, `email`, `address`, `postcode`, `created_at`) VALUES
(4, '', '0311-8042307', '', 'opf', '', '2025-08-26 10:33:42'),
(5, 'jibran', '0311-8042307', '', 'chamkani', 'f15', '2025-08-26 10:38:25'),
(6, 'test', '', '', '', '25000', '2025-08-31 19:37:58'),
(7, 'jkjk', '4420', '', 'jkj nk jk', '', '2025-09-11 12:17:39'),
(8, 'Rafay khan', '', '', '', '', '2025-09-12 12:46:34'),
(9, 'test', '3456789', '', '567890', 'test', '2025-09-17 09:49:31');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `category_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT 'default-item.jpg',
  `description` text DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `is_deleted` tinyint(1) DEFAULT 0,
  `stock_quantity` int(11) DEFAULT 0,
  `has_size_variants` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `variety_type` varchar(100) DEFAULT NULL,
  `size` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `name`, `category_id`, `price`, `image`, `description`, `is_available`, `is_deleted`, `stock_quantity`, `has_size_variants`, `created_at`, `updated_at`, `variety_type`, `size`) VALUES
(11, 'US Special Pizza', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:00:15', '2025-08-04 19:00:15', NULL, NULL),
(12, 'Chicken Tikka', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:03:26', '2025-08-04 19:03:26', NULL, NULL),
(13, 'Crown Crust', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:05:13', '2025-08-04 19:05:13', NULL, NULL),
(14, 'Hot and Spicy', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:06:43', '2025-08-05 15:06:00', NULL, NULL),
(15, 'Malai Boti', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:07:52', '2025-08-04 19:07:52', NULL, NULL),
(16, 'Calzone Pizza', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:09:14', '2025-08-04 19:09:14', NULL, NULL),
(17, 'US Royal Stuff', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:10:02', '2025-08-04 19:10:02', NULL, NULL),
(18, 'US Deep Dish', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:10:51', '2025-08-04 19:10:51', NULL, NULL),
(19, 'Cheese Lover', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:11:45', '2025-08-04 19:11:45', NULL, NULL),
(20, 'Labnani Pizza', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:12:49', '2025-08-04 19:12:49', NULL, NULL),
(21, 'Chicken Fajita', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:13:53', '2025-08-04 19:13:53', NULL, NULL),
(22, 'Four Session Pizza', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:14:51', '2025-08-04 19:14:51', NULL, NULL),
(24, 'Matka Pizza', 1, 1000.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:20:56', '2025-08-04 19:20:56', NULL, NULL),
(25, 'US Special Tower', 2, 600.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:23:05', '2025-08-04 19:23:05', NULL, NULL),
(26, 'Zinger Burger', 2, 500.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:23:36', '2025-08-04 19:23:36', NULL, NULL),
(27, 'Small Zinger Burger', 2, 350.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:24:04', '2025-08-04 19:24:04', NULL, NULL),
(28, 'Grill Chicken Burger', 2, 500.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:25:03', '2025-08-04 19:25:03', NULL, NULL),
(29, 'Small Grill Burger', 2, 350.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:25:40', '2025-08-04 19:25:40', NULL, NULL),
(30, 'Zinger Superme', 2, 550.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:26:03', '2025-08-04 19:26:03', NULL, NULL),
(31, 'Chicken Steak', 2, 500.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:26:30', '2025-08-04 19:26:30', NULL, NULL),
(33, 'Bufflo Wings', 4, 450.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:28:54', '2025-08-04 19:28:54', NULL, NULL),
(34, 'BBQ Wings', 4, 450.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:29:19', '2025-08-04 19:29:19', NULL, NULL),
(35, 'Hot &amp; Spicy Wings', 4, 450.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:29:47', '2025-08-04 19:29:47', NULL, NULL),
(36, 'Zinger Wings', 4, 400.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:30:09', '2025-08-04 19:30:09', NULL, NULL),
(37, 'Pieces 1', 3, 220.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:32:02', '2025-08-04 19:32:02', NULL, NULL),
(38, 'Pieces 2', 3, 420.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:32:34', '2025-08-04 19:32:34', NULL, NULL),
(39, 'Pieces 3', 3, 600.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:33:01', '2025-08-04 19:33:01', NULL, NULL),
(40, 'Pieces 5', 3, 1000.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:33:48', '2025-08-04 19:33:48', NULL, NULL),
(41, 'Pieces 5', 13, 300.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:36:15', '2025-08-04 19:36:15', NULL, NULL),
(42, 'Pieces 10', 13, 550.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:36:30', '2025-08-04 19:36:30', NULL, NULL),
(43, 'Pieces 15', 13, 850.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:36:51', '2025-08-04 19:36:51', NULL, NULL),
(44, 'Pieces 20', 13, 1100.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:37:14', '2025-08-04 19:37:14', NULL, NULL),
(45, 'Shawarma', 9, 180.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:38:03', '2025-08-04 19:38:03', NULL, NULL),
(46, 'Special Shawarma', 9, 260.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:38:27', '2025-08-04 19:38:27', NULL, NULL),
(47, 'Zinger Shawarma', 9, 300.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:39:27', '2025-08-04 19:39:27', NULL, NULL),
(48, 'Paratha Roll', 9, 300.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:40:12', '2025-08-04 19:40:12', NULL, NULL),
(49, 'Special Paratha Roll', 9, 350.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:40:48', '2025-08-04 19:40:48', NULL, NULL),
(50, 'Zinger Paratha Roll', 9, 320.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:41:58', '2025-08-04 19:41:58', NULL, NULL),
(51, 'Chicken Manchurian', 6, 650.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:42:52', '2025-08-04 19:42:52', NULL, NULL),
(52, 'Chicken Chilli Dry', 6, 650.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:43:49', '2025-08-04 19:43:49', NULL, NULL),
(53, 'Chicken Shashlik', 6, 650.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:44:41', '2025-08-04 19:44:41', NULL, NULL),
(54, 'Chicken Fried Rice', 6, 500.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:45:13', '2025-08-04 19:45:13', NULL, NULL),
(55, 'Vegetable Fried Rice', 6, 400.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:45:48', '2025-08-04 19:45:48', NULL, NULL),
(56, 'US Special Chowmein', 14, 650.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:47:55', '2025-08-04 19:47:55', NULL, NULL),
(57, 'Chicken Chowmein', 14, 600.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:48:18', '2025-08-04 19:48:18', NULL, NULL),
(58, 'Alfredo Pasta', 15, 650.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:49:31', '2025-08-04 19:49:31', NULL, NULL),
(59, 'Chicken Lesagne', 15, 680.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:50:02', '2025-08-04 19:50:02', NULL, NULL),
(60, 'Grill Chicken Sandwich', 12, 350.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:50:37', '2025-08-04 19:50:37', NULL, NULL),
(61, 'Grill Club Sandwich', 12, 500.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:51:02', '2025-08-21 13:15:34', NULL, NULL),
(62, 'Chicken Panini', 12, 500.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:51:25', '2025-08-04 19:51:25', NULL, NULL),
(63, 'Regular Fries', 10, 230.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:52:12', '2025-08-04 19:52:12', NULL, NULL),
(64, 'Large Fries', 10, 350.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:52:33', '2025-08-04 19:52:33', NULL, NULL),
(65, 'Loaded Fries', 10, 450.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:52:51', '2025-08-04 19:52:51', NULL, NULL),
(66, 'Cheese Fries', 10, 400.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:53:37', '2025-08-04 19:53:37', NULL, NULL),
(67, 'Matka Fries', 10, 700.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:54:23', '2025-08-04 19:54:23', NULL, NULL),
(69, 'Cappuccino', 8, 200.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:57:15', '2025-08-04 19:57:15', NULL, NULL),
(70, 'Coffee', 8, 200.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:57:30', '2025-08-04 19:57:30', NULL, NULL),
(71, 'Special Chai', 8, 160.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:57:52', '2025-08-04 19:57:52', NULL, NULL),
(73, 'Hot &amp; Sour', 5, 0.00, 'default-item.jpg', '', 0, 1, 0, 1, '2025-08-04 20:03:40', '2025-08-05 15:09:25', NULL, NULL),
(75, 'Mint Margarita', 16, 200.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 20:08:37', '2025-08-04 20:08:37', NULL, NULL),
(76, 'Fresh Lime', 16, 150.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 20:08:58', '2025-08-04 20:08:58', NULL, NULL),
(77, 'Oreo Shake', 16, 200.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 20:09:24', '2025-08-04 20:09:24', NULL, NULL),
(81, 'Delivery', 17, 0.00, 'default-item.jpg', '', 0, 1, 0, 1, '2025-08-04 20:19:01', '2025-08-05 14:57:17', NULL, NULL),
(82, '30', 17, 30.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-05 14:57:42', '2025-08-05 15:00:32', NULL, NULL),
(83, '50', 17, 50.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-05 15:00:01', '2025-08-05 15:00:01', NULL, NULL),
(84, '100', 17, 100.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-05 15:01:23', '2025-08-05 15:01:23', NULL, NULL),
(85, '150', 17, 150.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-05 15:01:38', '2025-08-05 15:01:38', NULL, NULL),
(86, '200', 17, 200.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-05 15:01:50', '2025-08-05 15:01:50', NULL, NULL),
(88, 'Chicken Corn - Full', 5, 950.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-05 15:05:15', '2025-08-05 15:05:15', NULL, NULL),
(89, 'REGULER NR', 7, 80.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-05 15:06:16', '2025-08-05 15:06:16', NULL, NULL),
(90, 'Hot &amp; Sour - Half', 5, 550.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-05 15:07:13', '2025-08-05 15:07:13', NULL, NULL),
(91, 'Hot &amp; Sour - Full', 5, 950.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-05 15:07:35', '2025-08-05 15:07:35', NULL, NULL),
(92, 'Chicken Corn - Half', 5, 550.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-05 15:08:06', '2025-08-05 15:08:06', NULL, NULL),
(94, 'US Special Soup - Half', 5, 600.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-05 15:10:44', '2025-08-05 15:10:44', NULL, NULL),
(95, 'US Special Soup - Full', 5, 950.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-05 15:11:18', '2025-08-05 15:11:18', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `item_size_variants`
--

CREATE TABLE `item_size_variants` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `size_name` varchar(50) NOT NULL,
  `size_price` decimal(10,2) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_size_variants`
--

INSERT INTO `item_size_variants` (`id`, `item_id`, `size_name`, `size_price`, `display_order`, `is_active`, `created_at`) VALUES
(17, 11, 'Small', 550.00, 0, 1, '2025-08-04 19:00:15'),
(18, 11, 'Medium', 1150.00, 0, 1, '2025-08-04 19:00:15'),
(19, 11, 'Large', 1600.00, 0, 1, '2025-08-04 19:00:15'),
(20, 11, 'F 15', 2400.00, 0, 1, '2025-08-04 19:00:15'),
(21, 12, 'Small', 520.00, 0, 1, '2025-08-04 19:03:26'),
(22, 12, 'Medium', 1050.00, 0, 1, '2025-08-04 19:03:26'),
(23, 12, 'Large', 1550.00, 0, 1, '2025-08-04 19:03:26'),
(24, 12, 'F 15', 2350.00, 0, 1, '2025-08-04 19:03:26'),
(25, 13, 'Medium', 1300.00, 0, 1, '2025-08-04 19:05:13'),
(26, 13, 'Large', 1700.00, 0, 1, '2025-08-04 19:05:13'),
(27, 13, 'F 15', 2450.00, 0, 1, '2025-08-04 19:05:13'),
(32, 15, 'Small', 520.00, 0, 1, '2025-08-04 19:07:52'),
(33, 15, 'Medium', 1050.00, 0, 1, '2025-08-04 19:07:52'),
(34, 15, 'Large', 1550.00, 0, 1, '2025-08-04 19:07:52'),
(35, 15, 'F 15', 2350.00, 0, 1, '2025-08-04 19:07:52'),
(36, 16, 'Small', 550.00, 0, 1, '2025-08-04 19:09:14'),
(37, 16, 'Medium', 1150.00, 0, 1, '2025-08-04 19:09:14'),
(38, 16, 'Large', 1600.00, 0, 1, '2025-08-04 19:09:14'),
(39, 16, 'F 15', 2400.00, 0, 1, '2025-08-04 19:09:14'),
(40, 17, 'Medium', 1250.00, 0, 1, '2025-08-04 19:10:02'),
(41, 17, 'Large', 1800.00, 0, 1, '2025-08-04 19:10:02'),
(42, 17, 'F 15', 2600.00, 0, 1, '2025-08-04 19:10:02'),
(43, 18, 'Medium', 1250.00, 0, 1, '2025-08-04 19:10:51'),
(44, 18, 'Large', 1800.00, 0, 1, '2025-08-04 19:10:51'),
(45, 18, 'F 15', 2600.00, 0, 1, '2025-08-04 19:10:51'),
(46, 19, 'Small', 500.00, 0, 1, '2025-08-04 19:11:45'),
(47, 19, 'Medium', 1050.00, 0, 1, '2025-08-04 19:11:45'),
(48, 19, 'Large', 1500.00, 0, 1, '2025-08-04 19:11:45'),
(49, 19, 'F 15', 2250.00, 0, 1, '2025-08-04 19:11:45'),
(50, 20, 'Small', 750.00, 0, 1, '2025-08-04 19:12:49'),
(51, 20, 'Medium', 1400.00, 0, 1, '2025-08-04 19:12:49'),
(52, 20, 'Large', 1800.00, 0, 1, '2025-08-04 19:12:49'),
(53, 21, 'Small', 520.00, 0, 1, '2025-08-04 19:13:53'),
(54, 21, 'Medium', 1050.00, 0, 1, '2025-08-04 19:13:53'),
(55, 21, 'Large', 1550.00, 0, 1, '2025-08-04 19:13:53'),
(56, 21, 'F 15', 2350.00, 0, 1, '2025-08-04 19:13:53'),
(57, 22, 'Small', 550.00, 0, 1, '2025-08-04 19:14:51'),
(58, 22, 'Medium', 1150.00, 0, 1, '2025-08-04 19:14:51'),
(59, 22, 'Large', 1600.00, 0, 1, '2025-08-04 19:14:51'),
(60, 22, 'F 15', 2400.00, 0, 1, '2025-08-04 19:14:51'),
(65, 73, 'Half', 550.00, 0, 1, '2025-08-04 20:03:40'),
(66, 73, 'Full', 950.00, 0, 1, '2025-08-04 20:03:40'),
(74, 81, '1 km', 30.00, 0, 1, '2025-08-05 14:50:24'),
(75, 81, '3 km', 50.00, 0, 1, '2025-08-05 14:50:24'),
(76, 81, '5 km', 100.00, 0, 1, '2025-08-05 14:50:24'),
(77, 81, '10 km', 150.00, 0, 1, '2025-08-05 14:50:24'),
(78, 81, '15 km', 200.00, 0, 1, '2025-08-05 14:50:24'),
(81, 14, 'Small', 520.00, 0, 1, '2025-08-05 15:06:00'),
(82, 14, 'Medium', 1050.00, 0, 1, '2025-08-05 15:06:00'),
(83, 14, 'Large', 1550.00, 0, 1, '2025-08-05 15:06:00'),
(84, 14, 'F 15', 2350.00, 0, 1, '2025-08-05 15:06:00');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `order_type` enum('dine_in','takeaway','delivery') DEFAULT 'dine_in',
  `table_number` int(11) DEFAULT 0,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','card','online') DEFAULT 'cash',
  `payment_status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `order_status` enum('pending','preparing','ready','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `customer_id`, `order_type`, `table_number`, `subtotal`, `discount_amount`, `tax_amount`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'ORD202508216656', 1, NULL, 'takeaway', NULL, 2250.00, 0.00, 337.50, 2587.50, 'cash', 'pending', 'preparing', '', '2025-08-21 14:54:15', '2025-08-25 14:21:39'),
(2, 'ORD202508210334', 1, NULL, 'takeaway', NULL, 550.00, 0.00, 82.50, 632.50, 'cash', 'pending', 'pending', '', '2025-08-21 14:56:32', '2025-08-21 14:56:32'),
(3, 'ORD202508210134', 1, NULL, 'dine_in', NULL, 850.00, 0.00, 127.50, 977.50, 'cash', 'pending', 'pending', '', '2025-08-21 15:03:03', '2025-08-21 15:03:03'),
(4, 'ORD202508210274', 1, NULL, 'dine_in', NULL, 150.00, 0.00, 22.50, 172.50, 'cash', 'pending', 'pending', '', '2025-08-21 15:27:02', '2025-08-21 15:27:02'),
(5, 'ORD202508246263', 1, NULL, 'takeaway', NULL, 500.00, 0.00, 75.00, 575.00, 'cash', 'pending', 'pending', '', '2025-08-24 18:50:20', '2025-08-24 18:50:20'),
(6, 'ORD202508248955', 1, NULL, 'takeaway', NULL, 500.00, 0.00, 75.00, 575.00, 'cash', 'pending', 'pending', '', '2025-08-24 18:50:20', '2025-08-24 18:50:20'),
(7, 'ORD202508258243', 1, NULL, 'dine_in', NULL, 3550.00, 0.00, 532.50, 4082.50, 'cash', 'pending', 'pending', '', '2025-08-25 14:20:58', '2025-08-25 14:20:58'),
(8, 'ORD202508262974', 1, 4, 'delivery', NULL, 610.00, 0.00, 91.50, 701.50, 'online', 'pending', 'completed', '', '2025-08-26 10:33:42', '2025-08-26 10:54:00'),
(9, 'ORD202508267375', 1, 5, 'delivery', NULL, 2750.00, 0.00, 412.50, 3162.50, 'cash', 'pending', 'completed', '', '2025-08-26 10:38:25', '2025-08-26 10:53:43'),
(10, 'ORD202508263590', 1, NULL, 'takeaway', NULL, 2350.00, 0.00, 352.50, 2702.50, 'cash', 'pending', 'completed', '', '2025-08-26 10:49:08', '2025-08-26 10:53:38'),
(11, 'ORD202508280162', 1, NULL, 'dine_in', NULL, 500.00, 0.00, 75.00, 575.00, 'cash', 'pending', 'pending', '', '2025-08-28 16:29:39', '2025-08-28 16:29:39'),
(12, 'ORD202508308502', 1, NULL, 'dine_in', NULL, 2600.00, 0.00, 390.00, 2990.00, 'cash', 'pending', 'pending', '', '2025-08-30 19:38:23', '2025-08-30 19:38:23'),
(13, 'ORD202508314545', 1, NULL, 'dine_in', NULL, 3350.00, 0.00, 502.50, 3852.50, 'cash', 'pending', 'pending', '', '2025-08-31 19:36:46', '2025-08-31 19:36:46'),
(14, 'ORD202508313993', 1, NULL, 'dine_in', NULL, 3350.00, 0.00, 502.50, 3852.50, 'cash', 'pending', 'completed', '', '2025-08-31 19:37:10', '2025-08-31 19:38:54'),
(15, 'ORD202508314401', 1, 6, 'dine_in', NULL, 2400.00, 0.00, 360.00, 2760.00, 'cash', 'pending', 'pending', '', '2025-08-31 19:37:58', '2025-08-31 19:37:58'),
(16, 'ORD202508310602', 1, NULL, 'dine_in', NULL, 500.00, 0.00, 75.00, 575.00, 'cash', 'pending', 'pending', '', '2025-08-31 20:03:46', '2025-08-31 20:03:46'),
(17, 'ORD202508316970', 1, NULL, 'dine_in', NULL, 500.00, 0.00, 75.00, 575.00, 'cash', 'pending', 'pending', '', '2025-08-31 20:29:38', '2025-08-31 20:29:38'),
(18, 'ORD202509087159', 1, NULL, 'dine_in', NULL, 2200.00, 0.00, 330.00, 2530.00, 'cash', 'pending', 'pending', '', '2025-09-08 07:28:54', '2025-09-08 07:28:54'),
(19, 'ORD202509117092', 1, 7, 'takeaway', NULL, 1450.00, 0.00, 217.50, 1667.50, 'cash', 'pending', 'pending', '', '2025-09-11 12:17:39', '2025-09-11 12:17:39'),
(20, 'ORD202509117084', 1, NULL, 'takeaway', NULL, 2350.00, 0.00, 352.50, 2702.50, 'cash', 'pending', 'pending', '', '2025-09-11 13:05:34', '2025-09-11 13:05:34'),
(21, 'ORD202509129666', 1, 8, 'dine_in', NULL, 1600.00, 0.00, 240.00, 1840.00, 'cash', 'pending', 'pending', '', '2025-09-12 12:46:34', '2025-09-12 12:46:34'),
(22, 'ORD202509156032', 1, NULL, 'dine_in', NULL, 3650.00, 0.00, 547.50, 4197.50, 'cash', 'pending', 'pending', '', '2025-09-15 00:28:01', '2025-09-15 00:28:01'),
(23, 'ORD202509150761', 1, NULL, 'dine_in', NULL, 3650.00, 0.00, 547.50, 4197.50, 'cash', 'pending', 'pending', '', '2025-09-15 00:28:01', '2025-09-15 00:28:01'),
(24, 'ORD202509176220', 1, NULL, 'dine_in', 9, 2600.00, 0.00, 390.00, 2990.00, 'cash', 'pending', 'pending', '', '2025-09-17 09:47:41', '2025-09-17 09:47:41'),
(25, 'ORD202509172605', 1, 9, 'takeaway', NULL, 5200.00, 0.00, 780.00, 5980.00, 'cash', 'pending', 'pending', '', '2025-09-17 09:49:31', '2025-09-17 09:49:31'),
(26, 'ORD202509198371', 1, NULL, 'takeaway', NULL, 1550.00, 0.00, 232.50, 1782.50, 'cash', 'pending', 'pending', '', '2025-09-19 14:21:49', '2025-09-19 14:21:49');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `size_name` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `item_id`, `item_name`, `size_name`, `quantity`, `unit_price`, `total_price`, `notes`, `created_at`) VALUES
(39, 1, 16, 'Calzone Pizza (Large)', 'Large', 1, 1600.00, 1600.00, '', '2025-08-21 14:54:15'),
(40, 1, 75, 'Mint Margarita', '', 1, 200.00, 200.00, '', '2025-08-21 14:54:15'),
(41, 1, 33, 'Bufflo Wings', '', 1, 450.00, 450.00, '', '2025-08-21 14:54:15'),
(42, 2, 16, 'Calzone Pizza (Small)', 'Small', 1, 550.00, 550.00, '', '2025-08-21 14:56:32'),
(43, 3, 28, 'Grill Chicken Burger', '', 1, 500.00, 500.00, '', '2025-08-21 15:03:03'),
(44, 3, 27, 'Small Zinger Burger', '', 1, 350.00, 350.00, '', '2025-08-21 15:03:03'),
(45, 4, 76, 'Fresh Lime', '', 1, 150.00, 150.00, '', '2025-08-21 15:27:02'),
(46, 5, 19, 'Cheese Lover (Small)', 'Small', 1, 500.00, 500.00, '', '2025-08-24 18:50:20'),
(47, 6, 19, 'Cheese Lover (Small)', 'Small', 1, 500.00, 500.00, '', '2025-08-24 18:50:20'),
(48, 7, 11, 'US Special Pizza', '', 1, 2400.00, 2400.00, '', '2025-08-25 14:20:58'),
(49, 7, 22, 'Four Session Pizza', '', 1, 1150.00, 1150.00, '', '2025-08-25 14:20:58'),
(50, 8, 64, 'Large Fries', '', 1, 350.00, 350.00, '', '2025-08-26 10:33:42'),
(51, 8, 46, 'Special Shawarma', '', 1, 260.00, 260.00, '', '2025-08-26 10:33:42'),
(52, 9, 62, 'Chicken Panini', '', 1, 500.00, 500.00, '', '2025-08-26 10:38:25'),
(53, 9, 17, 'US Royal Stuff (Medium)', 'Medium', 1, 1250.00, 1250.00, '', '2025-08-26 10:38:25'),
(54, 9, 24, 'Matka Pizza', '', 1, 1000.00, 1000.00, '', '2025-08-26 10:38:25'),
(55, 10, 12, 'Chicken Tikka (F 15)', 'F 15', 1, 2350.00, 2350.00, '', '2025-08-26 10:49:08'),
(56, 11, 28, 'Grill Chicken Burger', '', 1, 500.00, 500.00, '', '2025-08-28 16:29:39'),
(57, 12, 18, 'US Deep Dish', '', 1, 2600.00, 2600.00, '', '2025-08-30 19:38:23'),
(58, 13, 31, 'Chicken Steak', '', 1, 500.00, 500.00, '', '2025-08-31 19:36:46'),
(59, 13, 28, 'Grill Chicken Burger', '', 1, 500.00, 500.00, '', '2025-08-31 19:36:46'),
(60, 13, 29, 'Small Grill Burger', '', 1, 350.00, 350.00, '', '2025-08-31 19:36:46'),
(61, 13, 27, 'Small Zinger Burger', '', 1, 350.00, 350.00, '', '2025-08-31 19:36:46'),
(62, 13, 25, 'US Special Tower', '', 1, 600.00, 600.00, '', '2025-08-31 19:36:46'),
(63, 13, 26, 'Zinger Burger', '', 1, 500.00, 500.00, '', '2025-08-31 19:36:46'),
(64, 13, 30, 'Zinger Superme', '', 1, 550.00, 550.00, '', '2025-08-31 19:36:46'),
(65, 14, 31, 'Chicken Steak', '', 1, 500.00, 500.00, '', '2025-08-31 19:37:10'),
(66, 14, 28, 'Grill Chicken Burger', '', 1, 500.00, 500.00, '', '2025-08-31 19:37:10'),
(67, 14, 29, 'Small Grill Burger', '', 1, 350.00, 350.00, '', '2025-08-31 19:37:10'),
(68, 14, 27, 'Small Zinger Burger', '', 1, 350.00, 350.00, '', '2025-08-31 19:37:10'),
(69, 14, 25, 'US Special Tower', '', 1, 600.00, 600.00, '', '2025-08-31 19:37:10'),
(70, 14, 26, 'Zinger Burger', '', 1, 500.00, 500.00, '', '2025-08-31 19:37:10'),
(71, 14, 30, 'Zinger Superme', '', 1, 550.00, 550.00, '', '2025-08-31 19:37:10'),
(72, 15, 16, 'Calzone Pizza', '', 1, 2400.00, 2400.00, '', '2025-08-31 19:37:58'),
(73, 16, 31, 'Chicken Steak', '', 1, 500.00, 500.00, '', '2025-08-31 20:03:46'),
(74, 17, 28, 'Grill Chicken Burger', '', 1, 500.00, 500.00, '', '2025-08-31 20:29:38'),
(75, 18, 16, 'Calzone Pizza', '', 1, 1150.00, 1150.00, '', '2025-09-08 07:28:54'),
(76, 18, 19, 'Cheese Lover', '', 1, 1050.00, 1050.00, '', '2025-09-08 07:28:54'),
(77, 19, 28, 'Grill Chicken Burger', '', 1, 500.00, 500.00, '', '2025-09-11 12:17:39'),
(78, 19, 27, 'Small Zinger Burger', '', 1, 350.00, 350.00, '', '2025-09-11 12:17:39'),
(79, 19, 25, 'US Special Tower', '', 1, 600.00, 600.00, '', '2025-09-11 12:17:39'),
(80, 20, 21, 'Chicken Fajita', '', 1, 2350.00, 2350.00, '', '2025-09-11 13:05:34'),
(81, 21, 16, 'Calzone Pizza', '', 1, 1600.00, 1600.00, '', '2025-09-12 12:46:34'),
(82, 22, 16, 'Calzone Pizza', '', 1, 2400.00, 2400.00, '', '2025-09-15 00:28:01'),
(83, 22, 19, 'Cheese Lover', '', 1, 500.00, 500.00, '', '2025-09-15 00:28:01'),
(84, 22, 20, 'Labnani Pizza', '', 1, 750.00, 750.00, '', '2025-09-15 00:28:01'),
(85, 23, 16, 'Calzone Pizza', '', 1, 2400.00, 2400.00, '', '2025-09-15 00:28:01'),
(86, 23, 19, 'Cheese Lover', '', 1, 500.00, 500.00, '', '2025-09-15 00:28:01'),
(87, 23, 20, 'Labnani Pizza', '', 1, 750.00, 750.00, '', '2025-09-15 00:28:01'),
(88, 24, 18, 'US Deep Dish', '', 1, 2600.00, 2600.00, '', '2025-09-17 09:47:41'),
(89, 25, 18, 'US Deep Dish', '', 1, 2600.00, 2600.00, '', '2025-09-17 09:49:31'),
(90, 25, 18, 'US Deep Dish', '', 1, 2600.00, 2600.00, '', '2025-09-17 09:49:31'),
(91, 26, 12, 'Chicken Tikka', '', 1, 1550.00, 1550.00, '', '2025-09-19 14:21:49');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'company_name', 'US FOODS', 'Company name for receipts and invoices', '2025-08-04 18:56:06', '2025-08-12 13:09:07'),
(2, 'company_address', 'Commercial Market #1 OPF Colony Peshawar', 'Company address', '2025-08-04 18:56:06', '2025-08-12 13:09:07'),
(3, 'company_phone', '091-2614488', 'Company phone number', '2025-08-04 18:56:06', '2025-08-12 13:30:49'),
(4, 'company_email', '0313-9508243', 'Company email address', '2025-08-04 18:56:06', '2025-08-12 13:20:59'),
(5, 'company_website', '', 'Company website', '2025-08-04 18:56:06', '2025-08-15 06:40:54'),
(6, 'company_gst', 'GST123456789', 'Company GST number', '2025-08-04 18:56:06', '2025-08-04 18:56:06'),
(7, 'company_license', 'LIC123456789', 'Business license number', '2025-08-04 18:56:06', '2025-08-04 18:56:06'),
(8, 'tax_rate', '0', 'Tax rate percentage', '2025-08-04 18:56:06', '2025-08-12 13:09:07'),
(9, 'currency', 'PKR', 'Currency symbol', '2025-08-04 18:56:06', '2025-08-04 18:56:06'),
(10, 'receipt_footer', 'Once Bill Printed, order can not be cancel', 'Footer message for receipts', '2025-08-04 18:56:06', '2025-08-12 13:10:09'),
(11, 'auto_order_number', 'true', 'Auto-generate order numbers', '2025-08-04 18:56:06', '2025-08-04 18:56:06'),
(23, 'order_prefix', 'ORD', NULL, '2025-08-04 20:12:29', '2025-08-04 20:12:29');

-- --------------------------------------------------------

--
-- Table structure for table `special_offers`
--

CREATE TABLE `special_offers` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percentage','fixed_amount') DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `minimum_order_amount` decimal(10,2) DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','cashier') DEFAULT 'cashier',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, '2025-08-04 18:56:06', '2025-08-04 18:56:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_items_category_id` (`category_id`),
  ADD KEY `idx_items_is_available` (`is_available`);

--
-- Indexes for table `item_size_variants`
--
ALTER TABLE `item_size_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_size` (`item_id`,`size_name`),
  ADD KEY `idx_item_size_variants_item_id` (`item_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_orders_user_id` (`user_id`),
  ADD KEY `idx_orders_created_at` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_order_items_order_id` (`order_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `special_offers`
--
ALTER TABLE `special_offers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `item_size_variants`
--
ALTER TABLE `item_size_variants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=204;

--
-- AUTO_INCREMENT for table `special_offers`
--
ALTER TABLE `special_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_size_variants`
--
ALTER TABLE `item_size_variants`
  ADD CONSTRAINT `item_size_variants_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
