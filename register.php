<?php
require_once 'includes/db_connect.php';
require_once 'includes/session.php';
require_once 'includes/email_sender.php';

$errors = [];

// Handle AJAX/postback from modal: send verification code for just-registered user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_verification') {
    // ensure session available
    if (session_status() === PHP_SESSION_NONE) session_start();
    $justId = !empty($_SESSION['just_registered_user_id']) ? intval($_SESSION['just_registered_user_id']) : 0;
    if ($justId) {
        $u = $conn->query('SELECT email, first_name FROM users WHERE user_id = ' . $justId)->fetch_assoc();
        $to = $u['email'] ?? null;
        $name = trim(($u['first_name'] ?? '')) ?: 'User';
    $ttl = intval(getenv('VERIFICATION_TTL_MIN') ?: 5);
        $res = create_and_send_verification_code($conn, $justId, $to, $name, 'email_verify', $ttl);
        if (!empty($res['success'])) {
            $_SESSION['pending_2fa_user_id'] = $justId;
            $_SESSION['pending_2fa_token_id'] = $res['id'];
            $_SESSION['pending_verification_purpose'] = 'email_verify';
            // clear just_registered flag
            unset($_SESSION['just_registered_user_id']);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'redirect' => '/artine3/auth/verify.php']);
            exit;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Server-side validation with user-friendly messages
    if ($first_name === '') {
        $errors[] = 'First name is required.';
    }
    if ($last_name === '') {
        $errors[] = 'Last name is required.';
    }
    // Email should contain an @ and be a valid email
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strpos($email, '@') === false) {
        $errors[] = 'Please enter a valid email address (must contain an @).';
    }
    // Phone must be exactly 11 digits
    if (!preg_match('/^\d{11}$/', $phone)) {
        $errors[] = 'Phone must be an 11-digit number (numbers only).';
    }
    // Gender required
    if (!in_array($gender, ['male', 'female'])) {
        $errors[] = 'Gender is required.';
    }
    // Password rules: 8+ chars, at least one number, at least one capital, no spaces
    if (!preg_match('/^(?=.*[A-Z])(?=.*\d)[^\s]{8,}$/', $password)) {
        $errors[] = 'Password must be at least 8 characters, include at least one number and one uppercase letter, and contain no spaces.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Check if email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'Email is already registered.';
        }
        $stmt->close();
    }

    if (empty($errors)) {
    // create verification code flow: store user and send 6-digit verification code
    $hash = password_hash($password, PASSWORD_DEFAULT);
        // compute TTL for verification codes (we use token table for codes)
        $ttl = intval(getenv('VERIFICATION_TTL_MIN') ?: 60);

        // Insert into the current users schema (older DBs may not have verification columns)
        $stmt = $conn->prepare(
            'INSERT INTO users (first_name, last_name, email, phone, gender, password_hash, email_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())'
        );
        $stmt->bind_param('ssssss', $first_name, $last_name, $email, $phone, $gender, $hash);

        if ($stmt->execute()) {
            $stmt->close();
                // Save just-registered user id in session and show modal to ask about verification
                $newUserId = intval($conn->insert_id);
                if (session_status() === PHP_SESSION_NONE) session_start();
                $_SESSION['just_registered_user_id'] = $newUserId;
                // indicate to the template to show the verification modal
                $show_verify_modal = true;
                $registered_notice = 'Account created. You can verify your email now.';
        } else {
            $errors[] = 'Database error: ' . $stmt->error;
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
    <title>FitCheck | Register</title>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="auth-content">
        <h1>Create Account</h1>

        <div class="auth-container">
            <div class="auth-forms">

                <?php if (!empty($errors)): ?>
                    <div id="registerMessage" class="form-message error" role="alert" aria-live="polite">
                        <ul>
                            <?php foreach ($errors as $e): ?>
                                <li><?php echo htmlspecialchars($e); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form class="auth-form" id="signupForm" method="post">
                    <div class="name-row">
                        <div class="form-group">
                            <label for="signupFirstName">First Name</label>
                            <input class="input-form" type="text" id="signupFirstName" name="first_name" placeholder="First name"
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="signupLastName">Last Name</label>
                            <input class="input-form" type="text" id="signupLastName" name="last_name" placeholder="Last name"
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="form-group">
                            <label for="signupPhone">Phone</label>
                            <input class="input-form" type="text" id="signupPhone" name="phone" placeholder="11-digit number"
                                value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="signupGender">Gender</label>
                            <select id="signupGender" name="gender">
                                <option value="">-- Select Gender --</option>
                                <option value="male" <?php if (($_POST['gender'] ?? '') == 'male') echo 'selected'; ?>>Male</option>
                                <option value="female" <?php if (($_POST['gender'] ?? '') == 'female') echo 'selected'; ?>>Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="signupEmail">Email</label>
                        <input class="input-form" type="email" id="signupEmail" name="email" placeholder="Enter your email"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="signupPassword">Password</label>
                        <input class="input-form" type="password" id="signupPassword" name="password" placeholder="Create a password">
                    </div>

                    <div class="form-group">
                        <label for="signupPasswordConfirm">Confirm Password</label>
                        <input class="input-form" type="password" id="signupPasswordConfirm" name="password_confirm" placeholder="Confirm password">
                    </div>

                    <button type="submit" class="big-btn btn primary wide">Create Account</button>

                    <div class="switch-form">
                        <button type="button">
                            <a href="login.php">Already have an account?</a>
                        </button>
                    </div>
                </form>
                <?php if (!empty($registered_notice)): ?>
                    <div class="notice"><?php echo htmlspecialchars($registered_notice); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <?php if (!empty($show_verify_modal) && !empty($_SESSION['just_registered_user_id'])): ?>
    <div id="verifyModal" class="modal-overlay" aria-hidden="false">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="verify-modal-title">
            <button class="modal-close" aria-label="Close"><i class="fa fa-times"></i></button>
            <h3 id="verify-modal-title">Verify your account</h3>
            <p>Please verify your account to log in. Unverified accounts cannot access the system.</p>
            <div class="modal-btn">
                <button id="verifyLater" class="btn">Later</button>
                <button id="verifyNow" class="btn primary">Verify now</button>
            </div>
        </div>
    </div>
    <script>
        (function(){
            const modal = document.getElementById('verifyModal');
            const closeBtn = modal.querySelector('.modal-close');
            closeBtn && closeBtn.addEventListener('click', () => { modal.style.display = 'none'; window.location.href = '/artine3/login.php'; });
            document.getElementById('verifyLater').addEventListener('click', function(){
                window.location.href = '/artine3/login.php';
            });
            document.getElementById('verifyNow').addEventListener('click', async function(){
                const btn = this; btn.disabled = true; btn.textContent = 'Sending...';
                try {
                    const form = new FormData();
                    form.append('action', 'send_verification');
                    const res = await fetch('', { method: 'POST', body: form });
                    const j = await res.json();
                    if (j && j.success && j.redirect) {
                        window.location.href = j.redirect;
                    } else {
                        alert('Failed to send verification code. Please try again.');
                        btn.disabled = false; btn.textContent = 'Verify now';
                    }
                } catch (e) {
                    alert('Failed to send verification code.');
                    btn.disabled = false; btn.textContent = 'Verify now';
                }
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
