-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 04, 2025 at 10:52 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pizza_pos`
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
(16, 'DRINKS', 'fas fa-glass-whiskey', 'default-category.jpg', 16, 1, '2025-08-04 20:07:13'),
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `name`, `category_id`, `price`, `image`, `description`, `is_available`, `is_deleted`, `stock_quantity`, `has_size_variants`, `created_at`, `updated_at`) VALUES
(11, 'US Special Pizza', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:00:15', '2025-08-04 19:00:15'),
(12, 'Chicken Tikka', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:03:26', '2025-08-04 19:03:26'),
(13, 'Crown Crust', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:05:13', '2025-08-04 19:05:13'),
(14, 'Hot &amp; Spicy', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:06:43', '2025-08-04 19:06:43'),
(15, 'Malai Boti', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:07:52', '2025-08-04 19:07:52'),
(16, 'Calzone Pizza', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:09:14', '2025-08-04 19:09:14'),
(17, 'US Royal Stuff', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:10:02', '2025-08-04 19:10:02'),
(18, 'US Deep Dish', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:10:51', '2025-08-04 19:10:51'),
(19, 'Cheese Lover', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:11:45', '2025-08-04 19:11:45'),
(20, 'Labnani Pizza', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:12:49', '2025-08-04 19:12:49'),
(21, 'Chicken Fajita', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:13:53', '2025-08-04 19:13:53'),
(22, 'Four Session Pizza', 1, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:14:51', '2025-08-04 19:14:51'),
(24, 'Matka Pizza', 1, 1000.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:20:56', '2025-08-04 19:20:56'),
(25, 'US Special Tower', 2, 600.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:23:05', '2025-08-04 19:23:05'),
(26, 'Zinger Burger', 2, 500.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:23:36', '2025-08-04 19:23:36'),
(27, 'Small Zinger Burger', 2, 350.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:24:04', '2025-08-04 19:24:04'),
(28, 'Grill Chicken Burger', 2, 500.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:25:03', '2025-08-04 19:25:03'),
(29, 'Small Grill Burger', 2, 350.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:25:40', '2025-08-04 19:25:40'),
(30, 'Zinger Superme', 2, 550.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:26:03', '2025-08-04 19:26:03'),
(31, 'Chicken Steak', 2, 500.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:26:30', '2025-08-04 19:26:30'),
(32, 'Chef Burger', 2, 650.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:27:12', '2025-08-04 19:27:12'),
(33, 'Bufflo Wings', 4, 450.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:28:54', '2025-08-04 19:28:54'),
(34, 'BBQ Wings', 4, 450.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:29:19', '2025-08-04 19:29:19'),
(35, 'Hot &amp; Spicy Wings', 4, 450.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:29:47', '2025-08-04 19:29:47'),
(36, 'Zinger Wings', 4, 400.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:30:09', '2025-08-04 19:30:09'),
(37, 'Pieces 1', 3, 220.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:32:02', '2025-08-04 19:32:02'),
(38, 'Pieces 2', 3, 420.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:32:34', '2025-08-04 19:32:34'),
(39, 'Pieces 3', 3, 600.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:33:01', '2025-08-04 19:33:01'),
(40, 'Pieces 5', 3, 1000.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:33:48', '2025-08-04 19:33:48'),
(41, 'Pieces 5', 13, 300.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:36:15', '2025-08-04 19:36:15'),
(42, 'Pieces 10', 13, 550.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:36:30', '2025-08-04 19:36:30'),
(43, 'Pieces 15', 13, 850.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:36:51', '2025-08-04 19:36:51'),
(44, 'Pieces 20', 13, 1100.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:37:14', '2025-08-04 19:37:14'),
(45, 'Shawarma', 9, 180.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:38:03', '2025-08-04 19:38:03'),
(46, 'Special Shawarma', 9, 260.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:38:27', '2025-08-04 19:38:27'),
(47, 'Zinger Shawarma', 9, 300.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:39:27', '2025-08-04 19:39:27'),
(48, 'Paratha Roll', 9, 300.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:40:12', '2025-08-04 19:40:12'),
(49, 'Special Paratha Roll', 9, 350.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:40:48', '2025-08-04 19:40:48'),
(50, 'Zinger Paratha Roll', 9, 320.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:41:58', '2025-08-04 19:41:58'),
(51, 'Chicken Manchurian', 6, 650.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:42:52', '2025-08-04 19:42:52'),
(52, 'Chicken Chilli Dry', 6, 650.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:43:49', '2025-08-04 19:43:49'),
(53, 'Chicken Shashlik', 6, 650.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:44:41', '2025-08-04 19:44:41'),
(54, 'Chicken Fried Rice', 6, 500.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:45:13', '2025-08-04 19:45:13'),
(55, 'Vegetable Fried Rice', 6, 400.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:45:48', '2025-08-04 19:45:48'),
(56, 'US Special Chowmein', 14, 650.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:47:55', '2025-08-04 19:47:55'),
(57, 'Chicken Chowmein', 14, 600.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:48:18', '2025-08-04 19:48:18'),
(58, 'Alfredo Pasta', 15, 650.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:49:31', '2025-08-04 19:49:31'),
(59, 'Chicken Lesagne', 15, 680.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:50:02', '2025-08-04 19:50:02'),
(60, 'Grill Chicken Sandwich', 12, 350.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:50:37', '2025-08-04 19:50:37'),
(61, 'Grill Glub Sandwich', 12, 500.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:51:02', '2025-08-04 19:51:02'),
(62, 'Chicken Panini', 12, 500.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:51:25', '2025-08-04 19:51:25'),
(63, 'Regular Fries', 10, 230.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:52:12', '2025-08-04 19:52:12'),
(64, 'Large Fries', 10, 350.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:52:33', '2025-08-04 19:52:33'),
(65, 'Loaded Fries', 10, 450.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:52:51', '2025-08-04 19:52:51'),
(66, 'Cheese Fries', 10, 400.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:53:37', '2025-08-04 19:53:37'),
(67, 'Matka Fries', 10, 700.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:54:23', '2025-08-04 19:54:23'),
(68, 'Pizza', 10, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:55:45', '2025-08-04 19:55:45'),
(69, 'Cappuccino', 8, 200.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:57:15', '2025-08-04 19:57:15'),
(70, 'Coffee', 8, 200.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:57:30', '2025-08-04 19:57:30'),
(71, 'Special Chai', 8, 160.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 19:57:52', '2025-08-04 19:57:52'),
(72, 'US Special Soup', 5, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 19:58:54', '2025-08-04 19:58:54'),
(73, 'Hot &amp; Sour', 5, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 20:03:40', '2025-08-04 20:03:40'),
(74, 'Chicken Corn', 5, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 20:04:20', '2025-08-04 20:04:20'),
(75, 'Mint Margarita', 16, 200.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 20:08:37', '2025-08-04 20:08:37'),
(76, 'Fresh Lime', 16, 150.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 20:08:58', '2025-08-04 20:08:58'),
(77, 'Oreo Shake', 16, 200.00, 'default-item.jpg', '', 1, 0, 0, 0, '2025-08-04 20:09:24', '2025-08-04 20:09:24'),
(81, 'Delivery', 17, 0.00, 'default-item.jpg', '', 1, 0, 0, 1, '2025-08-04 20:19:01', '2025-08-04 20:19:01');

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
(28, 14, 'Small', 520.00, 0, 1, '2025-08-04 19:06:43'),
(29, 14, 'Medium', 1050.00, 0, 1, '2025-08-04 19:06:43'),
(30, 14, 'Large', 1550.00, 0, 1, '2025-08-04 19:06:43'),
(31, 14, 'F 15', 2350.00, 0, 1, '2025-08-04 19:06:43'),
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
(61, 68, 'Small', 380.00, 0, 1, '2025-08-04 19:55:45'),
(62, 68, 'Large', 650.00, 0, 1, '2025-08-04 19:55:45'),
(63, 72, 'Half', 600.00, 0, 1, '2025-08-04 19:58:54'),
(64, 72, 'Full', 950.00, 0, 1, '2025-08-04 19:58:54'),
(65, 73, 'Half', 550.00, 0, 1, '2025-08-04 20:03:40'),
(66, 73, 'Full', 950.00, 0, 1, '2025-08-04 20:03:40'),
(67, 74, 'Half', 550.00, 0, 1, '2025-08-04 20:04:20'),
(68, 74, 'Full', 950.00, 0, 1, '2025-08-04 20:04:20'),
(69, 81, '1 km', 30.00, 0, 1, '2025-08-04 20:19:01'),
(70, 81, '3 km', 50.00, 0, 1, '2025-08-04 20:19:01'),
(71, 81, '5 km', 100.00, 0, 1, '2025-08-04 20:19:01'),
(72, 81, '10 km', 150.00, 0, 1, '2025-08-04 20:19:01'),
(73, 81, '15 km', 200.00, 0, 1, '2025-08-04 20:19:01');

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
(1, 'company_name', 'Fast Food POS', 'Company name for receipts and invoices', '2025-08-04 18:56:06', '2025-08-04 18:56:06'),
(2, 'company_address', 'US Foods Commercial Market # 1 OPF Colony Peshawar', 'Company address', '2025-08-04 18:56:06', '2025-08-04 20:12:29'),
(3, 'company_phone', '03139508243', 'Company phone number', '2025-08-04 18:56:06', '2025-08-04 20:12:29'),
(4, 'company_email', 'muhammadwasim.cusit@gmail.com', 'Company email address', '2025-08-04 18:56:06', '2025-08-04 20:12:29'),
(5, 'company_website', 'https://wasim-cusit.github.io/MR-MUHAMMAD-WASIM/', 'Company website', '2025-08-04 18:56:06', '2025-08-04 20:12:29'),
(6, 'company_gst', 'GST123456789', 'Company GST number', '2025-08-04 18:56:06', '2025-08-04 18:56:06'),
(7, 'company_license', 'LIC123456789', 'Business license number', '2025-08-04 18:56:06', '2025-08-04 18:56:06'),
(8, 'tax_rate', '2', 'Tax rate percentage', '2025-08-04 18:56:06', '2025-08-04 20:12:48'),
(9, 'currency', 'PKR', 'Currency symbol', '2025-08-04 18:56:06', '2025-08-04 18:56:06'),
(10, 'receipt_footer', 'Thank you for your order!', 'Footer message for receipts', '2025-08-04 18:56:06', '2025-08-04 18:56:06'),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `item_size_variants`
--
ALTER TABLE `item_size_variants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `special_offers`
--
ALTER TABLE `special_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
