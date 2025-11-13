<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/email_sender.php';

$errors = [];
$notice = '';

// Decide view: 'forgot' shows email form, 'change' shows change-password for logged-in user
$view = $_GET['action'] ?? 'forgot';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'send_reset') {
        // Forgot password: send 6-digit reset code
        $email = trim($_POST['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email.';
        } else {
            $stmt = $conn->prepare('SELECT user_id, first_name FROM users WHERE email = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $uid = intval($row['user_id']);
                    $name = trim($row['first_name'] ?? '') ?: $email;
                    $ttl = intval(getenv('VERIFICATION_TTL_MIN') ?: 5);
                    $sendRes = create_and_send_verification_code($conn, $uid, $email, $name, 'password_reset', $ttl);
                    if (!empty($sendRes['success'])) {
                        if (session_status() === PHP_SESSION_NONE) session_start();
                        $_SESSION['pending_2fa_user_id'] = $uid;
                        $_SESSION['pending_2fa_token_id'] = $sendRes['id'];
                        $_SESSION['pending_verification_purpose'] = 'password_reset';
                        header('Location: /artine3/auth/verify.php');
                        exit;
                    }
                }
                // UX privacy: show generic notice
                $notice = 'If that email exists we sent a password reset code.';
            } else {
                $errors[] = 'Database error.';
            }
        }
    } else if ($action === 'change') {
        // Change password (user must be logged in)
        require_login();
        $user = current_user($conn);
        if (!$user) {
            header('Location: /artine3/login.php'); exit;
        }
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';
        if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $confirm) $errors[] = 'New passwords do not match.';
        if (!password_verify($old, $user['password_hash'])) $errors[] = 'Current password is incorrect.';
        if (!empty($errors)) {
            $msg = urlencode(implode(' ', $errors));
            header('Location: /artine3/account.php?change_password_error=' . $msg);
            exit;
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $up = $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        if ($up) {
            $up->bind_param('si', $hash, $user['user_id']);
            if ($up->execute()) {
                $up->close();
                header('Location: /artine3/account.php?change_password_ok=1');
                exit;
            }
        }
        header('Location: /artine3/account.php?change_password_error=' . urlencode('Unable to update password.'));
        exit;
    } else if ($action === 'reset') {
        // Password reset after successful code verification. verify.php sets $_SESSION['password_reset_user_id']
        if (session_status() === PHP_SESSION_NONE) session_start();
        $resetUid = $_SESSION['password_reset_user_id'] ?? null;
        if (!$resetUid) {
            $errors[] = 'Password reset session not found or expired. Request a new code.';
        } else {
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['new_password_confirm'] ?? '';
            if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
            if ($new !== $confirm) $errors[] = 'New passwords do not match.';
            if (empty($errors)) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $up = $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
                if ($up) {
                    $up->bind_param('si', $hash, $resetUid);
                    if ($up->execute()) {
                        $up->close();
                        unset($_SESSION['password_reset_user_id']);
                        header('Location: /artine3/login.php?password_reset=1');
                        exit;
                    }
                }
                $errors[] = 'Unable to update password.';
            }
        }
    }
}

// Render simple combined UI
?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/artine3/assets/css/auth.css">
    <link rel="stylesheet" href="/artine3/assets/css/components.css">
    <link rel="stylesheet" href="/artine3/assets/css/sty.css">
    <title>Password</title>
    <style> .auth-container{max-width:720px;margin:24px auto}</style>
</head>
<body>
    <?php include __DIR__ . '/../includes/simple_header.php'; ?>
    <main class="auth-content">
        <?php if ($view === 'change'): ?>
            <h1>Change password</h1>
            <div class="auth-container">
                <form method="post" class="auth-form">
                    <input type="hidden" name="action" value="change" />
                    <div class="form-group"><label>Current password</label><input class="input-form" type="password" name="old_password" required></div>
                    <div class="form-group"><label>New password</label><input class="input-form" type="password" name="new_password" required></div>
                    <div class="form-group"><label>Confirm new password</label><input class="input-form" type="password" name="new_password_confirm" required></div>
                    <button class="big-btn btn primary wide" type="submit">Update password</button>
                </form>
            </div>
        <?php elseif ($view === 'reset'): ?>
            <h1>Set a new password</h1>
            <div class="auth-container">
                <?php if (session_status() === PHP_SESSION_NONE) session_start();
                      $resetUid = $_SESSION['password_reset_user_id'] ?? null;
                ?>
                <?php if (!$resetUid): ?>
                    <div class="error-box"><p>Reset session not found or expired. Please request a new reset code.</p>
                    <p><a href="/artine3/auth/password.php">Request a new code</a></p></div>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <div class="error-box"><ul><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul></div>
                    <?php endif; ?>
                    <form method="post" class="auth-form">
                        <input type="hidden" name="action" value="reset" />
                        <div class="form-group"><label>New password</label><input class="input-form" type="password" name="new_password" required></div>
                        <div class="form-group"><label>Confirm new password</label><input class="input-form" type="password" name="new_password_confirm" required></div>
                        <button class="big-btn btn primary wide" type="submit">Set new password</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <h1>Reset password</h1>
            <div class="auth-container">
                <?php if (!empty($errors)): ?>
                    <div class="error-box"><ul><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul></div>
                <?php endif; ?>
                <?php if ($notice): ?>
                    <div class="notice"><?php echo htmlspecialchars($notice); ?></div>
                <?php else: ?>
                    <form method="post" class="auth-form">
                        <input type="hidden" name="action" value="send_reset" />
                        <div class="form-group"><label>Email</label><input class="input-form" type="email" name="email" required></div>
                        <button class="big-btn btn primary wide" type="submit">Send reset code</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
