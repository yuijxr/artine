<?php
require_once 'includes/db_connect.php';
require_once 'includes/session.php';
require_once 'includes/email_sender.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($first_name === '') {
        $errors[] = 'First name is required.';
    }
    if ($last_name === '') {
        $errors[] = 'Last name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (!preg_match('/^\d{11}$/', $phone)) {
        $errors[] = 'Phone number must be 11 digits.';
    }
    if (!in_array($gender, ['male', 'female'])) {
        $errors[] = 'Please select a valid gender.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
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
        // create verification token and store it; email verification required
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $verification_token = bin2hex(random_bytes(16));
        // Ensure verification_expires column exists (older DBs may not have it)
        $col = $conn->query("SHOW COLUMNS FROM users LIKE 'verification_expires'");
        if (!$col || $col->num_rows === 0) {
            // attempt to add column (MySQL 8+ supports IF NOT EXISTS for ADD COLUMN, but use simple ALTER)
            $conn->query("ALTER TABLE users ADD COLUMN verification_expires TIMESTAMP NULL DEFAULT NULL");
        }

        // compute expiry timestamp (minutes configurable via VERIFICATION_TTL_MIN env var, default 60)
        $ttl = intval(getenv('VERIFICATION_TTL_MIN') ?: 60);
        $verification_expires = date('Y-m-d H:i:s', time() + ($ttl * 60));

        $stmt = $conn->prepare(
            'INSERT INTO users (first_name, last_name, email, phone, gender, password_hash, verification_token, verification_expires, email_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())'
        );
        $stmt->bind_param('ssssssss', $first_name, $last_name, $email, $phone, $gender, $hash, $verification_token, $verification_expires);

        if ($stmt->execute()) {
            $stmt->close();
            // For local/dev environments we previously showed the verification link.
            $verification_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/artine3/auth/verify.php?token=' . urlencode($verification_token);

            // Attempt to send verification email. Regardless of send success, redirect to login
            // so the site shows a single toast-based confirmation message there.
            $sendResult = send_verification_email($email, $first_name ?: $email, $verification_token);

            // Redirect to login page where a toast will show the registration notice.
            header('Location: login.php?registered=1');
            exit;
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
                    <div class="error-box">
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
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="signupLastName">Last Name</label>
                            <input class="input-form" type="text" id="signupLastName" name="last_name" placeholder="Last name"
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="form-group">
                            <label for="signupPhone">Phone</label>
                            <input class="input-form" type="text" id="signupPhone" name="phone" placeholder="11-digit number"
                                value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="signupGender">Gender</label>
                            <select id="signupGender" name="gender" required>
                                <option value="">-- Select Gender --</option>
                                <option value="male" <?php if (($_POST['gender'] ?? '') == 'male') echo 'selected'; ?>>Male</option>
                                <option value="female" <?php if (($_POST['gender'] ?? '') == 'female') echo 'selected'; ?>>Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="signupEmail">Email</label>
                        <input class="input-form" type="email" id="signupEmail" name="email" placeholder="Enter your email"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="signupPassword">Password</label>
                        <input class="input-form" type="password" id="signupPassword" name="password" placeholder="Create a password" required>
                    </div>

                    <div class="form-group">
                        <label for="signupPasswordConfirm">Confirm Password</label>
                        <input class="input-form" type="password" id="signupPasswordConfirm" name="password_confirm" placeholder="Confirm password" required>
                    </div>

                    <button type="submit" class="auth-button">Create Account</button>

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
</body>
</html>
