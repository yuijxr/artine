<?php
require_once __DIR__ . '/../includes/session.php';
require_admin();
require_once __DIR__ . '/../includes/db_connect.php';
header('Content-Type: text/html; charset=utf-8');

// Handle AJAX POST actions: details, update_status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prefer JSON responses for AJAX
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        $order_id = intval($_POST['order_id'] ?? 0);
        $new_status = trim($_POST['status'] ?? '');
        $allowed = ['pending','confirmed','paid','shipped','delivered','cancelled','returned'];
        if ($order_id <= 0 || !in_array($new_status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
        }
        $stmt = $conn->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?');
        if (!$stmt) { echo json_encode(['success' => false, 'message' => 'DB prepare failed']); exit; }
        $stmt->bind_param('si', $new_status, $order_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Status updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        }
        $stmt->close();
        exit;
    } elseif ($action === 'details') {
        $order_id = intval($_POST['order_id'] ?? 0);
        if ($order_id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid order id']); exit; }
    $stmt = $conn->prepare('SELECT o.*, u.first_name,u.last_name,u.email,u.phone, a.full_name AS addr_full_name, a.phone AS addr_phone, a.house_number, a.barangay, a.street,a.city,a.province,a.postal_code,a.country, pm.name AS payment_method FROM orders o JOIN users u ON o.user_id = u.user_id JOIN addresses a ON o.address_id = a.address_id JOIN payment_methods pm ON o.payment_method_id = pm.method_id WHERE o.order_id = ? LIMIT 1');
        if (!$stmt) { echo json_encode(['success' => false, 'message' => 'DB prepare failed']); exit; }
        $stmt->bind_param('i', $order_id); $stmt->execute(); $ord = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$ord) { echo json_encode(['success' => false, 'message' => 'Order not found']); exit; }
        // fetch items
        $it = $conn->prepare('SELECT product_name,product_price,quantity,size,subtotal FROM order_items WHERE order_id = ?');
        $it->bind_param('i', $order_id); $it->execute(); $items = $it->get_result()->fetch_all(MYSQLI_ASSOC); $it->close();
        $ord['items'] = $items;
        echo json_encode(['success' => true, 'order' => $ord]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Unknown action']); exit;
}

// GET: render admin orders UI
$statusFilter = $_GET['status'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$q = trim($_GET['q'] ?? '');

$where = [];
$params = [];
$types = '';
if ($statusFilter) { $where[] = 'o.status = ?'; $types .= 's'; $params[] = $statusFilter; }
if ($from) { $where[] = 'o.created_at >= ?'; $types .= 's'; $params[] = $from . ' 00:00:00'; }
if ($to) { $where[] = 'o.created_at <= ?'; $types .= 's'; $params[] = $to . ' 23:59:59'; }
if ($q) { $where[] = '(o.order_id = ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)'; $types .= 'isss'; $params[] = is_numeric($q) ? intval($q) : 0; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }

$sql = 'SELECT o.order_id,o.user_id,o.total_amount,o.status,o.created_at, u.first_name,u.last_name, pm.name AS payment_method FROM orders o JOIN users u ON o.user_id = u.user_id JOIN payment_methods pm ON o.payment_method_id = pm.method_id ';
if (count($where) > 0) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY o.created_at DESC LIMIT 200';

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($types)) {
        $bind_names[] = $types;
        for ($i=0;$i<count($params);$i++) { $bind_name = 'bind' . $i; $$bind_name = $params[$i]; $bind_names[] = &$$bind_name; }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else { $orders = []; }

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Admin — Orders</title>
    <link rel="stylesheet" href="/artine3/assets/css/style.css">
    <style> .orders-table{width:100%;border-collapse:collapse} .orders-table th,.orders-table td{padding:8px;border:1px solid #e6e6e6} .status-pill{padding:4px 8px;border-radius:6px;font-weight:600} </style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; // admin nav (if exists) ?>
<main style="padding:20px;">
    <h2>Orders</h2>
    <form method="get" style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
        <label>Status:
            <select name="status">
                <option value="">All</option>
                <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
                <option value="confirmed" <?= $statusFilter==='confirmed'?'selected':'' ?>>Confirmed</option>
                <option value="paid" <?= $statusFilter==='paid'?'selected':'' ?>>Paid</option>
                <option value="shipped" <?= $statusFilter==='shipped'?'selected':'' ?>>Shipped</option>
                <option value="delivered" <?= $statusFilter==='delivered'?'selected':'' ?>>Delivered</option>
                <option value="cancelled" <?= $statusFilter==='cancelled'?'selected':'' ?>>Cancelled</option>
                <option value="returned" <?= $statusFilter==='returned'?'selected':'' ?>>Returned</option>
            </select>
        </label>
        <label>From: <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
        <label>To: <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
        <input type="text" name="q" placeholder="Order id, email or name" value="<?= htmlspecialchars($q) ?>">
        <button type="submit" class="btn">Filter</button>
    </form>

    <table class="orders-table">
        <thead><tr><th>ID</th><th>Customer</th><th>Payment</th><th>Total</th><th>Status</th><th>Placed</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr data-order-id="<?= intval($o['order_id']) ?>">
                <td>#<?= intval($o['order_id']) ?></td>
                <td><?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?></td>
                <td><?= htmlspecialchars($o['payment_method']) ?></td>
                <td>₱<?= number_format(floatval($o['total_amount']),2) ?></td>
                <td><span class="status-pill"><?= htmlspecialchars(ucfirst($o['status'])) ?></span></td>
                <td><?= htmlspecialchars($o['created_at']) ?></td>
                <td>
                    <button class="view-order-btn btn" data-id="<?= intval($o['order_id']) ?>">View</button>
                    <select class="status-select" data-id="<?= intval($o['order_id']) ?>">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="paid">Paid</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="returned">Returned</option>
                    </select>
                    <button class="update-status-btn btn" data-id="<?= intval($o['order_id']) ?>">Update</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div id="order-modal" style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);background:#fff;padding:16px;border:1px solid #ddd;max-width:900px;width:90%;max-height:80vh;overflow:auto;z-index:1000;">
        <button id="modal-close" style="float:right;">Close</button>
        <h3>Order <span id="modal-order-id"></span></h3>
        <div id="modal-content"></div>
    </div>

    <script>
    async function postAction(data){
        const fd = new FormData(); for(const k in data) fd.append(k,data[k]);
        const r = await fetch('orders.php', { method:'POST', body: fd });
        return await r.json();
    }
    document.querySelectorAll('.view-order-btn').forEach(btn=> btn.addEventListener('click', async ()=>{
        const id = btn.getAttribute('data-id');
        const res = await postAction({ action:'details', order_id: id });
        if (!res.success){ alert(res.message||'Failed'); return; }
        const o = res.order;
        document.getElementById('modal-order-id').textContent = o.order_id;
    const content = document.getElementById('modal-content');
    let html = `<p><strong>Customer:</strong> ${o.first_name} ${o.last_name} &lt;${o.email}&gt; ${o.phone ? (' / ' + o.phone) : ''}</p>`;
    // Shipping: four-line format: Name (Phone) / House No., Street / Barangay, City / Province, Postal, Country
    html += `<div><strong>Shipping:</strong></div>`;
    const shipName = o.addr_full_name ? o.addr_full_name : (o.first_name + ' ' + o.last_name);
    const shipPhone = o.addr_phone ? o.addr_phone : o.phone;
    html += `<div class="address-name">${shipName}${shipPhone ? (' <span class="address-phone">(' + shipPhone + ')</span>') : ''}</div>`;
    html += `<div class="address-details">${o.house_number ? (o.house_number + ', ') : ''}${o.street}</div>`;
    html += `<div class="address-details">${o.city}${o.barangay ? (', Barangay ' + o.barangay) : ''}</div>`;
    html += `<div class="address-details">${o.province}, ${o.postal_code}, ${o.country}</div>`;
        html += `<p><strong>Payment:</strong> ${o.payment_method}</p>`;
        html += '<h4>Items</h4><ul>';
        o.items.forEach(it=>{ html += `<li>${it.product_name} — ${it.quantity} × ₱${parseFloat(it.product_price).toFixed(2)} = ₱${parseFloat(it.subtotal).toFixed(2)}</li>`; });
        html += '</ul>';
        html += `<p><strong>Total:</strong> ₱${parseFloat(o.total_amount).toFixed(2)}</p>`;
        content.innerHTML = html;
        document.getElementById('order-modal').style.display = '';
    }));

    document.getElementById('modal-close').addEventListener('click', ()=>{ document.getElementById('order-modal').style.display = 'none'; });

    document.querySelectorAll('.update-status-btn').forEach(btn=> btn.addEventListener('click', async ()=>{
        const id = btn.getAttribute('data-id');
        const sel = document.querySelector('.status-select[data-id="'+id+'"]');
        if (!sel) return; const status = sel.value;
        const res = await postAction({ action:'update_status', order_id: id, status: status });
        if (res.success) { alert('Updated'); location.reload(); } else { alert(res.message || 'Failed to update'); }
    }));
    </script>
</main>
</body>
</html>
