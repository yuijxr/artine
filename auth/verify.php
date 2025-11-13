<?php
require_once __DIR__ . '/../includes/db_connect.php';

$token = $_GET['token'] ?? '';
$message = '';
if ($token) {
    // Ensure column exists (older DBs may not have verification_expires)
    $col = $conn->query("SHOW COLUMNS FROM users LIKE 'verification_expires'");
    if (!$col || $col->num_rows === 0) {
        // If column is missing, create it so subsequent operations are consistent
        $conn->query("ALTER TABLE users ADD COLUMN verification_expires TIMESTAMP NULL DEFAULT NULL");
    }

    $stmt = $conn->prepare('SELECT user_id, email_verified, verification_expires FROM users WHERE verification_token = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            if ($row['email_verified']) {
                $message = 'Email already verified.';
            } else {
                // check expiry
                if (!empty($row['verification_expires'])) {
                    $expires = strtotime($row['verification_expires']);
                    if ($expires < time()) {
                        $message = 'Verification token has expired. Please request a new verification email.';
                        // do not allow verification with expired token
                        // optionally, clear the token so it can't be reused
                        $clear = $conn->prepare('UPDATE users SET verification_token = NULL, verification_expires = NULL WHERE user_id = ?');
                        if ($clear) { $clear->bind_param('i', $row['user_id']); $clear->execute(); $clear->close(); }
                        // skip further processing
                        goto _verify_done;
                    }
                } else {
                    $message = 'Invalid or expired verification token.';
                    goto _verify_done;
                }
                $up = $conn->prepare('UPDATE users SET email_verified = 1, verification_token = NULL WHERE user_id = ?');
                if ($up) {
                    $up->bind_param('i', $row['user_id']);
                    $up->execute();
                    $up->close();
                    $message = 'Email verified successfully. You may now log in.';
                } else {
                    $message = 'Database error while verifying.';
                }
            }
        } else {
            $message = 'Invalid verification token.';
        }
    } else {
        $message = 'Database error.';
    }
} else {
_verify_done:
    $message = 'Missing token.';
}
?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/artine3/assets/css/style.css">
    <title>Email Verification</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main style="padding:20px;max-width:800px;margin:0 auto;">
        <h1>Email Verification</h1>
        <p><?php echo htmlspecialchars($message); ?></p>
        <p><a href="/artine3/login.php">Go to login</a></p>
    </main>
</body>
</html>