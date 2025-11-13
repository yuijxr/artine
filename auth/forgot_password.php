<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/email_sender.php';

$errors = [];
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email.';
    } else {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $uid = intval($row['user_id']);
                // create a short-lived token and expiry similar to verification flow
                $token = bin2hex(random_bytes(16));
                $ttl = intval(getenv('VERIFICATION_TTL_MIN') ?: 60);
                $verification_expires = date('Y-m-d H:i:s', time() + ($ttl * 60));

                // ensure column exists before updating (older DBs may not have the column)
                $col = $conn->query("SHOW COLUMNS FROM users LIKE 'verification_expires'");
                if (!$col || $col->num_rows === 0) {
                    $conn->query("ALTER TABLE users ADD COLUMN verification_expires TIMESTAMP NULL DEFAULT NULL");
                }

                // store token on users table (we reuse verification_token field for simplicity)
                $up = $conn->prepare('UPDATE users SET verification_token = ?, verification_expires = ? WHERE user_id = ?');
                if ($up) {
                    $up->bind_param('ssi', $token, $verification_expires, $uid);
                    $ok = $up->execute();
                    $up->close();
                    if ($ok) {
                        // send reset link by email (do not reveal existence in UI regardless of success)
                        // we don't assume we have a first name; use the email as friendly name fallback
                        $name = $row['first_name'] ?? $email;
                        $sendRes = send_password_reset_email($email, $name, $uid, $token);
                        // keep UX private: always show the same notice to caller
                        $sent = true;
                    } else {
                        // on DB failure, treat as generic (do not reveal email existence)
                        $sent = true;
                    }
                } else {
                    $errors[] = 'Database error while creating reset token.';
                }
            } else {
                // do not reveal whether email exists
                $sent = true;
            }
        } else {
            $errors[] = 'Database error.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/artine3/assets/css/auth.css">
    <link rel="stylesheet" href="/artine3/assets/css/components.css">
    <title>Password reset</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main class="auth-content">
        <h1>Reset password</h1>
        <div class="auth-container">
            <?php if (!empty($errors)): ?>
                <div class="error-box"><ul><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul></div>
            <?php endif; ?>
            <?php if ($sent === true): ?>
                <div class="notice">If that email exists we sent a reset link.</div>
            <?php elseif ($sent): ?>
                <div class="notice">Reset link (development): <a href="<?php echo htmlspecialchars($sent); ?>"><?php echo htmlspecialchars($sent); ?></a></div>
            <?php else: ?>
                <form method="post" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input class="input-form" id="email" name="email" type="email" required>
                    </div>
                    <button class="auth-button" type="submit">Send reset link</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>