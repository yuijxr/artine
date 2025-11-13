<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';
header('Content-Type: application/json');
// Ensure browsers do not cache order responses (avoid stale/missing entries on reload)
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Expires: 0');
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
$user_id = intval($_SESSION['user_id']);
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    // if order_id provided, return details
    $order_id = intval($_GET['order_id'] ?? 0);
    if ($order_id > 0) {
        // Return order items with a thumbnail (try product thumbnail_images JSON -> image_url)
        $stmt = $conn->prepare(
            'SELECT oi.*, p.image_url, p.thumbnail_images, c.name AS category_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id LEFT JOIN categories c ON p.category_id = c.category_id WHERE oi.order_id = ?'
        );
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $items = [];
        foreach ($rows as $r) {
            $thumb = null;
            if (!empty($r['thumbnail_images'])) {
                $tmp = json_decode($r['thumbnail_images'], true);
                if (is_array($tmp) && count($tmp) > 0) {
                    $thumb = $tmp[0];
                }
            }
            if (!$thumb && !empty($r['image_url'])) {
                $thumb = $r['image_url'];
            }
            // Build thumbnail_path using resolve_thumbnail_path helper if available
            require_once __DIR__ . '/../includes/helpers.php';
            $r['thumbnail'] = $thumb;
            if ($thumb) {
                $r['thumbnail_path'] = resolve_thumbnail_path($thumb, $r['category_name'] ?? '');
            } else {
                $r['thumbnail_path'] = resolve_thumbnail_path(null, $r['category_name'] ?? '');
            }
            $items[] = $r;
        }
        echo json_encode($items);
        exit;
    }

    // Return orders list with a representative thumbnail and a short summary
    $stmt = $conn->prepare(
        'SELECT o.order_id,o.user_id,o.total_amount,o.status,o.created_at,o.updated_at,
            (SELECT COALESCE(p.image_url, NULL) FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id LIMIT 1) AS thumbnail,
            (SELECT c.name FROM order_items oi JOIN products p ON oi.product_id = p.product_id JOIN categories c ON p.category_id = c.category_id WHERE oi.order_id = o.order_id LIMIT 1) AS category_name,
            (SELECT GROUP_CONCAT(DISTINCT oi.product_name SEPARATOR ", ") FROM order_items oi WHERE oi.order_id = o.order_id LIMIT 3) AS summary
         FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC'
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    // Build thumbnail_path using category_name similar to index.php
    foreach ($orders as &$ord) {
        $thumb = $ord['thumbnail'] ?? null;
        $cat = strtolower($ord['category_name'] ?? '');
        $folder = '';
        if (strpos($cat, 'shirt') !== false) {
            $folder = 'shirts/';
        } elseif (strpos($cat, 'cap') !== false) {
            $folder = 'caps/';
        } elseif (strpos($cat, 'perfume') !== false) {
            $folder = 'perfumes/';
        }
        require_once __DIR__ . '/../includes/helpers.php';
        if ($thumb) {
            // prefer main image resolution
            $ord['thumbnail_path'] = resolve_image_path($thumb, $ord['category_name'] ?? '');
        } else {
            $ord['thumbnail_path'] = resolve_thumbnail_path(null, $ord['category_name'] ?? '');
        }
    }
    // compute simple per-user counts so the client can show accurate counts immediately
    $counts = [
        'all' => 0,
        'pending' => 0,
        // use 'delivered' as canonical status key
        'delivered' => 0,
        'cancelled' => 0,
        'returned' => 0
    ];
    foreach ($orders as $o) {
        $s = strtolower($o['status'] ?? '');
        $counts['all'] += 1;
        if ($s === 'pending') {
            $counts['pending'] += 1;
        }
        // treat legacy 'complete' as delivered; count 'delivered' as canonical
        if ($s === 'delivered' || $s === 'complete') {
            $counts['delivered'] += 1;
        }
        if ($s === 'cancelled') {
            $counts['cancelled'] += 1;
        }
        if ($s === 'returned') {
            $counts['returned'] += 1;
        }
    }
    echo json_encode(['orders' => $orders, 'counts' => $counts]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported method']);
