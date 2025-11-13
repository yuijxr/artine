<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(null);
    exit;
}

// ensure email is verified
$uid = intval($_SESSION['user_id']);
$vstmt = $conn->prepare('SELECT email_verified FROM users WHERE user_id = ? LIMIT 1');
if ($vstmt) {
    $vstmt->bind_param('i', $uid);
    $vstmt->execute();
    $vres = $vstmt->get_result();
    $urow = $vres->fetch_assoc();
    $vstmt->close();
    if (empty($urow) || empty($urow['email_verified'])) {
        // return a structured error for client-side handling
        echo json_encode(['error' => 'unverified', 'message' => 'Please verify your email before accessing the mannequin.']);
        exit;
    }
}

$user_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare('SELECT * FROM measurements WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1');
if (!$stmt) { echo json_encode(null); exit; }
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if ($row) {
    // normalize numeric values
    $row['shoulder_width'] = $row['shoulder_width'] !== null ? floatval($row['shoulder_width']) : null;
    $row['chest_bust'] = $row['chest_bust'] !== null ? floatval($row['chest_bust']) : null;
    $row['waist'] = $row['waist'] !== null ? floatval($row['waist']) : null;
    $row['torso_length'] = $row['torso_length'] !== null ? floatval($row['torso_length']) : null;
    $row['arm_length'] = $row['arm_length'] !== null ? floatval($row['arm_length']) : null;
    echo json_encode($row);
} else {
    echo json_encode(null);
}
