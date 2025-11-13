<?php
require_once 'includes/db_connect.php';
require_once 'includes/session.php';
require_once __DIR__ . '/includes/email_sender.php';

$errors = [];
$registered_notice = '';
$lock_remaining_seconds = 0;
$max_login_attempts = 5;
$attempts_remaining = null;
$attempt_window_min = 30; // failed attempts decay window

if (!empty($_GET['registered'])) {
    $registered_notice = 'Registration successful. Please log in with your new account.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } else {
        // --- Check login security (attempts & locks) ---
        try {
            $lk = $conn->prepare('SELECT attempt_count, last_attempt, locked_until, lock_count_24h, last_lock 
                                  FROM login_security WHERE identifier = ? LIMIT 1');
            if ($lk) {
                $lk->bind_param('s', $email);
                $lk->execute();
                $r = $lk->get_result()->fetch_assoc();
                $lk->close();

                $currentAttempts = 0;
                if ($r) {
                    // Only count attempts in the last $attempt_window_min minutes
                    $lastAttemptTs = strtotime($r['last_attempt'] ?? '1970-01-01 00:00:00');
                    if ($lastAttemptTs >= time() - ($attempt_window_min * 60)) {
                        $currentAttempts = intval($r['attempt_count']);
                    }
                    // Check if user is currently locked
                    if (!empty($r['locked_until']) && strtotime($r['locked_until']) > time()) {
                        $lock_remaining_seconds = strtotime($r['locked_until']) - time();
                        $attempts_remaining = null;
                    } else {
                        $attempts_remaining = max(0, $max_login_attempts - $currentAttempts);
                    }
                } else {
                    $attempts_remaining = $max_login_attempts;
                }
            }
        } catch (Exception $_) { }

        // --- Handle active lock ---
        if ($lock_remaining_seconds > 0) {
            $m = intval($lock_remaining_seconds / 60);
            $s = $lock_remaining_seconds % 60;
            $errors[] = "Too many login attempts. Please wait {$m}m {$s}s before trying again.";
        } else {
            // --- Validate user credentials ---
            $stmt = $conn->prepare('SELECT user_id, password_hash, first_name, email, email_2fa_enabled, email_verified 
                                    FROM users WHERE email = ? LIMIT 1');
            if ($stmt === false) {
                $errors[] = 'Database error: ' . $conn->error;
            } else {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // --- Successful login: clear attempts ---
                    try {
                        $cl = $conn->prepare('DELETE FROM login_security WHERE identifier = ?');
                        if ($cl) { $cl->bind_param('s', $email); $cl->execute(); $cl->close(); }
                    } catch (Exception $_) { }

                    // --- Email verification check ---
                    if (!$user['email_verified']) {
                        // Don't show attempt remaining when the user needs to verify their email
                        $attempts_remaining = null;
                        // Use verify.php with an email query so the verify handler can resend a code for that address
                        $link = '/artine3/auth/verify.php?email=' . rawurlencode($email);
                        $errors[] = 'Please verify your email before logging in. <a href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener noreferrer">Resend verification email</a>';
                    } else {
                        // --- Handle 2FA ---
                        if (!empty($user['email_2fa_enabled'])) {
                            try {
                                $ttl2 = intval(getenv('VERIFICATION_TTL_MIN') ?: 5);
                                $res = create_and_send_verification_code(
                                    $conn,
                                    intval($user['user_id']),
                                    $user['email'],
                                    trim($user['first_name'] ?? 'User'),
                                    'login_2fa',
                                    $ttl2
                                );
                                if (!empty($res['success'])) {
                                    $_SESSION['pending_2fa_user_id'] = $user['user_id'];
                                    $_SESSION['pending_2fa_token_id'] = $res['id'];
                                    $_SESSION['pending_verification_purpose'] = 'login_2fa';
                                    header('Location: auth/verify.php');
                                    exit;
                                } else {
                                    $errors[] = 'Failed to send verification code. Please try again.';
                                }
                            } catch (Exception $_) {
                                $errors[] = 'Failed to send verification code. Please try again.';
                            }
                        }

                        // --- Complete login ---
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['first_name'] = $user['first_name'] ?? '';

                        // --- Update last_login ---
                        try {
                            $sid = session_id();
                            $prevCreated = null;
                            if ($sid) {
                                $ps = $conn->prepare('SELECT created_at FROM sessions WHERE user_id = ? AND session_id != ? ORDER BY created_at DESC LIMIT 1');
                                if ($ps) {
                                    $ps->bind_param('is', $user['user_id'], $sid);
                                    $ps->execute();
                                    $r = $ps->get_result()->fetch_assoc();
                                    if (!empty($r['created_at'])) $prevCreated = $r['created_at'];
                                    $ps->close();
                                }
                            }
                            $ul = $conn->prepare('UPDATE users SET last_login = ? WHERE user_id = ?');
                            if ($ul) { 
                                $lastLoginVal = $prevCreated ?? date('Y-m-d H:i:s'); 
                                $ul->bind_param('si', $lastLoginVal, $user['user_id']); 
                                $ul->execute(); 
                                $ul->close(); 
                            }
                        } catch (Exception $e) { }

                        // --- Record session ---
                        try {
                            $sid = session_id();
                            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                            if ($sid) {
                                $ins = $conn->prepare('REPLACE INTO sessions (session_id,user_id,ip,user_agent,last_seen,`status`,logout_time) VALUES (?,?,?,?,NOW(),?,NULL)');
                                if ($ins) { 
                                    $statusVal = 'active'; 
                                    $ins->bind_param('sisss', $sid, $user['user_id'], $ip, $ua, $statusVal); 
                                    $ins->execute(); 
                                    $ins->close(); 
                                }
                            }
                        } catch (Exception $e) { }

                        // --- Redirect ---
                        $next = trim($_GET['next'] ?? '');
                        if ($next && strpos($next, '://') === false && strpos($next, '//') === false) {
                            header('Location: ' . $next);
                            exit;
                        }
                        header('Location: index.php?logged_in=1');
                        exit;
                    }
                } else {
                    // --- Failed login attempt ---
                    try {
                        $uid = $user['user_id'] ?? null;
                        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

                        // Insert/update with decay: reset attempts if last attempt > $attempt_window_min
                        $ins = $conn->prepare('INSERT INTO login_security (identifier, ip, user_id, attempt_count, last_attempt) 
                                                VALUES (?, ?, ?, 1, NOW()) 
                                                ON DUPLICATE KEY UPDATE attempt_count = IF(last_attempt >= NOW() - INTERVAL ? MINUTE, attempt_count + 1, 1), 
                                                last_attempt = NOW()');
                        if ($ins) { $ins->bind_param('sisi', $email, $ip, $uid, $attempt_window_min); $ins->execute(); $ins->close(); }

                        // --- Lock logic ---
                        $chk = $conn->prepare('SELECT attempt_count, last_lock, lock_count_24h FROM login_security WHERE identifier = ? LIMIT 1');
                        if ($chk) {
                            $chk->bind_param('s', $email);
                            $chk->execute();
                            $r = $chk->get_result()->fetch_assoc();
                            $chk->close();

                            $recentAttempts = intval($r['attempt_count'] ?? 0);
                            $lockCount24h = intval($r['lock_count_24h'] ?? 0);

                            if ($recentAttempts >= $max_login_attempts) {
                                // Determine lock duration
                                $durationMin = ($lockCount24h >= 2) ? 12*60 : 5; // 3rd lock = 12h
                                $locked_until = date('Y-m-d H:i:s', time() + ($durationMin * 60));
                                $newLockCount = ($lockCount24h > 0) ? ($lockCount24h + 1) : 1;

                                $lu = $conn->prepare('UPDATE login_security SET locked_until = ?, attempt_count = 0, last_lock = NOW(), lock_count_24h = ? WHERE identifier = ?');
                                $lu->bind_param('sis', $locked_until, $newLockCount, $email);
                                $lu->execute();
                                $lu->close();

                                $attempts_remaining = null;
                                $lock_remaining_seconds = max(0, strtotime($locked_until) - time());

                                if ($durationMin >= 60) {
                                    $hours = intval($durationMin / 60);
                                    $mins = $durationMin % 60;
                                    $errors[] = "Too many login attempts. Please wait {$hours}h {$mins}m before trying again.";
                                } else {
                                    $errors[] = "Too many login attempts. Please wait {$durationMin} minutes before trying again.";
                                }
                            } else {
                                $attempts_remaining = max(0, $max_login_attempts - $recentAttempts);
                                $msg = 'Invalid email or password.';

                                // Add lock info if user is near 3rd lock
                                if ($attempts_remaining === 1 && $lockCount24h === 2) {
                                    $msg .= " You’ve been locked out 2 times. Another failed attempt will lock your account for 12 hours.";
                                } elseif ($attempts_remaining > 0) {
                                    $msg .= " You have {$attempts_remaining} attempt" . ($attempts_remaining>1?'s':'') . " left.";
                                    if ($attempts_remaining === 1) $msg .= ' Next failed login locks your account for 5 mins.';
                                }

                                $errors[] = $msg;
                            }
                        }
                    } catch (Exception $_) { $errors[] = 'Invalid email or password.'; }
                }
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
            <form class="auth-form active" id="loginForm" method="post">
                <!-- Inline message area (errors / info / lock countdown) -->
                <div id="loginMessage" class="form-message hidden" role="alert" aria-live="polite"></div>

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

                <button type="submit" class="big-btn btn primary wide">Login</button>

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
document.addEventListener('DOMContentLoaded', () => {
    const loginMessage = document.getElementById('loginMessage');
    const form = document.getElementById('loginForm');
    const registered = <?php echo json_encode($registered_notice ?: ''); ?>;
    const errors = <?php echo json_encode($errors ?: []); ?>;
    const attemptsRemaining = <?php echo json_encode($attempts_remaining ?? null); ?>;
    let lockSec = <?php echo json_encode($lock_remaining_seconds ?? 0); ?>;

    // Show registration success
    if (registered && loginMessage) {
        loginMessage.className = 'form-message info';
        loginMessage.innerHTML = registered;
        loginMessage.classList.remove('hidden');
    }

    // Show first server-side error
    if (errors.length && loginMessage) {
        loginMessage.className = 'form-message error';
        loginMessage.innerHTML = errors[0];

        if (attemptsRemaining !== null && attemptsRemaining > 0 && !/attempt|remaining|left/i.test(loginMessage.innerHTML)) {
            loginMessage.innerHTML += ' You have ' + attemptsRemaining + ' attempt' + (attemptsRemaining > 1 ? 's' : '') + ' remaining before your account will be locked.';
        }
        loginMessage.classList.remove('hidden');
    }

    // Handle lock countdown
    if (lockSec > 0 && form && loginMessage) {
        Array.from(form.querySelectorAll('input,button')).forEach(el => el.disabled = true);
        loginMessage.className = 'form-message error';
        function fmt(t){ return Math.floor(t/60) + "m " + t%60 + "s"; }
        loginMessage.innerHTML = 'Too many login attempts. Please wait ' + fmt(lockSec) + '.';
        loginMessage.classList.remove('hidden');

        const iv = setInterval(() => {
            lockSec--;
            if (lockSec <= 0) {
                clearInterval(iv);
                loginMessage.innerHTML = 'You may try logging in again.';
                Array.from(form.querySelectorAll('input,button')).forEach(el => el.disabled = false);
                return;
            }
            loginMessage.innerHTML = 'Too many login attempts. Please wait ' + fmt(lockSec) + '.';
        }, 1000);
    }
});
</script>
</body>
</html>

