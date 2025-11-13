<?php
require_once 'includes/session.php';
require_once 'includes/db_connect.php';

// Mark the current session row as logged_out in the database (if present)
if (session_status() === PHP_SESSION_ACTIVE) {
	$sid = session_id();
	if (!empty($sid) && isset($conn)) {
		try {
			$up = $conn->prepare('UPDATE sessions SET `status` = ?, logout_time = NOW() WHERE session_id = ?');
			if ($up) { $st = 'logged_out'; $up->bind_param('ss', $st, $sid); $up->execute(); $up->close(); }
		} catch (Exception $e) {
			// ignore DB errors; continue to destroy session
		}
	}

	// Unset all session variables
	$_SESSION = [];
	// Destroy session cookie if present
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params['path'], $params['domain'], $params['secure'], $params['httponly']
		);
	}
	session_destroy();
}

// redirect with a flag so landing page can show a logout notification
header('Location: index.php?logged_out=1');
exit;
