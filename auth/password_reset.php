<?php
require_once __DIR__ . '/../includes/db_connect.php';

$uid = intval($_GET['uid'] ?? 0);
$token = $_GET['token'] ?? '';
$message = '';
$allow_reset = false;

if ($uid && $token) {
    // First, try password_resets (hashed tokens)
    $stmt = $conn->prepare('SELECT reset_id, token_hash, expires_at, used FROM password_resets WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $found = null;
        foreach ($rows as $r) {
            if ($r['used']) continue;
            if (strtotime($r['expires_at']) < time()) continue;
            if (password_verify($token, $r['token_hash'])) {
                $found = $r;
                break;
            }
        }
        if ($found) {
            $allow_reset = true;
            $reset_id = intval($found['reset_id']);
        }
    }

    // If not found in password_resets, allow using users.verification_token (plain token)
    if (!$allow_reset) {
        $ustmt = $conn->prepare('SELECT user_id FROM users WHERE user_id = ? AND verification_token = ? LIMIT 1');
        if ($ustmt) {
            $ustmt->bind_param('is', $uid, $token);
            $ustmt->execute();
            $urow = $ustmt->get_result()->fetch_assoc();
            $ustmt->close();
            if ($urow) {
                $allow_reset = true;
                $use_verification_token = true;
            }
        }
    }

    if (!$allow_reset) {
        $message = 'Invalid or expired token.';
    }
} else {
    $message = 'Invalid request.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allow_reset) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $errors = [];
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password_confirm) $errors[] = 'Passwords do not match.';
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $up = $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        if ($up) {
            $up->bind_param('si', $hash, $uid);
            $up->execute();
            $up->close();
            // mark token used: either in password_resets or clear verification_token
            if (!empty($reset_id)) {
                $mu = $conn->prepare('UPDATE password_resets SET used = 1 WHERE reset_id = ?');
                if ($mu) { $mu->bind_param('i', $reset_id); $mu->execute(); $mu->close(); }
            }
            if (!empty($use_verification_token)) {
                $cle = $conn->prepare('UPDATE users SET verification_token = NULL WHERE user_id = ?');
                if ($cle) { $cle->bind_param('i', $uid); $cle->execute(); $cle->close(); }
            }
            $message = 'Password reset successfully. You may now log in.';
            $allow_reset = false;
        } else {
            $message = 'Database error while updating password.';
        }
    } else {
        $message = implode(' ', $errors);
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/artine3/assets/css/auth.css">
    <title>Set new password</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main class="auth-content">
        <h1>Set new password</h1>
        <div class="auth-container">
            <?php if ($message): ?>
                <div class="notice"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($allow_reset): ?>
                <form method="post" class="auth-form">
                    <div class="form-group">
                        <label for="password">New password</label>
                        <input class="input-form" id="password" name="password" type="password" required>
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Confirm password</label>
                        <input class="input-form" id="password_confirm" name="password_confirm" type="password" required>
                    </div>
                    <button class="auth-button" type="submit">Set password</button>
                </form>
            <?php else: ?>
                <p><a href="/artine3/auth/forgot_password.php">Request a new reset link</a></p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>