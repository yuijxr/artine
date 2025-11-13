<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';

// require admin access
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /artine3/admin/users.php');
    exit;
}
$action = $_POST['action'] ?? '';
// login_attempts table assumed to exist in schema

if ($action === 'unlock_all') {
    try {
        // reset attempts and locked_until for all
        $upd = $conn->prepare('UPDATE login_security SET locked_until = NULL, attempt_count = 0, lock_count_24h = 0, last_lock = NULL');
        if ($upd) { $upd->execute(); $upd->close(); }
        // history consolidated into login_security; clearing recent lock counters as part of unlock_all
    } catch (Exception $e) { }
    header('Location: /artine3/admin/users.php?unlocked=all');
    exit;
} elseif ($action === 'unlock_user') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') { header('Location: /artine3/admin/users.php?error=missing_email'); exit; }
    try {
        $upd = $conn->prepare('UPDATE login_security SET locked_until = NULL, attempt_count = 0, lock_count_24h = 0, last_lock = NULL WHERE identifier = ?');
        if ($upd) { $upd->bind_param('s', $email); $upd->execute(); $affected = $upd->affected_rows; $upd->close(); }
    } catch (Exception $e) { }
    header('Location: /artine3/admin/users.php?unlocked=user');
    exit;
} else {
    header('Location: /artine3/admin/users.php');
    exit;
}
