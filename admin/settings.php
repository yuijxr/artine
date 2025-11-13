<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';

require_admin();

$admin_user = null;
if (!empty($_SESSION['admin_id'])) {
    $stmt = $conn->prepare('SELECT admin_id, username FROM admins WHERE admin_id = ? LIMIT 1');
    if ($stmt) { $stmt->bind_param('i', $_SESSION['admin_id']); $stmt->execute(); $admin_user = $stmt->get_result()->fetch_assoc(); $stmt->close(); }
}

$settings_file = __DIR__ . '/../includes/site_settings.json';
$settings = [];
if (file_exists($settings_file)) {
    $raw = file_get_contents($settings_file);
    $settings = json_decode($raw, true) ?: [];
}

$error = '';
$section = $_POST['section'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $section) {
    // require admin password confirmation before applying changes
    $admin_password = $_POST['admin_password'] ?? '';
    if (empty($admin_password)) {
        $error = 'Please confirm your admin password to save changes.';
    } else {
        $pwStmt = $conn->prepare('SELECT password_hash FROM admins WHERE admin_id = ? LIMIT 1');
        $pwRow = null;
        if ($pwStmt) { $pwStmt->bind_param('i', $_SESSION['admin_id']); $pwStmt->execute(); $pwRow = $pwStmt->get_result()->fetch_assoc(); $pwStmt->close(); }
        if (!$pwRow || !function_exists('password_verify') || !password_verify($admin_password, $pwRow['password_hash'])) {
            $error = 'Invalid admin password.';
        }
    }

    // sanitize and merge incoming values depending on section
    $updates = [];
    if (empty($error)) {
    if ($section === 'account') {
        $updates['account'] = [
            'username' => trim($_POST['username'] ?? ''),
            'last_login_ip_display' => isset($_POST['last_login_ip_display']) ? boolval($_POST['last_login_ip_display']) : (bool)($settings['account']['last_login_ip_display'] ?? true),
        ];
        // change password handled separately by admin users page or auth endpoint
    } elseif ($section === 'email') {
        $updates['email'] = [
            'smtp_host' => trim($_POST['smtp_host'] ?? ''),
            'smtp_port' => intval($_POST['smtp_port'] ?? 587),
            'smtp_user' => trim($_POST['smtp_user'] ?? ''),
            'smtp_pass' => trim($_POST['smtp_pass'] ?? ''),
            'from_name' => trim($_POST['from_name'] ?? ''),
            'from_email' => trim($_POST['from_email'] ?? ''),
            'notify_admin_new_orders' => isset($_POST['notify_admin_new_orders']) ? 1 : 0,
        ];
    } elseif ($section === 'store') {
        $updates['store'] = [
            'name' => trim($_POST['store_name'] ?? ''),
            'tagline' => trim($_POST['store_tagline'] ?? ''),
            'contact_email' => trim($_POST['store_email'] ?? ''),
            'contact_number' => trim($_POST['store_phone'] ?? ''),
            'address' => trim($_POST['store_address'] ?? ''),
            'business_hours' => trim($_POST['business_hours'] ?? ''),
            'social_links' => array_filter(array_map('trim', (array)($_POST['social_links'] ?? []))),
        ];
        // logo upload
        if (!empty($_FILES['store_logo']) && $_FILES['store_logo']['error'] === UPLOAD_ERR_OK) {
            $up = $_FILES['store_logo'];
            $ext = pathinfo($up['name'], PATHINFO_EXTENSION);
            $fn = 'logo_' . time() . '.' . $ext;
            $destDir = __DIR__ . '/../uploads/site/';
            if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
            $target = $destDir . $fn;
            if (@move_uploaded_file($up['tmp_name'], $target)) {
                $updates['store']['logo'] = 'uploads/site/' . $fn;
            }
        }
    } elseif ($section === 'security') {
        $updates['security'] = [
            'password_policy' => trim($_POST['password_policy'] ?? ''),
            'min_password_length' => intval($_POST['min_password_length'] ?? 8),
            'require_symbol' => isset($_POST['require_symbol']) ? 1 : 0,
            'limit_login_attempts' => isset($_POST['limit_login_attempts']) ? 1 : 0,
            'lockout_threshold' => intval($_POST['lockout_threshold'] ?? 3),
            'enable_ip_logging' => isset($_POST['enable_ip_logging']) ? 1 : 0,
            'session_timeout_minutes' => intval($_POST['session_timeout_minutes'] ?? 60),
            'privacy_policy' => trim($_POST['privacy_policy'] ?? ''),
            'terms_of_service' => trim($_POST['terms_of_service'] ?? ''),
            'refund_policy' => trim($_POST['refund_policy'] ?? ''),
        ];
    }

        // merge and persist
        $settings = array_merge($settings, $updates);
        file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

        // log admin action to admin_actions table using the project's schema
        if (!empty($_SESSION['admin_id'])) {
            $action_type = 'updated_settings_' . $section;
            $target_table = 'site_settings';
            $details = json_encode($updates);
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $istmt = $conn->prepare('INSERT INTO admin_actions (admin_id, action_type, target_table, details, ip_address) VALUES (?, ?, ?, ?, ?)');
            if ($istmt) { $istmt->bind_param('issss', $_SESSION['admin_id'], $action_type, $target_table, $details, $ip); $istmt->execute(); $istmt->close(); }
        }

        // redirect back with success
        header('Location: settings.php?saved=1&section=' . urlencode($section));
        exit();
    }
}

// Read recent admin actions for Admin Log panel
$logs = [];
$stmt = $conn->prepare('SELECT aa.*, a.username FROM admin_actions aa LEFT JOIN admins a ON aa.admin_id = a.admin_id ORDER BY aa.created_at DESC LIMIT 200');
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); while ($r = $res->fetch_assoc()) $logs[] = $r; $stmt->close(); }
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/artine3/assets/css/style.css">
    <title>Admin Settings</title>
    <style>
        .settings-grid { display:flex; gap:20px; }
        .settings-left { width:240px; }
        .settings-panel { background:#fff;border:1px solid #eee;padding:12px;border-radius:6px; }
        .settings-row { margin-bottom:10px; }
        .settings-label { display:block;font-weight:600;margin-bottom:6px; }
        textarea.mono { font-family:monospace; font-size:13px; width:100%; min-height:120px; }
        table.admin-log { width:100%; border-collapse:collapse; }
        table.admin-log th, table.admin-log td { padding:6px; border-bottom:1px solid #f1f1f1; font-size:13px; }
    </style>
</head>
<body>
    <header style="padding:12px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">
        <h2>Admin Settings</h2>
        <div>
            <?php if (!empty($admin_user['username'])): ?>
                <span style="margin-right:12px;">Signed in as <?php echo htmlspecialchars($admin_user['username']); ?></span>
            <?php endif; ?>
            <a href="/artine3/admin/logout.php">Logout</a>
        </div>
    </header>
    <main style="padding:18px;">
        <div style="display:flex;gap:20px;">
            <?php include __DIR__ . '/_nav.php'; ?>
            <div style="flex:1;">
                <?php if (!empty($error)): ?>
                    <div style="padding:10px;background:#ffecec;border:1px solid #f5c2c2;margin-bottom:12px;border-radius:6px;color:#900"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($_GET['saved'])): ?>
                    <div style="padding:10px;background:#e6ffed;border:1px solid #bfe7c9;margin-bottom:12px;border-radius:6px;">Settings saved.</div>
                <?php endif; ?>

                <div class="settings-grid">
                    <div class="settings-left">
                        <div class="settings-panel">
                            <h4>Sections</h4>
                            <ul style="list-style:none;padding:0;margin:0;font-size:14px;">
                                <li><a href="#account">Account Settings</a></li>
                                <li><a href="#email">Email Settings</a></li>
                                <li><a href="#store">Store & Appearance</a></li>
                                <li><a href="#security">Security & Privacy</a></li>
                                <li><a href="#adminlog">Admin Log</a></li>
                            </ul>
                        </div>
                    </div>

                    <div style="flex:1;">
                        <!-- Account Settings -->
                        <section id="account" class="settings-panel" style="margin-bottom:12px;">
                            <h3>Account Settings</h3>
                            <form method="post">
                                <input type="hidden" name="section" value="account">
                                <div class="settings-row">
                                    <label class="settings-label">Admin username (display)</label>
                                    <input name="username" value="<?php echo htmlspecialchars($settings['account']['username'] ?? ($admin_user['username'] ?? '')); ?>" style="width:100%;padding:8px;" />
                                </div>
                                <div class="settings-row">
                                    <label><input type="checkbox" name="last_login_ip_display" <?php echo !empty($settings['account']['last_login_ip_display']) ? 'checked' : ''; ?>> Show last login & IP to admins</label>
                                </div>
                                <div class="settings-row"><label class="settings-label">Confirm admin password</label><input type="password" name="admin_password" required style="width:100%;padding:8px;" /></div>
                                <div style="text-align:right;"><button class="btn primary" type="submit">Save Account Settings</button></div>
                            </form>
                        </section>

                        <!-- Email Settings -->
                        <section id="email" class="settings-panel" style="margin-bottom:12px;">
                            <h3>Email Settings</h3>
                            <form method="post">
                                <input type="hidden" name="section" value="email">
                                <div class="settings-row"><label class="settings-label">SMTP Host</label><input name="smtp_host" value="<?php echo htmlspecialchars($settings['email']['smtp_host'] ?? ''); ?>" style="width:100%;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">SMTP Port</label><input name="smtp_port" value="<?php echo intval($settings['email']['smtp_port'] ?? 587); ?>" style="width:120px;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">SMTP Username</label><input name="smtp_user" value="<?php echo htmlspecialchars($settings['email']['smtp_user'] ?? ''); ?>" style="width:100%;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">SMTP Password / App Password</label><input name="smtp_pass" value="<?php echo htmlspecialchars($settings['email']['smtp_pass'] ?? ''); ?>" style="width:100%;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">From Name</label><input name="from_name" value="<?php echo htmlspecialchars($settings['email']['from_name'] ?? ''); ?>" style="width:100%;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">From Email</label><input name="from_email" value="<?php echo htmlspecialchars($settings['email']['from_email'] ?? ''); ?>" style="width:100%;padding:8px;" /></div>
                                <div class="settings-row"><label><input type="checkbox" name="notify_admin_new_orders" <?php echo !empty($settings['email']['notify_admin_new_orders']) ? 'checked' : ''; ?>> Notify admin for new orders</label></div>
                                <div class="settings-row"><label class="settings-label">Confirm admin password</label><input type="password" name="admin_password" required style="width:100%;padding:8px;" /></div>
                                <div style="display:flex;gap:8px;justify-content:flex-end;"><button class="btn" type="button" onclick="location.href='settings.php#email'">Cancel</button><button class="btn primary" type="submit">Save Email Settings</button></div>
                            </form>
                        </section>

                        <!-- Store Info -->
                        <section id="store" class="settings-panel" style="margin-bottom:12px;">
                            <h3>Store Information & Appearance</h3>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="section" value="store">
                                <div class="settings-row"><label class="settings-label">Store Name</label><input name="store_name" value="<?php echo htmlspecialchars($settings['store']['name'] ?? ''); ?>" style="width:100%;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">Store Tagline / Description</label><input name="store_tagline" value="<?php echo htmlspecialchars($settings['store']['tagline'] ?? ''); ?>" style="width:100%;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">Contact Email</label><input name="store_email" value="<?php echo htmlspecialchars($settings['store']['contact_email'] ?? ''); ?>" style="width:100%;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">Contact Number</label><input name="store_phone" value="<?php echo htmlspecialchars($settings['store']['contact_number'] ?? ''); ?>" style="width:100%;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">Store Address</label><input name="store_address" value="<?php echo htmlspecialchars($settings['store']['address'] ?? ''); ?>" style="width:100%;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">Business Hours</label><input name="business_hours" value="<?php echo htmlspecialchars($settings['store']['business_hours'] ?? ''); ?>" style="width:100%;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">Social Links (one per line)</label><textarea name="social_links[]" style="width:100%;min-height:80px;padding:8px;"><?php echo htmlspecialchars(implode("\n", $settings['store']['social_links'] ?? [])); ?></textarea></div>
                                <div class="settings-row"><label class="settings-label">Upload / Change Logo</label><input type="file" name="store_logo" accept="image/*" /></div>
                                <div class="settings-row"><label class="settings-label">Confirm admin password</label><input type="password" name="admin_password" required style="width:100%;padding:8px;" /></div>
                                <div style="text-align:right;"><button class="btn primary" type="submit">Save Store Settings</button></div>
                            </form>
                        </section>

                        <!-- Security & Privacy -->
                        <section id="security" class="settings-panel" style="margin-bottom:12px;">
                            <h3>Security & Privacy</h3>
                            <form method="post">
                                <input type="hidden" name="section" value="security">
                                <div class="settings-row"><label class="settings-label">Password Policy (text)</label><input name="password_policy" value="<?php echo htmlspecialchars($settings['security']['password_policy'] ?? 'Minimum 8 characters, include a symbol'); ?>" style="width:100%;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">Minimum Password Length</label><input name="min_password_length" value="<?php echo intval($settings['security']['min_password_length'] ?? 8); ?>" style="width:120px;padding:8px;" /></div>
                                <div class="settings-row"><label><input type="checkbox" name="require_symbol" <?php echo !empty($settings['security']['require_symbol']) ? 'checked' : ''; ?>> Require symbol in password</label></div>
                                <div class="settings-row"><label><input type="checkbox" name="limit_login_attempts" <?php echo !empty($settings['security']['limit_login_attempts']) ? 'checked' : ''; ?>> Limit login attempts</label></div>
                                <div class="settings-row"><label class="settings-label">Lockout threshold (failed attempts)</label><input name="lockout_threshold" value="<?php echo intval($settings['security']['lockout_threshold'] ?? 3); ?>" style="width:120px;padding:8px;" /></div>
                                <div class="settings-row"><label><input type="checkbox" name="enable_ip_logging" <?php echo !empty($settings['security']['enable_ip_logging']) ? 'checked' : ''; ?>> Enable IP logging</label></div>
                                <div class="settings-row"><label class="settings-label">Session timeout (minutes)</label><input name="session_timeout_minutes" value="<?php echo intval($settings['security']['session_timeout_minutes'] ?? 60); ?>" style="width:120px;padding:8px;" /></div>
                                <div class="settings-row"><label class="settings-label">Privacy Policy (HTML/text)</label><textarea name="privacy_policy" class="mono"><?php echo htmlspecialchars($settings['security']['privacy_policy'] ?? ''); ?></textarea></div>
                                <div class="settings-row"><label class="settings-label">Terms of Service</label><textarea name="terms_of_service" class="mono"><?php echo htmlspecialchars($settings['security']['terms_of_service'] ?? ''); ?></textarea></div>
                                <div class="settings-row"><label class="settings-label">Refund Policy</label><textarea name="refund_policy" class="mono"><?php echo htmlspecialchars($settings['security']['refund_policy'] ?? ''); ?></textarea></div>
                                <div class="settings-row"><label class="settings-label">Confirm admin password</label><input type="password" name="admin_password" required style="width:100%;padding:8px;" /></div>
                                <div style="text-align:right;"><button class="btn primary" type="submit">Save Security Settings</button></div>
                            </form>
                        </section>

                        <!-- Admin Log -->
                        <section id="adminlog" class="settings-panel" style="margin-bottom:12px;">
                            <h3>Admin Activity Log</h3>
                            <p style="color:#666;font-size:13px">Recent admin actions (from `admin_actions` table)</p>
                            <div style="max-height:420px;overflow:auto;margin-top:8px;">
                                        <table class="admin-log">
                                            <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($logs as $l): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($l['created_at']); ?></td>
                                                    <td><?php echo htmlspecialchars($l['username'] ?? ('#' . intval($l['admin_id']))); ?></td>
                                                    <td><?php echo htmlspecialchars($l['action_type'] ?? ($l['action'] ?? '')); ?></td>
                                                    <td><?php echo htmlspecialchars(($l['target_table'] ?? '') . (!empty($l['target_id']) ? '#' . intval($l['target_id']) : '')); ?></td>
                                                    <td><pre style="white-space:pre-wrap;margin:0;"><?php echo htmlspecialchars($l['details'] ?? ($l['meta'] ?? '')); ?></pre></td>
                                                    <td><?php echo htmlspecialchars($l['ip_address'] ?? ''); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
