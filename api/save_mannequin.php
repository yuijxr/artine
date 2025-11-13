<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
	echo json_encode(['success' => false, 'message' => 'Not authenticated']);
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
		echo json_encode(['success' => false, 'message' => 'Please verify your email before using the mannequin feature']);
		exit;
	}
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
	// also accept form-encoded POST as fallback
	$data = $_POST ?? [];
}

$user_id = intval($_SESSION['user_id']);

$shoulder = isset($data['shoulder_width']) ? $data['shoulder_width'] : null;
$chest = isset($data['chest_bust']) ? $data['chest_bust'] : null;
$waist = isset($data['waist']) ? $data['waist'] : null;
$torso = isset($data['torso_length']) ? $data['torso_length'] : null;
$arm = isset($data['arm_length']) ? $data['arm_length'] : null;
$body_shape = isset($data['body_shape']) ? $data['body_shape'] : null;
$face_shape = isset($data['face_shape']) ? $data['face_shape'] : null;
$skin_tone = isset($data['skin_tone']) ? $data['skin_tone'] : null;
$base_model_url = isset($data['base_model_url']) ? $data['base_model_url'] : null;

// Update measurement fields directly on users table (merged schema)
$stmt = $conn->prepare('UPDATE users SET shoulder_width = ?, chest_bust = ?, waist = ?, torso_length = ?, arm_length = ?, body_shape = ?, face_shape = ?, skin_tone = ?, base_model_url = ?, measurements_updated_at = CURRENT_TIMESTAMP WHERE user_id = ?');
if (!$stmt) { echo json_encode(['success'=>false,'message'=>'DB prepare failed']); exit; }
$stmt->bind_param('dddddssssi', $shoulder, $chest, $waist, $torso, $arm, $body_shape, $face_shape, $skin_tone, $base_model_url, $user_id);
$ok = $stmt->execute();
$stmt->close();
if ($ok) echo json_encode(['success'=>true,'message'=>'Mannequin saved']); else echo json_encode(['success'=>false,'message'=>'Failed to save']);
exit;

