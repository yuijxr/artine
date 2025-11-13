-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 13, 2025 at 08:20 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `artine`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `house_number` varchar(50) DEFAULT NULL,
  `street` varchar(255) NOT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country` varchar(100) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`address_id`, `user_id`, `full_name`, `phone`, `house_number`, `street`, `barangay`, `city`, `province`, `postal_code`, `country`, `is_default`, `created_at`) VALUES
(49, 42, 'Jessa Caboteja', '09915223550', 'Block 24 Lot 51', 'Kaunlaran Village', '35', 'Caloocan City', 'Metro Manila', '1410', 'Philippines', 1, '2025-11-12 19:39:35'),
(54, 41, 'Jessa Caboteja', '09915223550', 'Block 24 Lot 51', 'Kaunlaran Village', '35', 'Caloocan City', 'Metro Manila', '1410', 'Philippines', 1, '2025-11-13 06:33:42');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password_hash`, `created_at`, `user_id`) VALUES
(1, 'artineclothing', '$2y$10$BR4mYu1nNPw8E5ZeAnQ5aeZPOQZePnIWxfww.dV1HM/g3yxrmnQMa', '2025-11-08 12:14:31', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_actions`
--

CREATE TABLE `admin_actions` (
  `admin_action_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `target_table` varchar(100) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_actions`
--

INSERT INTO `admin_actions` (`admin_action_id`, `admin_id`, `action_type`, `target_table`, `target_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'update_product', 'products', 49, '{\"product_id\":49,\"name\":\"travis\"}', '::1', '2025-11-09 20:29:00'),
(2, 1, 'delete_product', 'products', 49, '{\"product_id\":49,\"image\":\"shirts\\/jessa-2.jpg\"}', '::1', '2025-11-09 20:30:15');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `size` varchar(20) DEFAULT 'default',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`) VALUES
(1, 'Shirts'),
(2, 'Caps'),
(3, 'Perfumes');

-- --------------------------------------------------------

--
-- Table structure for table `login_security`
--

CREATE TABLE `login_security` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `identifier` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `last_attempt` timestamp NULL DEFAULT NULL,
  `locked_until` timestamp NULL DEFAULT NULL,
  `last_lock` timestamp NULL DEFAULT NULL,
  `lock_count_24h` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_id` int(11) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','paid','shipped','delivered','cancelled','returned') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `address_id`, `payment_method_id`, `total_amount`, `status`, `created_at`, `updated_at`) VALUES
(69, 42, 49, 1, 579.00, 'pending', '2025-11-12 19:45:35', '2025-11-12 19:45:35'),
(70, 41, 54, 1, 579.00, 'pending', '2025-11-13 06:57:41', '2025-11-13 06:57:41'),
(71, 41, 54, 1, 569.00, 'cancelled', '2025-11-13 06:57:56', '2025-11-13 06:58:04');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `product_price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `size` varchar(20) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `product_name`, `product_price`, `quantity`, `size`, `subtotal`) VALUES
(100, 69, 1, 'Chris \'BREEZY\' Brown v1', 530.00, 1, 'XS', 530.00),
(101, 70, 1, 'Chris \'BREEZY\' Brown v1', 530.00, 1, 'XS', 530.00),
(102, 71, 6, 'Kobe x Jordan', 520.00, 1, 'XL', 520.00);

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `method_id` int(11) NOT NULL,
  `name` enum('COD','GCash','Credit Card') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`method_id`, `name`) VALUES
(1, 'COD'),
(2, 'GCash'),
(3, 'Credit Card');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category_id` int(11) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `thumbnail_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`thumbnail_images`)),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `name`, `description`, `price`, `category_id`, `stock`, `image_url`, `created_at`, `updated_at`, `thumbnail_images`, `deleted_at`) VALUES
(1, 'Chris \'BREEZY\' Brown v1', 'Premium cotton classic white t-shirt', 530.00, 1, 0, 'shirts/breezy v1.png', '2025-10-14 04:51:15', '2025-11-09 14:01:52', '[\"osip v3.png\", \"osip v2.png\", \"osip v1.png\"]', NULL),
(2, 'Chris \'BREEZY\' Brown v2', 'Vintage style denim jacket', 520.00, 1, 0, 'breezy v2.png', '2025-10-14 04:51:15', '2025-10-30 04:47:09', NULL, NULL),
(3, 'Kawhi \'KLAW\' Leonard v1', 'Comfortable striped polo shirt', 520.00, 1, 0, 'kawhi v1.png', '2025-10-14 04:51:15', '2025-10-30 04:47:25', NULL, NULL),
(4, 'Kawhi \'KLAW\' Leonard v2', 'Breathable linen button-up shirt', 520.00, 1, 0, 'kawhi v2.png', '2025-10-14 04:51:15', '2025-10-30 04:47:37', NULL, NULL),
(5, 'Lamelo Ball', 'Urban style graphic print t-shirt', 520.00, 1, 0, 'ball.png', '2025-10-14 04:51:15', '2025-11-01 10:07:10', '[\"osip v3.png\", \"osip v2.png\", \"osip v1.png\"]', NULL),
(6, 'Kobe x Jordan', 'Classic plaid flannel shirt', 520.00, 1, 0, 'kobe jordan.png', '2025-10-14 04:51:15', '2025-10-30 04:47:50', NULL, NULL),
(7, 'Kyrie \'DREW\' Irving', 'Comfortable henley long sleeve shirt', 520.00, 1, 0, 'drew.png', '2025-10-14 04:51:15', '2025-10-30 04:47:59', NULL, NULL),
(8, 'Luka Doncic', 'Professional oxford dress shirt', 520.00, 1, 0, 'luka.png', '2025-10-14 04:51:15', '2025-10-30 04:48:05', NULL, NULL),
(9, 'OSIP MAESTRO v1', 'Professional oxford dress shirt', 520.00, 1, 0, 'osip v1.png', '2025-10-14 04:51:15', '2025-10-30 04:48:11', NULL, NULL),
(10, 'OSIP MAESTRO v2', 'Professional oxford dress shirt', 520.00, 1, 0, 'osip v2.png', '2025-10-14 04:51:15', '2025-10-30 04:48:16', NULL, NULL),
(11, 'OSIP MAESTRO v3', 'Professional oxford dress shirt', 520.00, 1, 0, 'osip v3.png', '2025-10-14 04:51:15', '2025-10-30 04:48:22', NULL, NULL),
(12, 'OSIP MAESTRO v4', 'Professional oxford dress shirt', 520.00, 1, 0, 'osip v4.png', '2025-10-14 04:51:15', '2025-10-30 04:48:36', NULL, NULL),
(13, 'Denver Nuggets', 'Adjustable classic baseball cap', 350.00, 2, 0, 'denver.png', '2025-10-14 04:51:15', '2025-10-30 04:48:41', NULL, NULL),
(14, 'New York City', 'Urban style snapback cap', 350.00, 2, 0, 'nyc.png', '2025-10-14 04:51:15', '2025-10-30 04:48:46', NULL, NULL),
(15, 'Los Angeles', 'Trendy bucket hat', 350.00, 2, 0, 'la.png', '2025-10-14 04:51:15', '2025-10-30 04:48:52', NULL, NULL),
(16, 'Boston Celtics', 'Mesh back trucker cap', 350.00, 2, 0, 'boston.png', '2025-10-14 04:51:15', '2025-10-30 04:49:08', NULL, NULL),
(17, 'Chicago Bulls', 'Warm knitted beanie', 350.00, 2, 0, 'bulls.png', '2025-10-14 04:51:15', '2025-10-30 04:49:13', NULL, NULL),
(18, 'Raiders', 'Classic flat cap', 350.00, 2, 0, 'raiders.png', '2025-10-14 04:51:15', '2025-10-30 04:49:18', NULL, NULL),
(19, 'N.W.A', 'Sports visor cap', 350.00, 2, 0, 'nwa.png', '2025-10-14 04:51:15', '2025-10-30 04:49:24', NULL, NULL),
(20, 'George Town', 'Summer straw hat', 350.00, 2, 0, 'georgetown.png', '2025-10-14 04:51:15', '2025-10-30 04:49:30', NULL, NULL),
(21, 'Chicago White Sox', 'Summer straw hat', 350.00, 2, 0, 'sox.png', '2025-10-14 04:51:15', '2025-10-30 04:49:41', NULL, NULL),
(22, 'New York Yankees', 'Summer straw hat', 350.00, 2, 0, 'yankees.png', '2025-10-14 04:51:15', '2025-10-30 04:49:49', NULL, NULL),
(23, 'Caroline Tar Heels', 'Summer straw hat', 350.00, 2, 0, 'tarheels.png', '2025-10-14 04:51:15', '2025-10-30 04:49:54', NULL, NULL),
(24, 'Miami Dolphins', 'Summer straw hat', 350.00, 2, 0, 'dolphins.png', '2025-10-14 04:51:15', '2025-10-30 04:50:01', NULL, NULL),
(25, 'Detroit Tigers', 'Summer straw hat', 350.00, 2, 0, 'tigers.png', '2025-10-14 04:51:15', '2025-10-30 04:50:08', NULL, NULL),
(26, 'San Francisco Giants', 'Summer straw hat', 350.00, 2, 0, 'giants.png', '2025-10-14 04:51:15', '2025-10-30 04:50:29', NULL, NULL),
(27, 'POLO \'BLUE\'', 'Fresh aquatic scent', 150.00, 3, 0, 'polo blue.png', '2025-10-14 04:51:15', '2025-10-30 04:50:34', NULL, NULL),
(28, 'DRAKKAR NOIR', 'Deep woody fragrance', 150.00, 3, 0, 'noir.png', '2025-10-14 04:51:15', '2025-10-30 04:50:41', NULL, NULL),
(29, 'DESIRE BLUE', 'Energizing citrus scent', 150.00, 3, 0, 'desire.png', '2025-10-14 04:51:15', '2025-10-30 04:50:51', NULL, NULL),
(30, 'ETERNITY', 'Sweet vanilla fragrance', 150.00, 3, 0, 'eternity.png', '2025-10-14 04:51:15', '2025-10-30 04:50:55', NULL, NULL),
(31, 'ISSEY MIYAKE', 'Calming lavender scent', 150.00, 3, 0, 'issey.png', '2025-10-14 04:51:15', '2025-10-30 04:50:58', NULL, NULL),
(32, 'LACOSTE \'BLACK\'', 'Rich amber and wood fragrance', 150.00, 3, 0, 'lacoste.png', '2025-10-14 04:51:15', '2025-10-30 04:51:01', NULL, NULL),
(33, 'ACQUA DI GIO', 'Elegant rose fragrance', 150.00, 3, 0, 'gio.png', '2025-10-14 04:51:15', '2025-10-30 04:51:04', NULL, NULL),
(34, 'CLASSIC', 'Fresh pine forest scent', 150.00, 3, 0, 'classic.png', '2025-10-14 04:51:15', '2025-10-30 04:51:07', NULL, NULL),
(48, 'test', 'test', 300.00, 1, 50, 'shirts/jessa.jpg', '2025-11-09 14:24:23', '2025-11-09 14:25:06', '[\"shirts\\/breezy v1-1.png\",\"shirts\\/breezy v2-1.png\",\"shirts\\/drew.png\",\"shirts\\/kawhi v1.png\",\"shirts\\/kawhi v2.png\"]', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','logged_out') DEFAULT 'active',
  `logout_time` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`session_id`, `user_id`, `ip`, `user_agent`, `created_at`, `last_seen`, `status`, `logout_time`) VALUES
('5f4c757pkr1q2gqhh2c6pkpuga', 42, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-12 19:45:21', '2025-11-12 19:48:17', 'logged_out', '2025-11-12 19:48:17'),
('8dpjhpmv9795nru0gftnsftqhh', 42, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-12 19:38:40', '2025-11-12 19:40:18', 'logged_out', '2025-11-12 19:40:18'),
('av4ip14cukmr0chnjlcaj1vjbq', 41, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-12 20:02:08', '2025-11-12 20:03:38', 'active', NULL),
('cfs4umna1d6ja97arihr3bpiak', 41, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-13 06:29:45', '2025-11-13 07:20:29', 'active', NULL),
('moerv0us8tpua0a8e4uoffq5pi', 39, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-12 16:37:28', '2025-11-12 16:43:07', 'logged_out', '2025-11-12 16:43:07'),
('sq6r1ev3t81b56mt6eb03dkbf6', 39, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-12 16:16:02', '2025-11-12 16:37:09', 'logged_out', '2025-11-12 16:37:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('male','female') NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_2fa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `shoulder_width` decimal(5,2) DEFAULT NULL,
  `chest_bust` decimal(5,2) DEFAULT NULL,
  `waist` decimal(5,2) DEFAULT NULL,
  `torso_length` decimal(5,2) DEFAULT NULL,
  `arm_length` decimal(5,2) DEFAULT NULL,
  `body_shape` varchar(50) DEFAULT NULL,
  `face_shape` varchar(50) DEFAULT NULL,
  `skin_tone` varchar(50) DEFAULT NULL,
  `base_model_url` varchar(255) DEFAULT NULL,
  `measurements_updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `gender`, `email`, `password_hash`, `phone`, `created_at`, `updated_at`, `email_verified`, `email_2fa_enabled`, `last_login`, `deleted_at`, `shoulder_width`, `chest_bust`, `waist`, `torso_length`, `arm_length`, `body_shape`, `face_shape`, `skin_tone`, `base_model_url`, `measurements_updated_at`) VALUES
(41, 'Jessa', 'Caboteja', 'female', '422002632@ntc.edu.ph', '$2y$10$Fwh.Ozay5XUYkmyyPkMu2uuVOhZrLZo/zUatr6u1ybHx9RDPDe.IG', '09915223550', '2025-11-12 16:43:56', '2025-11-13 07:13:54', 1, 0, '2025-11-12 20:02:08', NULL, 40.00, 80.00, 70.00, 50.00, 150.00, 'Straight Body', 'Oval Face Shape', '#FFDFC4', NULL, '2025-11-13 07:13:54'),
(42, 'Jessa', 'Caboteja', 'female', 'jrmugly3@gmail.com', '$2y$10$868yOHeJghJQ.N69xvhpvOWvuHBBLXEOVSASJsjQyEUdc64iAxTUS', '09915223550', '2025-11-12 19:00:42', '2025-11-12 19:47:29', 1, 1, '2025-11-12 19:45:21', NULL, 48.00, 94.00, 84.00, 64.00, 175.00, 'Straight Body', 'Oval Face Shape', '#FFDFC4', NULL, '2025-11-12 19:47:29');

-- --------------------------------------------------------

--
-- Table structure for table `verification_attempts`
--

CREATE TABLE `verification_attempts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `purpose` varchar(50) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `last_attempt` timestamp NULL DEFAULT NULL,
  `locked_until` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_attempts`
--

INSERT INTO `verification_attempts` (`id`, `user_id`, `purpose`, `attempts`, `last_attempt`, `locked_until`) VALUES
(9, 39, 'enable_2fa', 1, '2025-11-12 16:03:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `verification_codes`
--

CREATE TABLE `verification_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `purpose` varchar(50) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_codes`
--

INSERT INTO `verification_codes` (`id`, `user_id`, `purpose`, `code_hash`, `expires_at`, `used`, `created_at`) VALUES
(37, 41, 'email_verify', '$2y$10$p0oZqgqVEpOmvlQiNgHC8OhJLhT1LHvB9nEvIBx4CsBT.eGoiIKdC', '2025-11-12 16:44:26', 1, '2025-11-12 16:43:58'),
(38, 41, 'enable_2fa', '$2y$10$DZuH8i9/zUoqOZp90XqUJerKc0UjFfRALi2.B5eoAO4dLCgaOD5zG', '2025-11-12 09:50:36', 0, '2025-11-12 16:45:36'),
(39, 41, 'enable_2fa', '$2y$10$RC.gWqaKGKv8nODdr2j41O1q0xdMPo4lZeZJWVTf5LnciHLCBK/3e', '2025-11-12 09:50:38', 0, '2025-11-12 16:45:38'),
(40, 41, 'enable_2fa', '$2y$10$Djna0gFmzu4vgMmQflWVGuOzLWSSnXaebGqgpeZP7X93HbTKAIgBm', '2025-11-12 09:51:01', 0, '2025-11-12 16:46:01'),
(41, 41, 'enable_2fa', '$2y$10$XFcxBomKzGuadleP2JE7fuu0niEbZGjW9PUd49EabvysJ5qlqijGS', '2025-11-12 09:51:04', 0, '2025-11-12 16:46:04'),
(42, 41, 'enable_2fa', '$2y$10$41nywWLhxKhdcHk76yXWOurFidOZgCxkECDya4LXxgzaADZvVOA.i', '2025-11-12 16:46:50', 1, '2025-11-12 16:46:40'),
(43, 41, 'password_reset', '$2y$10$lEEP5.TvKPmk9qkrV80Yn.fguuoqRyb8L9VIYT19159vbQJS00ZSG', '2025-11-12 16:54:53', 1, '2025-11-12 16:54:44'),
(44, 41, 'login_2fa', '$2y$10$610Or6C6gWzYACYjd2QGu.Fy484Za4gH2V2lyZYHbHCpn3.rYOx/O', '2025-11-12 16:56:24', 1, '2025-11-12 16:56:14'),
(46, 41, 'password_reset', '$2y$10$fYj5PKRf5Y.3R.EMhkDYU.sJt39HMZCoaJ6PGU.dHT6sPVDj35I2y', '2025-11-12 17:31:49', 1, '2025-11-12 17:31:35'),
(47, 41, 'login_2fa', '$2y$10$11PwdepzuoegTjm09hvG.usFlH7DbXwxDetRX1OQsa2XTq.vzKasm', '2025-11-12 17:32:21', 1, '2025-11-12 17:32:11'),
(48, 41, 'enable_2fa', '$2y$10$FP4qu0wBjgVNGf7zczWsJO4fXrMTtXkkD8u9U0e.ryl5duPBKjg2G', '2025-11-12 17:32:46', 1, '2025-11-12 17:32:33'),
(49, 41, 'login_2fa', '$2y$10$NZIOjBuM.qSixfPhs5ylQOYLMbtwGmD4Kq35BzmJICgPKInZkp3bu', '2025-11-12 17:37:58', 1, '2025-11-12 17:37:46'),
(50, 41, 'email_verify', '$2y$10$lm2O7/EWdB432g0C4ne2f./RDybiGa5hAprMryx6DEWeMGzKaAAJK', '2025-11-12 10:47:16', 0, '2025-11-12 17:42:16'),
(51, 41, 'enable_2fa', '$2y$10$GXITjuEhPbXjlzqi2lCUCeP9ig9fXHBAYziuxKdLBVnfpjAwItRlW', '2025-11-12 17:49:12', 1, '2025-11-12 17:48:34'),
(52, 42, 'email_verify', '$2y$10$7hQhtznKcf1TCB6RzuwIdubsm9Fsdl4SpAMf0OADlyLvFnjxseT/2', '2025-11-12 19:01:21', 1, '2025-11-12 19:00:50'),
(53, 42, 'enable_2fa', '$2y$10$b.kVV/ou6aZYyt1RPwj7TegNQ.ni9i3MbHo8pluPkbJor/mQ6mkjC', '2025-11-12 19:38:43', 1, '2025-11-12 19:37:32'),
(54, 42, 'enable_2fa', '$2y$10$EJYGvMXVvYEJudUUFCk16eFSNAWEcCTpaM8c.scBhMEE03uCNcGaq', '2025-11-12 19:38:07', 1, '2025-11-12 19:37:35'),
(55, 42, 'login_2fa', '$2y$10$f0/BsUUlWp2HJCsAfom6QOr84.8nColRZcmrGkI6aUyhNEu08V8WC', '2025-11-12 19:38:40', 1, '2025-11-12 19:38:26'),
(56, 42, 'enable_2fa', '$2y$10$rgS/f8VaY9mHLY187vNlAO5pcG5W4rYTAZFsuaqGkONeNOVfz6.SW', '2025-11-12 19:39:11', 1, '2025-11-12 19:38:57'),
(57, 42, 'login_2fa', '$2y$10$NLYp1b7/m37QX6EsrD1Jlu4er.dhdQXsbIRqi3WVRFjXazSF0nHnG', '2025-11-12 19:45:21', 1, '2025-11-12 19:45:12'),
(58, 42, 'login_2fa', '$2y$10$Ki6vx3FcKoHSSC.TUkTxR.L9aOfc.Fr4I4QCuWfUOqh5QxCBNGEb.', '2025-11-12 12:53:40', 0, '2025-11-12 19:48:40'),
(59, 41, 'email_verify', '$2y$10$ycJVWxinbyt9OLU2ZsL3I.JDNvP2yxGYWGWf7H9a71px/459oNycy', '2025-11-12 19:52:05', 1, '2025-11-12 19:51:34'),
(60, 41, 'email_verify', '$2y$10$hZvbyl0.K1KyqVUXrjcf5.yut92BgcwL4Wm5kMSJPdZ031QCsPYO6', '2025-11-12 19:55:20', 1, '2025-11-12 19:55:09'),
(61, 41, 'email_verify', '$2y$10$ewK2rJooBiSjPYdsnpBiWOhCGD3Z.iUMXshr1TlmLr7X.jx7H2awG', '2025-11-12 20:02:01', 1, '2025-11-12 20:01:45');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `fk_addresses_user_cascade` (`user_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD PRIMARY KEY (`admin_action_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `unique_cart_item` (`user_id`,`product_id`,`size`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `login_security`
--
ALTER TABLE `login_security`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ui_identifier` (`identifier`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `payment_method_id` (`payment_method_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `fk_orderitems_order_cascade` (`order_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`method_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`session_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `verification_attempts`
--
ALTER TABLE `verification_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ui_user_purpose` (`user_id`,`purpose`);

--
-- Indexes for table `verification_codes`
--
ALTER TABLE `verification_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_actions`
--
ALTER TABLE `admin_actions`
  MODIFY `admin_action_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=207;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `login_security`
--
ALTER TABLE `login_security`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `method_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `verification_attempts`
--
ALTER TABLE `verification_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `verification_codes`
--
ALTER TABLE `verification_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `fk_addresses_user_cascade` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `fk_admins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD CONSTRAINT `admin_actions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `login_security`
--
ALTER TABLE `login_security`
  ADD CONSTRAINT `login_security_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`address_id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`method_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_orderitems_order_cascade` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);

--
-- Constraints for table `verification_codes`
--
ALTER TABLE `verification_codes`
  ADD CONSTRAINT `verification_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
