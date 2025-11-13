<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';

// require admin access
require_admin();

$admin_user = null;
if (!empty($_SESSION['admin_id'])) {
    $stmt = $conn->prepare('SELECT admin_id, username, user_id FROM admins WHERE admin_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['admin_id']);
        $stmt->execute();
        $admin_user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
// Gather dashboard metrics
$user_count = 0;
$product_count = 0;
$orders_count = 0;
$total_sales = 0.00;
$pending_orders = 0;
$low_stock_threshold = 5;
$low_stock = [];
$sales_series = [];

// total users (exclude soft-deleted)
$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM users WHERE deleted_at IS NULL');
if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $user_count = intval($r['cnt'] ?? 0); $stmt->close(); }

// total products (exclude deleted)
$stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM products WHERE deleted_at IS NULL');
if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $product_count = intval($r['cnt'] ?? 0); $stmt->close(); }

// total orders and total sales
$stmt = $conn->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS sales FROM orders');
if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $orders_count = intval($r['cnt'] ?? 0); $total_sales = floatval($r['sales'] ?? 0); $stmt->close(); }

// pending orders
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE status = 'pending'");
if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $pending_orders = intval($r['cnt'] ?? 0); $stmt->close(); }

// low-stock products
$stmt = $conn->prepare('SELECT product_id, name, stock FROM products WHERE deleted_at IS NULL AND stock <= ? ORDER BY stock ASC LIMIT 50');
if ($stmt) { $stmt->bind_param('i', $low_stock_threshold); $stmt->execute(); $res = $stmt->get_result(); while ($row = $res->fetch_assoc()) { $low_stock[] = $row; } $stmt->close(); }

// simple sales series for last 14 days
$stmt = $conn->prepare("SELECT DATE(created_at) AS d, COALESCE(SUM(total_amount),0) AS s FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC");
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); $series_map = []; while ($row = $res->fetch_assoc()) { $series_map[$row['d']] = floatval($row['s']); } $stmt->close();
    // build contiguous series for 14 days
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $sales_series[] = ['date' => $d, 'sales' => $series_map[$d] ?? 0];
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/artine3/assets/css/style.css">
    <title>Admin Dashboard</title>
</head>
<body>
    <header style="padding:16px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">
        <h2>Admin Dashboard</h2>
        <div>
            <?php if (!empty($admin_user['username'])): ?>
                <span style="margin-right:12px;">Signed in as <?php echo htmlspecialchars($admin_user['username']); ?></span>
            <?php endif; ?>
            <a href="/artine3/admin/logout.php">Logout</a>
        </div>
    </header>
    <main style="padding:20px;display:flex;gap:20px;">
        <nav style="width:220px;flex:0 0 220px;background:#fff;border:1px solid #eee;padding:12px;border-radius:6px;">
            <ul style="list-style:none;padding:0;margin:0;font-size:14px;">
                <li style="margin-bottom:8px;"><a href="/artine3/admin/index.php" style="text-decoration:none;color:#333;font-weight:700;">Dashboard</a></li>
                <li style="margin-bottom:8px;"><a href="/artine3/admin/users.php" style="text-decoration:none;color:#333;">Users</a></li>
                <li style="margin-bottom:8px;"><a href="/artine3/admin/products.php" style="text-decoration:none;color:#333;">Products</a></li>
                <li style="margin-bottom:8px;"><a href="/artine3/admin/orders.php" style="text-decoration:none;color:#333;">Orders</a></li>
                <li style="margin-bottom:8px;"><a href="/artine3/admin/settings.php" style="text-decoration:none;color:#333;">Settings</a></li>
            </ul>
        </nav>

        <section style="flex:1;">
            <h3>Overview</h3>
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:18px;">
                <a href="users.php" style="text-decoration:none;color:inherit;flex:1 1 180px;">
                    <div style="background:#fff;border:1px solid #eee;padding:12px;border-radius:6px;">
                        <div style="font-size:12px;color:#666">Customers</div>
                        <div style="font-size:24px;font-weight:700;margin-top:6px;"><?php echo number_format($user_count); ?></div>
                    </div>
                </a>

                <a href="products.php" style="text-decoration:none;color:inherit;flex:1 1 180px;">
                    <div style="background:#fff;border:1px solid #eee;padding:12px;border-radius:6px;">
                        <div style="font-size:12px;color:#666">Products</div>
                        <div style="font-size:24px;font-weight:700;margin-top:6px;"><?php echo number_format($product_count); ?></div>
                    </div>
                </a>

                <a href="orders.php" style="text-decoration:none;color:inherit;flex:1 1 180px;">
                    <div style="background:#fff;border:1px solid #eee;padding:12px;border-radius:6px;">
                        <div style="font-size:12px;color:#666">Orders</div>
                        <div style="font-size:24px;font-weight:700;margin-top:6px;"><?php echo number_format($orders_count); ?></div>
                    </div>
                </a>

                <div style="flex:1 1 220px;background:#fff;border:1px solid #eee;padding:12px;border-radius:6px;">
                    <div style="font-size:12px;color:#666">Total Sales</div>
                    <div style="font-size:24px;font-weight:700;margin-top:6px;">₱ <?php echo number_format($total_sales,2); ?></div>
                </div>

                <a href="orders.php?filter=pending" style="text-decoration:none;color:inherit;flex:1 1 180px;">
                    <div style="background:#fff;border:1px solid #ffeeba;padding:12px;border-radius:6px;">
                        <div style="font-size:12px;color:#666">Pending Orders</div>
                        <div style="font-size:24px;font-weight:700;margin-top:6px;"><?php echo number_format($pending_orders); ?></div>
                    </div>
                </a>
            </div>

            <div style="display:flex;gap:20px;align-items:flex-start;">
                <div style="flex:2 1 600px;background:#fff;border:1px solid #eee;padding:12px;border-radius:6px;">
                    <h4 style="margin-top:0">Sales (last 14 days)</h4>
                    <canvas id="salesChart" width="700" height="200" style="width:100%;height:200px;border:1px solid #fafafa;padding:6px;background:#fff"></canvas>
                </div>

                <div style="flex:1 1 320px;background:#fff;border:1px solid #eee;padding:12px;border-radius:6px;">
                    <h4 style="margin-top:0">Low-stock products (≤ <?php echo intval($low_stock_threshold); ?>)</h4>
                    <?php if (empty($low_stock)): ?>
                        <div style="color:#28a745">No low-stock products.</div>
                    <?php else: ?>
                        <table style="width:100%;border-collapse:collapse;font-size:13px;">
                            <thead>
                                <tr>
                                    <th style="text-align:left;padding:6px;border-bottom:1px solid #eee">Product</th>
                                    <th style="text-align:right;padding:6px;border-bottom:1px solid #eee">Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock as $p): ?>
                                    <tr>
                                        <td style="padding:6px;border-bottom:1px solid #fafafa;"><?php echo htmlspecialchars($p['name']); ?></td>
                                        <td style="padding:6px;border-bottom:1px solid #fafafa;text-align:right"><?php echo intval($p['stock']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            <div style="margin-top:18px;font-size:13px;color:#666">
                <a href="/artine3/admin/products.php">Manage Products</a> •
                <a href="/artine3/admin/orders.php">Manage Orders</a> •
                <a href="/artine3/admin/users.php">Manage Users</a>
            </div>
        </section>
    </main>

    <script>
        // Render simple bar chart for sales_series
        (function(){
            var series = <?php echo json_encode($sales_series); ?>;
            var labels = series.map(function(s){ return s.date.substr(5); });
            var data = series.map(function(s){ return Number(s.sales); });
            var canvas = document.getElementById('salesChart');
            if (!canvas || !canvas.getContext) return;
            var ctx = canvas.getContext('2d');
            var W = canvas.width, H = canvas.height;
            ctx.clearRect(0,0,W,H);
            var pad = 30;
            var max = Math.max.apply(null, data.concat([10]));
            var barW = (W - pad*2) / data.length * 0.8;
            data.forEach(function(v,i){
                var x = pad + i * ((W - pad*2) / data.length) + (( (W - pad*2) / data.length) - barW)/2;
                var h = max > 0 ? ( (H - pad*2) * (v / max) ) : 0;
                var y = H - pad - h;
                ctx.fillStyle = '#4a90e2';
                ctx.fillRect(x, y, barW, h);
                ctx.fillStyle = '#666';
                ctx.font = '11px Arial';
                ctx.textAlign = 'center';
                ctx.fillText(labels[i], x + barW/2, H - 8);
            });
            // Y-axis labels
            ctx.fillStyle = '#999'; ctx.font='11px Arial'; ctx.textAlign='right';
            for (var t=0;t<=4;t++){ var y = H - pad - (H - pad*2) * (t/4); var val = (max * (t/4)).toFixed(0); ctx.fillText(val, pad-6, y+4); }
        })();
    </script>
</body>
</html>
