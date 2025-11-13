<?php
require_once __DIR__ . '/../includes/session.php';
// Ensure DB connection is available
if (empty($conn) && file_exists(__DIR__ . '/../includes/db_connect.php')) {
    require_once __DIR__ . '/../includes/db_connect.php';
}

header('Content-Type: application/json; charset=utf-8');

// Only accept JSON POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || !array_key_exists('enable', $data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$enable = intval($data['enable']) ? 1 : 0;

// defensive: ensure $conn exists
if (empty($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

// users.email_2fa_enabled column assumed to exist in schema

$user_id = intval($_SESSION['user_id']);

// If disabling, update immediately and clear any pending tokens
if ($enable === 0) {
    try {
        $stmt = $conn->prepare('UPDATE users SET email_2fa_enabled = 0 WHERE user_id = ?');
        if (!$stmt) throw new Exception('DB prepare failed: ' . ($conn->error ?? ''));
        $stmt->bind_param('i', $user_id);
        if (!$stmt->execute()) throw new Exception('DB execute failed: ' . ($stmt->error ?? ''));
        $stmt->close();
    // mark any pending enable-2fa verification codes as used for this user
    try { $conn->query("UPDATE verification_codes SET used = 1 WHERE user_id = " . intval($user_id) . " AND purpose = 'enable_2fa'"); } catch (Throwable $_) {}
        echo json_encode(['success' => true, 'enabled' => false]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update preference', 'error' => $e->getMessage()]);
        exit;
    }
}

// Enabling: create a verification token, store it, and send an email
require_once __DIR__ . '/../includes/email_sender.php';

    // create and send a 6-digit code to confirm enabling 2FA
    $u = $conn->query('SELECT email, first_name, last_name FROM users WHERE user_id = ' . intval($user_id))->fetch_assoc();
    $to_email = $u['email'] ?? null;
    $to_name = trim((($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?: 'User';
    if (empty($to_email)) throw new Exception('User email not found');

    $ttl = intval(getenv('VERIFICATION_TTL_MIN') ?: 5);
    $res = create_and_send_verification_code($conn, $user_id, $to_email, $to_name, 'enable_2fa', $ttl);
    if (empty($res['success'])) throw new Exception('Failed to send verification code');

    // store pending state in session so user can enter the code on verify.php
    $_SESSION['pending_2fa_user_id'] = $user_id;
    $_SESSION['pending_2fa_token_id'] = $res['id'];
    $_SESSION['pending_verification_purpose'] = 'enable_2fa';

    echo json_encode(['success' => true, 'message' => 'Verification code sent', 'redirect' => '/artine3/auth/verify.php']);
    exit;

?>
