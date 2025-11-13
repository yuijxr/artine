<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/email_sender.php';

if (!is_logged_in()) {
    header('Location: /artine3/login.php');
    exit;
}
$user = current_user($conn);
if (!$user) {
    header('Location: /artine3/login.php');
    exit;
}

// create new token and show link
// create new token and expiry
// ensure column exists before updating (older DBs may not have the column)
$col = $conn->query("SHOW COLUMNS FROM users LIKE 'verification_expires'");
if (!$col || $col->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN verification_expires TIMESTAMP NULL DEFAULT NULL");
}

$token = bin2hex(random_bytes(16));
$ttl = intval(getenv('VERIFICATION_TTL_MIN') ?: 60);
$verification_expires = date('Y-m-d H:i:s', time() + ($ttl * 60));
$stmt = $conn->prepare('UPDATE users SET verification_token = ?, verification_expires = ?, email_verified = 0 WHERE user_id = ?');
if ($stmt) {
    $stmt->bind_param('ssi', $token, $verification_expires, $user['user_id']);
    $stmt->execute();
    $stmt->close();
}
$verification_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/artine3/auth/verify.php?token=' . urlencode($token);

// Try to send verification email to the user
$sendRes = send_verification_email($user['email'], $user['first_name'] ?? $user['email'], $token);
if (!empty($sendRes['success'])) {
    $sent_notice = 'Verification email sent. Please check your inbox.';
} else {
    $sent_error = 'Could not send verification email: ' . ($sendRes['error'] ?? 'unknown error');
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/artine3/assets/css/style.css">
    <title>Resend Verification</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main style="padding:20px;max-width:800px;margin:0 auto;">
        <h1>Resend Verification</h1>
            <?php if (!empty($sent_notice)): ?>
                <div class="notice"><?php echo htmlspecialchars($sent_notice); ?></div>
            <?php elseif (!empty($sent_error)): ?>
                <div class="error-box"><ul><li><?php echo htmlspecialchars($sent_error); ?></li></ul></div>
            <?php else: ?>
                <p>If an email address is associated with your account we attempted to send a verification link. Please check your inbox.</p>
            <?php endif; ?>
        <p><a href="/artine3/account.php">Back to account</a></p>
    </main>
</body>
</html>