<?php
// includes/email_sender.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

function send_email($to_email, $to_name, $subject, $html_body, $alt_body = '') {
    $mail = new PHPMailer(true);
    try {
        // SMTP server config - example: Gmail
        // Prefer environment/config variables for SMTP settings. Falls back to example Gmail values.
        $smtp_host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $smtp_user = getenv('SMTP_USER') ?: 'jrmugly3@gmail.com';
        $smtp_pass = getenv('SMTP_PASS') ?: 'aovu uymv slde fyhy';
        $smtp_port = getenv('SMTP_PORT') ?: 587;
        $smtp_secure = getenv('SMTP_SECURE') ?: PHPMailer::ENCRYPTION_STARTTLS; // or 'ssl'
        $from_email = getenv('MAIL_FROM') ?: $smtp_user;
        $from_name = getenv('MAIL_FROM_NAME') ?: 'Artine';

        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;          // your SMTP username
        $mail->Password   = $smtp_pass;          // app password (Gmail) or real SMTP pass
        $mail->SMTPSecure = $smtp_secure;
        $mail->Port       = $smtp_port;

        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo($from_email, $from_name . ' Support');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = $alt_body ?: strip_tags($html_body);

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        // Log error: $mail->ErrorInfo
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Send a verification email containing a token link to the user.
 *
 * @param string $to_email
 * @param string $to_name
 * @param string $token
 * @return array ['success' => bool, 'error' => string|null]
 */
function send_verification_email($to_email, $to_name, $token) {
    // Build verification link
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $verification_link = $scheme . '://' . $host . '/artine3/auth/verify.php?token=' . urlencode($token);

    $subject = 'Verify your Artine account';
    // Clean, mobile-friendly verification email with clear call to action
    $html_body = '<div style="font-family:Arial,Helvetica,sans-serif;color:#111;line-height:1.4">'
        . '<div style="max-width:600px;margin:0 auto;padding:20px">'
        . '<div style="background:#ffffff;border-radius:8px;padding:24px;border:1px solid #eef2ff">'
        . '<h2 style="margin-top:0;color:#0b57d0">Verify your email address</h2>'
        . '<p style="margin:8px 0 16px">Hi ' . htmlspecialchars($to_name) . ',</p>'
        . '<p style="margin:8px 0 16px;color:#374151">Thanks for creating an Artine account. To complete setup and unlock all features, please verify your email address by clicking the button below.</p>'
        . '<div style="text-align:center;margin:18px 0">'
        . '<a href="' . htmlspecialchars($verification_link) . '" style="display:inline-block;padding:12px 20px;background:#0b57d0;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600">Verify my email</a>'
        . '</div>'
        . '<p style="font-size:13px;color:#6b7280;margin:8px 0">This link will expire shortly. If the button doesn\'t work, copy and paste the following URL into your browser:</p>'
        . '<p style="word-break:break-all;font-size:13px;color:#6b7280"><small>' . htmlspecialchars($verification_link) . '</small></p>'
        . '<hr style="border:none;border-top:1px solid #f3f4f6;margin:18px 0">'
        . '<p style="font-size:13px;color:#6b7280;margin:0">If you didn\'t create an account, you can ignore this message.</p>'
        . '<p style="font-size:13px;color:#6b7280;margin:8px 0">Need help? Reply to this email or contact support.</p>'
        . '<p style="color:#9ca3af;font-size:12px;margin-top:18px">— The Artine Team</p>'
        . '</div></div></div>';

    $alt_body = "Hi $to_name\n\nPlease verify your Artine account by visiting the following link:\n$verification_link\n\nIf you didn't create an account, you can ignore this message.\n\n-- The Artine Team";

    return send_email($to_email, $to_name, $subject, $html_body, $alt_body);
}

/**
 * Send a password reset email containing a token link to the user.
 * Uses users.verification_token (for compatibility) and points to change_password
 *
 * @param string $to_email
 * @param string $to_name
 * @param int $uid
 * @param string $token
 * @return array ['success' => bool, 'error' => string|null]
 */
function send_password_reset_email($to_email, $to_name, $uid, $token) {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $reset_link = $scheme . '://' . $host . '/artine3/auth/password_reset.php?uid=' . urlencode($uid) . '&token=' . urlencode($token);

    $subject = 'Reset your Artine account password';
    $html_body = '<p>Hi ' . htmlspecialchars($to_name) . ',</p>' .
        '<p>We received a request to reset the password for your Artine account. Click the button below to reset your password. This link will expire shortly.</p>' .
        '<p><a href="' . htmlspecialchars($reset_link) . '" style="display:inline-block;padding:10px 16px;background:#2b7cff;color:#fff;text-decoration:none;border-radius:4px;">Reset password</a></p>' .
        '<p>If you didn\'t request a password reset, you can ignore this email.</p>' .
        '<p>If the button doesn\'t work, copy and paste the following URL into your browser:</p>' .
        '<p><small>' . htmlspecialchars($reset_link) . '</small></p>' .
        '<p>— The Artine Team</p>';

    $alt_body = "Hi $to_name\n\nReset your password by visiting the following link:\n$reset_link\n\nIf you didn't request this, ignore this email.\n\n-- The Artine Team";

    return send_email($to_email, $to_name, $subject, $html_body, $alt_body);
}


/**
 * Create a 6-digit verification code, store it in verification_codes table and send it to user.
 * Returns array with success and inserted id. In dev mode (DEV_SHOW_CODES env) the plain code is returned.
 */
function create_and_send_verification_code($conn, $user_id, $to_email, $to_name, $purpose = 'generic', $ttl_min = 5) {
    // verification_codes table assumed to exist in schema

    try {
        try { $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT); } catch (Exception $e) { $code = sprintf('%06d', mt_rand(0, 999999)); }
        $code_hash = password_hash($code, PASSWORD_DEFAULT);
        $expires_at = date('Y-m-d H:i:s', time() + ($ttl_min * 60));
        $ins = $conn->prepare('INSERT INTO verification_codes (user_id, purpose, code_hash, expires_at) VALUES (?, ?, ?, ?)');
        if (!$ins) return ['success' => false, 'error' => 'DB prepare failed'];
        $ins->bind_param('isss', $user_id, $purpose, $code_hash, $expires_at);
    $ins->execute();
    // use connection insert_id (mysqli_stmt->insert_id is not reliable); get from connection
    $newId = $conn->insert_id;
        $ins->close();

        // choose subject/body based on purpose
        // Build a clear, readable HTML email for code-based verification
        $expiresText = 'This code expires in ' . intval($ttl_min) . ' minutes.';
        if ($purpose === 'email_verify') {
            $subject = 'Verify your Artine account';
            $subtext = 'Enter the code below to verify your email address and complete account setup.';
        } else if ($purpose === 'password_reset') {
            $subject = 'Artine password reset code';
            $subtext = 'Enter the code below to reset your password.';
        } else if ($purpose === 'enable_2fa') {
            $subject = 'Enable email two-factor authentication (2FA)';
            $subtext = 'Enter the code below to confirm enabling email-based two-factor authentication for your account.';
        } else if ($purpose === 'login_2fa') {
            $subject = 'Your Artine login code';
            $subtext = 'Enter the code below to complete your login.';
        } else {
            $subject = 'Your Artine verification code';
            $subtext = 'Enter the code below to continue.';
        }

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;color:#111;line-height:1.4">'
            . '<div style="max-width:600px;margin:0 auto;padding:20px">'
            . '<div style="background:#ffffff;border-radius:8px;padding:20px;border:1px solid #eef2ff;text-align:center">'
            . '<h2 style="margin:0 0 8px;color:#0b57d0">' . htmlspecialchars($subject) . '</h2>'
            . '<p style="margin:0 0 16px;color:#374151">' . htmlspecialchars($subtext) . '</p>'
            . '<div style="margin:18px 0;padding:14px;border-radius:8px;background:#f8fafc;display:inline-block">'
            . '<span style="display:block;font-size:28px;letter-spacing:6px;font-weight:700;color:#111">' . htmlspecialchars($code) . '</span>'
            . '</div>'
            . '<p style="margin:10px 0 0;color:#6b7280;font-size:13px">' . htmlspecialchars($expiresText) . '</p>'
            . '<hr style="border:none;border-top:1px solid #f3f4f6;margin:18px 0">'
            . '<p style="font-size:13px;color:#6b7280;margin:0">If you did not request this, you can safely ignore this email.</p>'
            . '<p style="color:#9ca3af;font-size:12px;margin-top:12px">— The Artine Team</p>'
            . '</div></div></div>';

        // send
        send_email($to_email, $to_name, $subject, $html);

        $out = ['success' => true, 'id' => $newId];
        if (getenv('DEV_SHOW_CODES')) $out['code'] = $code;
        return $out;
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Failed to create or send code'];
    }
}
