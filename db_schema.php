<?php
/**
 * db_schema.php
 *
 * Run this file once (via browser or CLI) to create the database schema used by the app.
 * It uses the mysqli $conn from includes/db_connect.php. It prints the result for each
 * CREATE TABLE statement.
 */

require_once __DIR__ . '/includes/db_connect.php';

// If the `users` table exists and the `gender` column contains other/extra values,
// modify it to only allow 'male' and 'female'. This makes the schema change idempotent
// and safe to run multiple times.
try {
    $tbl = $conn->query("SHOW TABLES LIKE 'users'");
    if ($tbl && $tbl->num_rows > 0) {
        $col = $conn->query("SHOW COLUMNS FROM users LIKE 'gender'");
        if ($col && $col->num_rows > 0) {
            $c = $col->fetch_assoc();
            $type = $c['Type'] ?? '';
            // If the enum contains values other than male/female, run an ALTER to restrict it.
            if (stripos($type, "other") !== false || preg_match('/enum\((.*?)\)/i', $type, $m) && substr_count($m[1], "'")/2 > 2) {
                // Attempt a safe ALTER: convert any non-male/female values to 'female' before changing type
                // so we don't violate the new enum constraint. Update in a transaction where supported.
                $conn->begin_transaction();
                // Replace common legacy value 'other' or 'prefer_not_to_say' with 'female' (admin choice)
                $conn->query("UPDATE users SET gender = 'female' WHERE gender NOT IN ('male','female')");
                $conn->query("ALTER TABLE users MODIFY gender ENUM('male','female') NOT NULL");
                $conn->commit();
            }
        }
    }
} catch (Throwable $e) {
    // If anything fails here, we continue — the rest of schema creation will handle missing objects.
}

// Ensure 'confirmed' status exists in orders.status enum (safe to run repeatedly)
try {
    $tbl = $conn->query("SHOW TABLES LIKE 'orders'");
    if ($tbl && $tbl->num_rows > 0) {
        $col = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
        if ($col && $col->num_rows > 0) {
            $c = $col->fetch_assoc();
            $type = $c['Type'] ?? '';
            if (stripos($type, "confirmed") === false) {
                // Add 'confirmed' into the enum safely by recreating the enum with the new value
                $conn->begin_transaction();
                $conn->query("ALTER TABLE orders MODIFY status ENUM('pending','confirmed','paid','shipped','delivered','cancelled','returned') DEFAULT 'pending'");
                $conn->commit();
            }
        }
    }
} catch (Throwable $e) {
    // continue if alter fails; user can run migration manually
}

$statements = [
    // users
    "CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
    gender ENUM('male','female') NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        -- account / security fields
        email_verified TINYINT(1) NOT NULL DEFAULT 0,
        verification_token VARCHAR(255) DEFAULT NULL,
        verification_expires TIMESTAMP NULL DEFAULT NULL,
        last_login TIMESTAMP NULL DEFAULT NULL,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // measurements
    "CREATE TABLE IF NOT EXISTS measurements (
        measurement_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        base_model_url VARCHAR(255),
        shoulder_width DECIMAL(5,2),
        chest_bust DECIMAL(5,2),
        waist DECIMAL(5,2),
        torso_length DECIMAL(5,2),
        arm_length DECIMAL(5,2),
        body_shape VARCHAR(50),
        face_shape VARCHAR(50),
        skin_tone VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // admins (linkable to users table)
    "CREATE TABLE IF NOT EXISTS admins (
        admin_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL UNIQUE,
        username VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // addresses
    "CREATE TABLE IF NOT EXISTS addresses (
        address_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        full_name VARCHAR(200) NOT NULL,
        phone VARCHAR(20),
        street VARCHAR(255) NOT NULL,
        city VARCHAR(100) NOT NULL,
        province VARCHAR(100) NOT NULL,
        postal_code VARCHAR(20) NOT NULL,
        country VARCHAR(100) NOT NULL,
        is_default BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // payment_methods
    "CREATE TABLE IF NOT EXISTS payment_methods (
        method_id INT AUTO_INCREMENT PRIMARY KEY,
        name ENUM('COD','GCash','Credit Card') NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // categories
    "CREATE TABLE IF NOT EXISTS categories (
        category_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // products
    "CREATE TABLE IF NOT EXISTS products (
        product_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        category_id INT NOT NULL,
        stock INT DEFAULT 0,
    image_url VARCHAR(255),
        thumbnail_images JSON DEFAULT NULL,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // cart
    "CREATE TABLE IF NOT EXISTS cart (
        cart_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        size VARCHAR(20) DEFAULT 'default',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_cart_item (user_id, product_id, size),
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // orders
    "CREATE TABLE IF NOT EXISTS orders (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        address_id INT NOT NULL,
        payment_method_id INT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending','confirmed','paid','shipped','delivered','cancelled','returned') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id),
        FOREIGN KEY (address_id) REFERENCES addresses(address_id),
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods(method_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // order_items
    "CREATE TABLE IF NOT EXISTS order_items (
        order_item_id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(150) NOT NULL,
        product_price DECIMAL(10,2) NOT NULL,
        quantity INT NOT NULL,
        size VARCHAR(20),
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // failed login attempts
    "CREATE TABLE IF NOT EXISTS failed_logins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        ip VARCHAR(45),
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // sessions: track active sessions for users (to show active devices and allow logout-all)
    "CREATE TABLE IF NOT EXISTS sessions (
        session_id VARCHAR(128) PRIMARY KEY,
        user_id INT NOT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // password reset tokens (store hashed token)
    "CREATE TABLE IF NOT EXISTS password_resets (
        reset_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(255) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    // admin actions / audit log (matches existing schema used in this project)
    "CREATE TABLE IF NOT EXISTS admin_actions (
        admin_action_id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NULL,
        action_type VARCHAR(100) NOT NULL,
        target_table VARCHAR(100) DEFAULT NULL,
        target_id INT DEFAULT NULL,
        details JSON DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
];

// Execute statements sequentially and report results
foreach ($statements as $i => $sql) {
    $idx = $i + 1;
    echo "Statement #{$idx}: ";
    if ($conn->query($sql) === TRUE) {
        echo "OK<br>\n";
    } else {
        echo "ERROR: " . htmlspecialchars($conn->error) . "<br>\n";
        echo "SQL: " . htmlspecialchars($sql) . "<br><br>\n";
    }
}

echo "\nDone.";
