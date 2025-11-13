<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json');
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
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
        echo json_encode(['success' => false, 'message' => 'Please verify your email before checking out.']);
        exit;
    }
}
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$user_id = intval($_SESSION['user_id']);
$address_id = intval($data['address_id'] ?? 0);
$payment_method_id = intval($data['payment_method_id'] ?? 0);
if ($address_id <= 0 || $payment_method_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Address and payment method required']);
    exit;
}

// fetch cart items for user
$stmt = $conn->prepare(
    'SELECT c.product_id, c.quantity, c.size, p.price, p.name FROM cart c JOIN products p ON c.product_id = p.product_id WHERE c.user_id = ?'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$items = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    exit;
}

// calculate totals (prices are tax-inclusive). Total should be subtotal + shipping.
$subtotal = 0.0;
foreach ($items as $it) {
    $subtotal += floatval($it['price']) * intval($it['quantity']);
}
$shipping = 49.00;
$total = round($subtotal + $shipping, 2);

// place order in transaction
try {
    $conn->begin_transaction();
    $ins = $conn->prepare(
        'INSERT INTO orders (user_id,address_id,payment_method_id,total_amount,status,created_at) VALUES (?,?,?,?,?,NOW())'
    );
    $status = 'pending';
    $ins->bind_param('iiids', $user_id, $address_id, $payment_method_id, $total, $status);
    if (!$ins->execute()) {
        throw new Exception('Failed to insert order: ' . $conn->error);
    }
    $order_id = $conn->insert_id;
    $ins->close();

    $itemStmt = $conn->prepare(
        'INSERT INTO order_items (order_id,product_id,product_name,product_price,quantity,size,subtotal) VALUES (?,?,?,?,?,?,?)'
    );
    if (!$itemStmt) {
        throw new Exception('Prepare order_items failed: ' . $conn->error);
    }
    foreach ($items as $it) {
        $pid = intval($it['product_id']);
        $pname = $it['name'];
        $pprice = round(floatval($it['price']), 2);
        $pqty = intval($it['quantity']);
        $psize = $it['size'] ?? '';
        $sub = round($pprice * $pqty, 2);
        // types: i (order_id), i (product_id), s (product_name), d (product_price), i (quantity), s (size), d (subtotal)
        if (!$itemStmt->bind_param('iisdisd', $order_id, $pid, $pname, $pprice, $pqty, $psize, $sub)) {
            throw new Exception('Bind failed for order_items: ' . $itemStmt->error);
        }
        if (!$itemStmt->execute()) {
            throw new Exception('Insert order_item failed: ' . $itemStmt->error);
        }
    }
    $itemStmt->close();

    // empty cart
    $del = $conn->prepare('DELETE FROM cart WHERE user_id = ?');
    $del->bind_param('i', $user_id);
    $del->execute();
    $del->close();

    $conn->commit();
    echo json_encode(['success' => true, 'order_id' => $order_id, 'message' => 'Order placed']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Order failed: ' . $e->getMessage()]);
}
