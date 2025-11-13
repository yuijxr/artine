<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$uid = intval($_SESSION['user_id']);
$out = ['success' => true, 'sessions' => []];
try {
    $stmt = $conn->prepare('SELECT session_id, ip, user_agent, last_seen, created_at, `status`, logout_time FROM sessions WHERE user_id = ? ORDER BY last_seen DESC');
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $out['sessions'][] = $r;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
}

echo json_encode($out);
