<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json');
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
$user_id = intval($_SESSION['user_id']);
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$name = trim($data['name'] ?? '');
$phone = trim($data['phone'] ?? '');
if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit;
}

// Validate phone (if provided) must be 11 digits
if ($phone !== '' && !preg_match('/^\d{11}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number must be 11 digits']);
    exit;
}

// Split name into first and last (best-effort)
$parts = preg_split('/\s+/', $name);
$first = $parts[0] ?? '';
$last = '';
if (count($parts) > 1) {
    $last = array_pop($parts);
    // remaining parts (if any) form the first name(s)
    $first = implode(' ', $parts);
}

// Update users table
$stmt = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param('sssi', $first, $last, $phone, $user_id);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}
$stmt->close();

// Also update saved addresses so they reflect the current account name/phone
$u = $conn->prepare('UPDATE addresses SET full_name = ?, phone = ? WHERE user_id = ?');
if ($u) {
    $u->bind_param('ssi', $name, $phone, $user_id);
    $u->execute();
    $u->close();
}

echo json_encode(['success' => true, 'message' => 'Profile updated']);
exit;
