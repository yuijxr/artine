<?php
/**
 * Auto-generated database schema for `artine`
 * Generated on 2025-11-12 16:34:17
 */

require_once __DIR__ . '/includes/db_connect.php';

$statements = [
    "CREATE TABLE IF NOT EXISTS `addresses` (
        `address_id` int(11) NOT NULL AUTO_INCREMENT,
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
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`address_id`),
        KEY `fk_addresses_user_cascade` (`user_id`),
        CONSTRAINT `fk_addresses_user_cascade` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "CREATE TABLE IF NOT EXISTS `admin_actions` (
        `admin_action_id` int(11) NOT NULL AUTO_INCREMENT,
        `admin_id` int(11) NOT NULL,
        `action_type` varchar(100) NOT NULL,
        `target_table` varchar(100) DEFAULT NULL,
        `target_id` int(11) DEFAULT NULL,
        `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
        `ip_address` varchar(45) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`admin_action_id`),
        KEY `admin_id` (`admin_id`),
        CONSTRAINT `admin_actions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "CREATE TABLE IF NOT EXISTS `admins` (
        `admin_id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(100) NOT NULL,
        `password_hash` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `user_id` int(11) DEFAULT NULL,
        PRIMARY KEY (`admin_id`),
        UNIQUE KEY `username` (`username`),
        UNIQUE KEY `user_id` (`user_id`),
        CONSTRAINT `fk_admins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "CREATE TABLE IF NOT EXISTS `cart` (
        `cart_id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `product_id` int(11) NOT NULL,
        `quantity` int(11) NOT NULL DEFAULT 1,
        `size` varchar(20) DEFAULT 'default',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`cart_id`),
        UNIQUE KEY `unique_cart_item` (`user_id`,`product_id`,`size`),
        KEY `product_id` (`product_id`),
        CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
        CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "CREATE TABLE IF NOT EXISTS `categories` (
        `category_id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        PRIMARY KEY (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    /* email_2fa_tokens removed - legacy table replaced by verification_codes (see auth/2fa_toggle.php) */
    
    /* Consolidated login_security table to replace failed_logins, login_attempts, login_locks.
       This table tracks attempts, last attempt time and lock state per identifier (email) and
       optionally user_id and ip. Additional columns (last_lock, lock_count_24h) are used to
       preserve the previous escalation logic (counting recent locks in last 24 hours).
    */
    "CREATE TABLE IF NOT EXISTS `login_security` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) DEFAULT NULL,
        `identifier` varchar(255) DEFAULT NULL,
        `ip` varchar(45) DEFAULT NULL,
        `attempt_count` int(11) NOT NULL DEFAULT 0,
        `last_attempt` timestamp NULL DEFAULT NULL,
        `locked_until` timestamp NULL DEFAULT NULL,
        `last_lock` timestamp NULL DEFAULT NULL,
        `lock_count_24h` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `ui_identifier` (`identifier`),
        KEY `idx_user` (`user_id`),
        CONSTRAINT `login_security_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "CREATE TABLE IF NOT EXISTS `order_items` (
        `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
        `order_id` int(11) NOT NULL,
        `product_id` int(11) NOT NULL,
        `product_name` varchar(150) NOT NULL,
        `product_price` decimal(10,2) NOT NULL,
        `quantity` int(11) NOT NULL,
        `size` varchar(20) DEFAULT NULL,
        `subtotal` decimal(10,2) NOT NULL,
        PRIMARY KEY (`order_item_id`),
        KEY `product_id` (`product_id`),
        KEY `fk_orderitems_order_cascade` (`order_id`),
        CONSTRAINT `fk_orderitems_order_cascade` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
        CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "CREATE TABLE IF NOT EXISTS `orders` (
        `order_id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `address_id` int(11) NOT NULL,
        `payment_method_id` int(11) NOT NULL,
        `total_amount` decimal(10,2) NOT NULL,
        `status` enum('pending','confirmed','paid','shipped','delivered','cancelled','returned') DEFAULT 'pending',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`order_id`),
        KEY `user_id` (`user_id`),
        KEY `address_id` (`address_id`),
        KEY `payment_method_id` (`payment_method_id`),
        CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
        CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`address_id`),
        CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`method_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "CREATE TABLE IF NOT EXISTS `payment_methods` (
        `method_id` int(11) NOT NULL AUTO_INCREMENT,
        `name` enum('COD','GCash','Credit Card') NOT NULL,
        PRIMARY KEY (`method_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "CREATE TABLE IF NOT EXISTS `products` (
        `product_id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(150) NOT NULL,
        `description` text DEFAULT NULL,
        `price` decimal(10,2) NOT NULL,
        `category_id` int(11) NOT NULL,
        `stock` int(11) DEFAULT 0,
        `image_url` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        `thumbnail_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`thumbnail_images`)),
        `deleted_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`product_id`),
        KEY `category_id` (`category_id`),
        CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "CREATE TABLE IF NOT EXISTS `sessions` (
        `session_id` varchar(128) NOT NULL,
        `user_id` int(11) NOT NULL,
        `ip` varchar(45) DEFAULT NULL,
        `user_agent` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `last_seen` timestamp NOT NULL DEFAULT current_timestamp(),
        `status` enum('active','logged_out') DEFAULT 'active',
        `logout_time` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`session_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "CREATE TABLE IF NOT EXISTS `users` (
        `user_id` int(11) NOT NULL AUTO_INCREMENT,
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
        `measurements_updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`user_id`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "CREATE TABLE IF NOT EXISTS `verification_attempts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `purpose` varchar(50) NOT NULL,
        `attempts` int(11) NOT NULL DEFAULT 0,
        `last_attempt` timestamp NULL DEFAULT NULL,
        `locked_until` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `ui_user_purpose` (`user_id`,`purpose`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
    
    "CREATE TABLE IF NOT EXISTS `verification_codes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `purpose` varchar(50) NOT NULL,
        `code_hash` varchar(255) NOT NULL,
        `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        `used` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        CONSTRAINT `verification_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
];

// Execute table creation
foreach ($statements as $index => $sql) {
    echo "Statement #" . ($index + 1) . ": ";
    if ($conn->query($sql) === TRUE) {
        echo "✅ OK<br>\n";
    } else {
        echo "❌ ERROR: " . htmlspecialchars($conn->error) . "<br>\n";
        echo "SQL: " . htmlspecialchars($sql) . "<br><br>\n";
    }
}

echo "Done.";
