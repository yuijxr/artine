<?php
require_once 'includes/db_connect.php';
require_once 'includes/session.php';

$errors = [];
$registered_notice = '';

if (!empty($_GET['registered'])) {
    $registered_notice = 'Registration successful. Please log in with your new account.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } else {
        $stmt = $conn->prepare('SELECT user_id, password_hash, first_name FROM users WHERE email = ? LIMIT 1');
        if ($stmt === false) {
            $errors[] = 'Database error: ' . $conn->error;
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password_hash'])) {
                // If email verification is enabled, require verification before allowing login
                    if (isset($user['email_verified']) && !$user['email_verified']) {
                    $errors[] = 'Please verify your email before logging in. <a href="/artine3/auth/resend_verification.php">Resend verification</a>';
                } else {
                    // Successful login: update session and last_login
                    $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['first_name'] = $user['first_name'] ?? '';
                // Note: admin accounts are separate; do not set user is_admin session fallback here.
                // Update last_login safely
                try {
                    $ul = $conn->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?');
                    if ($ul) { $ul->bind_param('i', $user['user_id']); $ul->execute(); $ul->close(); }
                } catch (Exception $e) { /* ignore */ }

                    // Record a session row for session management (create or update)
                    try {
                        $sid = session_id();
                        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                        if ($sid) {
                            // create sessions table if missing (defensive)
                            $conn->query("CREATE TABLE IF NOT EXISTS sessions (
                                session_id VARCHAR(128) PRIMARY KEY,
                                user_id INT NOT NULL,
                                ip VARCHAR(45) DEFAULT NULL,
                                user_agent VARCHAR(255) DEFAULT NULL,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                            $ins = $conn->prepare('REPLACE INTO sessions (session_id,user_id,ip,user_agent,last_seen) VALUES (?,?,?,?,NOW())');
                            if ($ins) { $ins->bind_param('siss', $sid, $user['user_id'], $ip, $ua); $ins->execute(); $ins->close(); }
                        }
                    } catch (Exception $e) { /* ignore session tracking errors */ }

                // Redirect back to `next` if provided and safe, otherwise to index
                $next = trim($_GET['next'] ?? '');
                if ($next && strpos($next, '://') === false && strpos($next, '//') === false) {
                    header('Location: ' . $next);
                    exit;
                }
                header('Location: index.php?logged_in=1');
                exit;
                }
            } else {
                // Log failed login attempt (if users table exists, associate by email where possible)
                try {
                    $uid = $user['user_id'] ?? null;
                    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                    $fl = $conn->prepare('INSERT INTO failed_logins (user_id, ip) VALUES (?, ?)');
                    if ($fl) { $fl->bind_param('is', $uid, $ip); $fl->execute(); $fl->close(); }
                } catch (Exception $e) { /* ignore logging errors */ }
                $errors[] = 'Invalid email or password.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dekko&family=Devonshire&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>FitCheck | Login</title>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="auth-content">
        <h1>Login</h1>
        
        <div class="auth-container">
            <div class="auth-forms">
                <!-- Login Form -->
                <form class="auth-form active" id="loginForm" method="post">
                    <div class="form-group">
                        <label for="loginEmail">Email</label>
                        <input 
                            class="input-form" 
                            type="email" 
                            id="loginEmail" 
                            name="email"
                            placeholder="Enter your email" 
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <input 
                            class="input-form" 
                            type="password" 
                            id="loginPassword" 
                            name="password"
                            placeholder="Enter your password" 
                            required
                        >
                    </div>
                    
                    <div class="forgot-password">
                        <a href="#" onclick="return showForgotPassword()">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="auth-button">Login</button>
                    
                    <div class="switch-form">
                        <button type="button">
                            <a href="register.php">Create Account</a>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
	
    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/index.js"></script>
    <script>
        // Show server-side notices using the toast system if available
        document.addEventListener('DOMContentLoaded', () => {
            try {
                var registered = <?php echo json_encode($registered_notice ? $registered_notice : ''); ?>;
                var errors = <?php echo json_encode(!empty($errors) ? $errors : []); ?>;
                if (registered && registered.length) {
                    if (typeof showNotification === 'function') {
                        showNotification(registered, 'success');
                    }
                }
                if (errors && errors.length) {
                    // show first error as a toast and keep the HTML list for accessibility
                    if (typeof showNotification === 'function') {
                        showNotification(errors[0], 'error');
                    }
                }
            } catch (e) {
                /* ignore */
            }
        });
    </script>
</body>
</html>
