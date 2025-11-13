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
// create and send a 6-digit verification code for email verification
$ttl = intval(getenv('VERIFICATION_TTL_MIN') ?: 5);
$sendRes = create_and_send_verification_code($conn, intval($user['user_id']), $user['email'], $user['first_name'] ?? $user['email'], 'email_verify', $ttl);
if (!empty($sendRes['success'])) {
    // put pending flags in session and redirect to code entry
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['pending_2fa_user_id'] = intval($user['user_id']);
    $_SESSION['pending_2fa_token_id'] = $sendRes['id'];
    $_SESSION['pending_verification_purpose'] = 'email_verify';
    header('Location: /artine3/auth/verify.php');
    exit;
} else {
    $sent_error = 'Could not send verification code.' . (isset($sendRes['error']) ? ' ' . $sendRes['error'] : '');
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
    <?php include __DIR__ . '/../includes/simple_header.php'; ?>
    <main class="auth-content">
        <h1>Resend verification</h1>
        <div class="auth-container">
            <?php if (!empty($sent_notice)): ?>
                <div class="notice"><?php echo htmlspecialchars($sent_notice); ?></div>
            <?php elseif (!empty($sent_error)): ?>
                <div class="error-box"><ul><li><?php echo htmlspecialchars($sent_error); ?></li></ul></div>
            <?php else: ?>
                <div class="auth-forms">
                    <div class="auth-form">
                        <p>If an email address is associated with your account we attempted to send a 6-digit verification code. Please check your inbox.</p>
                        <p><a href="/artine3/account.php" target="_blank" rel="noopener noreferrer">Back to account</a></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>