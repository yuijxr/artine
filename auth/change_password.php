<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /artine3/account.php');
    exit;
}

require_login();
$user = current_user($conn);
if (!$user) {
    header('Location: /artine3/login.php');
    exit;
}

$old = $_POST['old_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['new_password_confirm'] ?? '';

$errors = [];
if (strlen($new) < 6) $errors[] = 'New password must be at least 6 characters.';
if ($new !== $confirm) $errors[] = 'New passwords do not match.';

if (!password_verify($old, $user['password_hash'])) {
    $errors[] = 'Current password is incorrect.';
}

if (!empty($errors)) {
    // Redirect back with error (simple approach)
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
?>