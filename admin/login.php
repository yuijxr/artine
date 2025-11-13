<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';

// If already logged in as admin, redirect to dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$errors = [];
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $errors[] = 'Username and password are required.';
    } else {
        $stmt = $conn->prepare('SELECT admin_id, username, password_hash, user_id FROM admins WHERE username = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($admin && password_verify($password, $admin['password_hash'])) {
                // set admin session
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                // optional: if admin is linked to user, also set user fallback
                if (!empty($admin['user_id'])) {
                    $_SESSION['user_id'] = intval($admin['user_id']);
                }
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'Invalid admin credentials.';
            }
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/artine3/assets/css/auth.css">
    <title>Admin Login</title>
</head>
<body>
    <main class="auth-content">
        <h1>Admin Login</h1>
        <div class="auth-container">
            <form method="post" class="auth-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" value="<?php echo htmlspecialchars($username); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <?php if (!empty($errors)): ?>
                    <div class="error-list">
                        <?php foreach ($errors as $e): ?>
                            <div class="error"><?php echo htmlspecialchars($e); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <button type="submit" class="auth-button">Login</button>
            </form>
            <p style="margin-top:12px;color:#666;font-size:14px;">Development admin credentials: <strong>artineclothing</strong> / <strong>artine123</strong></p>
        </div>
    </main>
</body>
</html>
