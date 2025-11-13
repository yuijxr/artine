<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Return true if a user_id is set in session
 */
function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

/**
 * Require login and redirect to login page if not logged in
 */
function require_login()
{
    if (!is_logged_in()) {
        header('Location: /artine3/login.php');
        exit;
    }
}

/**
 * Require that the current session is an admin account.
 * If not logged in, redirect to login. If logged in but not admin, show 403.
 */
function require_admin()
{
    // Admin area uses a separate admin session. Do not rely on users.is_admin.
    // Require an admin session (set by admin login logic as $_SESSION['admin_id']).
    if (empty($_SESSION['admin_id'])) {
        // Redirect to admin login
        header('Location: /artine3/admin/login.php');
        exit;
    }
}

/**
 * Fetch current user row from users table using mysqli connection
 *
 * @param mysqli $conn
 * @return array|null
 */
function current_user($conn)
{
    if (!is_logged_in()) {
        return null;
    }

    $user_id = intval($_SESSION['user_id']);
    $stmt = $conn->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}
