<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json');
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
$user_id = intval($_SESSION['user_id']);
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $stmt = $conn->prepare(
        'SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC'
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode($res);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true) ?: [];
if ($method === 'POST') {
    // create (defaults to non-default; default is set via separate action)
    $stmt = $conn->prepare(
        'INSERT INTO addresses (user_id,full_name,phone,house_number,barangay,street,city,province,postal_code,country,is_default,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,0,NOW())'
    );
    $full_name = $data['full_name'] ?? '';
    $phone = $data['phone'] ?? '';
    $house_number = $data['house_number'] ?? '';
    $barangay = $data['barangay'] ?? '';
    $street = $data['street'] ?? '';
    $city = $data['city'] ?? '';
    $province = $data['province'] ?? '';
    $postal_code = $data['postal_code'] ?? '';
    $country = $data['country'] ?? '';
    $stmt->bind_param('isssssssss', $user_id, $full_name, $phone, $house_number, $barangay, $street, $city, $province, $postal_code, $country);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => $conn->error]);
        exit;
    }
    $address_id = $conn->insert_id;
    echo json_encode(['success' => true, 'address_id' => $address_id]);
    exit;
}
if ($method === 'PUT') {
    // update or set default
    $id = intval($data['address_id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false]);
        exit;
    }
    // if payload contains set_default flag, only set default
    if (!empty($data['set_default'])) {
        // clear others then set this one
        $clear = $conn->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?');
        $clear->bind_param('i', $user_id);
        $clear->execute();
        $clear->close();
        $s = $conn->prepare('UPDATE addresses SET is_default = 1 WHERE address_id = ? AND user_id = ?');
        $s->bind_param('ii', $id, $user_id);
        $s->execute();
        echo json_encode(['success' => true]);
        exit;
    }
    // otherwise perform a normal update (no is_default expected)
    $full_name = $data['full_name'] ?? '';
    $phone = $data['phone'] ?? '';
    $house_number = $data['house_number'] ?? '';
    $barangay = $data['barangay'] ?? '';
    $street = $data['street'] ?? '';
    $city = $data['city'] ?? '';
    $province = $data['province'] ?? '';
    $postal_code = $data['postal_code'] ?? '';
    $country = $data['country'] ?? '';
    $stmt = $conn->prepare(
        'UPDATE addresses SET full_name=?,phone=?,house_number=?,barangay=?,street=?,city=?,province=?,postal_code=?,country=? WHERE address_id=? AND user_id=?'
    );
    $stmt->bind_param('ssssssssssi', $full_name, $phone, $house_number, $barangay, $street, $city, $province, $postal_code, $country, $id, $user_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}
if ($method === 'DELETE') {
    $id = intval($data['address_id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false]);
        exit;
    }
    $stmt = $conn->prepare('DELETE FROM addresses WHERE address_id=? AND user_id=?');
    $stmt->bind_param('ii', $id, $user_id);
    if (!$stmt->execute()) {
        // foreign key error (1451) when address is referenced by orders
        $errno = $conn->errno;
        $err = $conn->error;
        if ($errno == 1451) {
            echo json_encode(['success' => false, 'message' => 'Address cannot be deleted because it is used by existing orders']);
        } else {
            echo json_encode(['success' => false, 'message' => $err]);
        }
        $stmt->close();
        exit;
    }
    if ($stmt->affected_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Address not found']);
        $stmt->close();
        exit;
    }
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported method']);
