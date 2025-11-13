<?php
// Logout from all devices for the currently authenticated user.
// This removes all rows in the `sessions` table for the user (if present)
// and then destroys the current PHP session (so the browser is logged out).
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// If user is logged in, mark their sessions as logged_out (preserve rows for history)
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
if ($user_id) {
	try {
		$up = $conn->prepare('UPDATE sessions SET `status` = ?, logout_time = NOW() WHERE user_id = ?');
		if ($up) { $st = 'logged_out'; $up->bind_param('si', $st, $user_id); $up->execute(); $up->close(); }
	} catch (Exception $e) {
		// ignore DB errors - still proceed to destroy current session
	}
}

// Destroy current session (mirror behavior from /logout.php)
if (session_status() === PHP_SESSION_ACTIVE) {
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params['path'], $params['domain'], $params['secure'], $params['httponly']
		);
	}
	session_destroy();
}

// If called via POST (client-side fetch from account.js), return JSON so JS can show a toast.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	header('Content-Type: application/json');
	echo json_encode(['success' => true]);
	exit;
}

// Otherwise (e.g. direct link), redirect to index with a flag so front-end shows a notification
header('Location: ../index.php?logged_out_all=1');
exit;
