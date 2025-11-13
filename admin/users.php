<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';

// require admin access
require_admin();

// fetch users (simple table, exclude soft-deleted)
$users = [];
$stmt = $conn->prepare('SELECT user_id, first_name, last_name, email, phone, email_verified, created_at FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 500');
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $users[] = $r; }
    $stmt->close();
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/artine3/assets/css/style.css">
    <title>Admin - Users</title>
</head>
<body>
    <header style="padding:16px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">
        <h2>Admin - Users</h2>
        <div>
            <a href="/artine3/admin/logout.php">Logout</a>
        </div>
    </header>
    <main style="padding:20px;display:flex;gap:20px;">
        <nav style="width:220px;flex:0 0 220px;background:#fff;border:1px solid #eee;padding:12px;border-radius:6px;">
            <ul style="list-style:none;padding:0;margin:0;font-size:14px;">
                <li style="margin-bottom:8px;"><a href="/artine3/admin/index.php" style="text-decoration:none;color:#333;">Dashboard</a></li>
                <li style="margin-bottom:8px;"><a href="/artine3/admin/users.php" style="text-decoration:none;color:#333;font-weight:700;">Users</a></li>
                <li style="margin-bottom:8px;"><a href="/artine3/admin/products.php" style="text-decoration:none;color:#333;">Products</a></li>
                <li style="margin-bottom:8px;"><a href="/artine3/admin/orders.php" style="text-decoration:none;color:#333;">Orders</a></li>
                <li style="margin-bottom:8px;"><a href="/artine3/admin/settings.php" style="text-decoration:none;color:#333;">Settings</a></li>
            </ul>
        </nav>
        <section style="flex:1;">
            <h3>Customers</h3>
            <?php if (empty($users)): ?>
                <div>No users found.</div>
            <?php else: ?>
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:8px;border-bottom:1px solid #eee">Name</th>
                            <th style="text-align:left;padding:8px;border-bottom:1px solid #eee">Email</th>
                            <th style="text-align:left;padding:8px;border-bottom:1px solid #eee">Phone</th>
                            <th style="text-align:left;padding:8px;border-bottom:1px solid #eee">Verified</th>
                            <th style="text-align:left;padding:8px;border-bottom:1px solid #eee">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td style="padding:8px;border-bottom:1px solid #fafafa"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                <td style="padding:8px;border-bottom:1px solid #fafafa"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td style="padding:8px;border-bottom:1px solid #fafafa"><?php echo htmlspecialchars($u['phone']); ?></td>
                                <td style="padding:8px;border-bottom:1px solid #fafafa"><?php echo $u['email_verified'] ? 'Yes' : 'No'; ?></td>
                                <td style="padding:8px;border-bottom:1px solid #fafafa"><?php echo htmlspecialchars($u['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
