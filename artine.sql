-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 05, 2025 at 04:31 PM
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
  `street` varchar(255) NOT NULL,
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

INSERT INTO `addresses` (`address_id`, `user_id`, `full_name`, `phone`, `street`, `city`, `province`, `postal_code`, `country`, `is_default`, `created_at`) VALUES
(5, 2, 'Jessa Barbosa', '12345678910', 'Block 24 Lot 55 Maypajo', 'Caloocan City', 'Manila', '1419', 'Philippines', 1, '2025-10-14 10:01:33'),
(11, 6, 'Jeremie Barbosa', '12345678910', 'Block 24 Lot 51', 'Caloocan City', 'Manila', '1410', 'Philippines', 1, '2025-11-05 08:24:53'),
(38, 1, 'Jeremie Barbosa', '09915223550', 'Block 24 Lot 51', 'Caloocan City', 'Metro Manila', '1410', 'Philippines', 0, '2025-11-05 11:53:53'),
(40, 1, 'Jeremie Barbosa', '12345678910', 'Block 24 Lot 52', 'Caloocan City', 'Metro Manila', '1410', 'Philippines', 0, '2025-11-05 12:07:14'),
(41, 1, 'Jeremie Barbosa', '12345678910', 'Block 24 Lot 55', 'Caloocan City', 'Metro Manila', '1410', 'Philippines', 1, '2025-11-05 13:33:26');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `measurements`
--

CREATE TABLE `measurements` (
  `measurement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `base_model_url` varchar(255) DEFAULT NULL,
  `shoulder_width` decimal(5,2) DEFAULT NULL,
  `chest_bust` decimal(5,2) DEFAULT NULL,
  `waist` decimal(5,2) DEFAULT NULL,
  `torso_length` decimal(5,2) DEFAULT NULL,
  `arm_length` decimal(5,2) DEFAULT NULL,
  `body_shape` varchar(50) DEFAULT NULL,
  `face_shape` varchar(50) DEFAULT NULL,
  `skin_tone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
  `status` enum('pending','paid','shipped','delivered','cancelled','returned') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `address_id`, `payment_method_id`, `total_amount`, `status`, `created_at`, `updated_at`) VALUES
(47, 1, 40, 1, 1089.00, 'pending', '2025-11-05 12:36:32', '2025-11-05 12:36:32'),
(48, 1, 40, 1, 569.00, 'cancelled', '2025-11-05 12:39:22', '2025-11-05 13:36:22'),
(49, 1, 40, 1, 1089.00, 'pending', '2025-11-05 13:05:11', '2025-11-05 13:05:11'),
(50, 1, 38, 1, 2649.00, 'pending', '2025-11-05 13:31:16', '2025-11-05 13:31:16'),
(51, 1, 41, 1, 3689.00, 'cancelled', '2025-11-05 14:40:42', '2025-11-05 14:40:48'),
(52, 1, 40, 1, 569.00, 'pending', '2025-11-05 14:55:25', '2025-11-05 14:55:25'),
(53, 1, 38, 1, 569.00, 'cancelled', '2025-11-05 15:09:06', '2025-11-05 15:09:11'),
(54, 1, 41, 1, 199.00, 'pending', '2025-11-05 15:27:08', '2025-11-05 15:27:08');

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
  `color` varchar(50) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `product_name`, `product_price`, `quantity`, `size`, `color`, `subtotal`) VALUES
(67, 47, 3, 'Kawhi \'KLAW\' Leonard v1', 520.00, 2, 'XS', NULL, 1040.00),
(68, 48, 4, 'Kawhi \'KLAW\' Leonard v2', 520.00, 1, 'XS', NULL, 520.00),
(69, 49, 4, 'Kawhi \'KLAW\' Leonard v2', 520.00, 1, 'XS', NULL, 520.00),
(70, 49, 3, 'Kawhi \'KLAW\' Leonard v1', 520.00, 1, 'XS', NULL, 520.00),
(71, 50, 3, 'Kawhi \'KLAW\' Leonard v1', 520.00, 2, 'XS', NULL, 1040.00),
(72, 50, 4, 'Kawhi \'KLAW\' Leonard v2', 520.00, 2, 'XS', NULL, 1040.00),
(73, 50, 12, 'OSIP MAESTRO v4', 520.00, 1, 'XS', NULL, 520.00),
(74, 51, 7, 'Kyrie \'DREW\' Irving', 520.00, 1, 'XS', NULL, 520.00),
(75, 51, 2, 'Chris \'BREEZY\' Brown v2', 520.00, 4, 'XS', NULL, 2080.00),
(76, 51, 4, 'Kawhi \'KLAW\' Leonard v2', 520.00, 1, 'XS', NULL, 520.00),
(77, 51, 3, 'Kawhi \'KLAW\' Leonard v1', 520.00, 1, 'XL', NULL, 520.00),
(78, 52, 1, 'Chris \'BREEZY\' Brown v1', 520.00, 1, 'XS', NULL, 520.00),
(79, 53, 1, 'Chris \'BREEZY\' Brown v1', 520.00, 1, 'XS', NULL, 520.00),
(80, 54, 28, 'DRAKKAR NOIR', 150.00, 1, '100ml', NULL, 150.00);

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
  `thumbnail_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`thumbnail_images`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `name`, `description`, `price`, `category_id`, `stock`, `image_url`, `created_at`, `updated_at`, `thumbnail_images`) VALUES
(1, 'Chris \'BREEZY\' Brown v1', 'Premium cotton classic white t-shirt', 520.00, 1, 0, 'breezy v1.png', '2025-10-14 04:51:15', '2025-11-01 09:41:01', '[\"osip v3.png\", \"osip v2.png\", \"osip v1.png\"]'),
(2, 'Chris \'BREEZY\' Brown v2', 'Vintage style denim jacket', 520.00, 1, 0, 'breezy v2.png', '2025-10-14 04:51:15', '2025-10-30 04:47:09', NULL),
(3, 'Kawhi \'KLAW\' Leonard v1', 'Comfortable striped polo shirt', 520.00, 1, 0, 'kawhi v1.png', '2025-10-14 04:51:15', '2025-10-30 04:47:25', NULL),
(4, 'Kawhi \'KLAW\' Leonard v2', 'Breathable linen button-up shirt', 520.00, 1, 0, 'kawhi v2.png', '2025-10-14 04:51:15', '2025-10-30 04:47:37', NULL),
(5, 'Lamelo Ball', 'Urban style graphic print t-shirt', 520.00, 1, 0, 'ball.png', '2025-10-14 04:51:15', '2025-11-01 10:07:10', '[\"osip v3.png\", \"osip v2.png\", \"osip v1.png\"]'),
(6, 'Kobe x Jordan', 'Classic plaid flannel shirt', 520.00, 1, 0, 'kobe jordan.png', '2025-10-14 04:51:15', '2025-10-30 04:47:50', NULL),
(7, 'Kyrie \'DREW\' Irving', 'Comfortable henley long sleeve shirt', 520.00, 1, 0, 'drew.png', '2025-10-14 04:51:15', '2025-10-30 04:47:59', NULL),
(8, 'Luka Doncic', 'Professional oxford dress shirt', 520.00, 1, 0, 'luka.png', '2025-10-14 04:51:15', '2025-10-30 04:48:05', NULL),
(9, 'OSIP MAESTRO v1', 'Professional oxford dress shirt', 520.00, 1, 0, 'osip v1.png', '2025-10-14 04:51:15', '2025-10-30 04:48:11', NULL),
(10, 'OSIP MAESTRO v2', 'Professional oxford dress shirt', 520.00, 1, 0, 'osip v2.png', '2025-10-14 04:51:15', '2025-10-30 04:48:16', NULL),
(11, 'OSIP MAESTRO v3', 'Professional oxford dress shirt', 520.00, 1, 0, 'osip v3.png', '2025-10-14 04:51:15', '2025-10-30 04:48:22', NULL),
(12, 'OSIP MAESTRO v4', 'Professional oxford dress shirt', 520.00, 1, 0, 'osip v4.png', '2025-10-14 04:51:15', '2025-10-30 04:48:36', NULL),
(13, 'Denver Nuggets', 'Adjustable classic baseball cap', 350.00, 2, 0, 'denver.png', '2025-10-14 04:51:15', '2025-10-30 04:48:41', NULL),
(14, 'New York City', 'Urban style snapback cap', 350.00, 2, 0, 'nyc.png', '2025-10-14 04:51:15', '2025-10-30 04:48:46', NULL),
(15, 'Los Angeles', 'Trendy bucket hat', 350.00, 2, 0, 'la.png', '2025-10-14 04:51:15', '2025-10-30 04:48:52', NULL),
(16, 'Boston Celtics', 'Mesh back trucker cap', 350.00, 2, 0, 'boston.png', '2025-10-14 04:51:15', '2025-10-30 04:49:08', NULL),
(17, 'Chicago Bulls', 'Warm knitted beanie', 350.00, 2, 0, 'bulls.png', '2025-10-14 04:51:15', '2025-10-30 04:49:13', NULL),
(18, 'Raiders', 'Classic flat cap', 350.00, 2, 0, 'raiders.png', '2025-10-14 04:51:15', '2025-10-30 04:49:18', NULL),
(19, 'N.W.A', 'Sports visor cap', 350.00, 2, 0, 'nwa.png', '2025-10-14 04:51:15', '2025-10-30 04:49:24', NULL),
(20, 'George Town', 'Summer straw hat', 350.00, 2, 0, 'georgetown.png', '2025-10-14 04:51:15', '2025-10-30 04:49:30', NULL),
(21, 'Chicago White Sox', 'Summer straw hat', 350.00, 2, 0, 'sox.png', '2025-10-14 04:51:15', '2025-10-30 04:49:41', NULL),
(22, 'New York Yankees', 'Summer straw hat', 350.00, 2, 0, 'yankees.png', '2025-10-14 04:51:15', '2025-10-30 04:49:49', NULL),
(23, 'Caroline Tar Heels', 'Summer straw hat', 350.00, 2, 0, 'tarheels.png', '2025-10-14 04:51:15', '2025-10-30 04:49:54', NULL),
(24, 'Miami Dolphins', 'Summer straw hat', 350.00, 2, 0, 'dolphins.png', '2025-10-14 04:51:15', '2025-10-30 04:50:01', NULL),
(25, 'Detroit Tigers', 'Summer straw hat', 350.00, 2, 0, 'tigers.png', '2025-10-14 04:51:15', '2025-10-30 04:50:08', NULL),
(26, 'San Francisco Giants', 'Summer straw hat', 350.00, 2, 0, 'giants.png', '2025-10-14 04:51:15', '2025-10-30 04:50:29', NULL),
(27, 'POLO \'BLUE\'', 'Fresh aquatic scent', 150.00, 3, 0, 'polo blue.png', '2025-10-14 04:51:15', '2025-10-30 04:50:34', NULL),
(28, 'DRAKKAR NOIR', 'Deep woody fragrance', 150.00, 3, 0, 'noir.png', '2025-10-14 04:51:15', '2025-10-30 04:50:41', NULL),
(29, 'DESIRE BLUE', 'Energizing citrus scent', 150.00, 3, 0, 'desire.png', '2025-10-14 04:51:15', '2025-10-30 04:50:51', NULL),
(30, 'ETERNITY', 'Sweet vanilla fragrance', 150.00, 3, 0, 'eternity.png', '2025-10-14 04:51:15', '2025-10-30 04:50:55', NULL),
(31, 'ISSEY MIYAKE', 'Calming lavender scent', 150.00, 3, 0, 'issey.png', '2025-10-14 04:51:15', '2025-10-30 04:50:58', NULL),
(32, 'LACOSTE \'BLACK\'', 'Rich amber and wood fragrance', 150.00, 3, 0, 'lacoste.png', '2025-10-14 04:51:15', '2025-10-30 04:51:01', NULL),
(33, 'ACQUA DI GIO', 'Elegant rose fragrance', 150.00, 3, 0, 'gio.png', '2025-10-14 04:51:15', '2025-10-30 04:51:04', NULL),
(34, 'CLASSIC', 'Fresh pine forest scent', 150.00, 3, 0, 'classic.png', '2025-10-14 04:51:15', '2025-10-30 04:51:07', NULL);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `gender`, `email`, `password_hash`, `phone`, `created_at`, `updated_at`) VALUES
(1, 'Jeremie', 'Barbosa', 'male', 'jrmugly3@gmail.com', '$2y$10$t6Scq.A2/d.NM/lX0l46IOPYGpio83MSVJ9nNpujRxa18NrJtMUwS', '12345678910', '2025-10-14 05:30:58', '2025-10-14 05:30:58'),
(2, 'Jessa', 'Barbosa', 'female', 'jessajoyvc@gmail.com', '$2y$10$.ZtDZwgqdHGPeDNdJFULg.iOgeqHt7HSj2oSUHoisN8kSqFTkljmK', '12345678910', '2025-10-14 05:33:17', '2025-10-14 05:33:17'),
(5, 'Jeremie', 'Barbosa', 'male', 'jer@gmail.com', '$2y$10$GNoX1rxttAyih7WetFIHPOIq0keL76WwdMLNaatWLAcL9LCqGmCKu', '12345678910', '2025-10-29 14:57:06', '2025-10-29 14:57:06'),
(6, 'Jeremie', 'Barbosa', 'male', 'jepoy28@gmail.com', '$2y$10$LYcawWGXThSsbkG5rrCM0uD2l7d99cOGtyEFaUU4O08cPDYJNcplS', '12345678910', '2025-10-30 12:05:56', '2025-10-30 12:05:56'),
(7, 'Jeremie', 'Barbosa', 'male', 'jer123@gmail.com', '$2y$10$o1bdwZtt4nmkoxjFbhHbIewarhEfpN3Lt46Ke22pVhev/9.kn5Mqq', '12345678910', '2025-11-02 06:46:49', '2025-11-02 06:46:49'),
(8, 'Jeremie', 'Barbosa', 'male', 'jeremie3@gmail.com', '$2y$10$NJmgEPnwpBTEntcc0mPSYuV/EeOcSGH7HRb4NmWEqbmXawc1yWPhm', '12345678910', '2025-11-02 12:30:29', '2025-11-02 12:30:29'),
(9, 'Jeremie', 'Barbosa', 'male', '422002632@ntc.edu.ph', '$2y$10$WVFiJG9oacZJKjltV.3yDenmRjezbGW39m0dLuaC4UBWuvgixkeb.', '12345678910', '2025-11-04 16:15:10', '2025-11-04 16:15:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

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
-- Indexes for table `measurements`
--
ALTER TABLE `measurements`
  ADD PRIMARY KEY (`measurement_id`),
  ADD KEY `user_id` (`user_id`);

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
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `address_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=176;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `measurements`
--
ALTER TABLE `measurements`
  MODIFY `measurement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `method_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `measurements`
--
ALTER TABLE `measurements`
  ADD CONSTRAINT `measurements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

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
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
