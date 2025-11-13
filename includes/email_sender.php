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
    $html_body = '<p>Hi ' . htmlspecialchars($to_name) . ',</p>' .
        '<p>Thanks for creating an account. Please verify your email address by clicking the button below:</p>' .
        '<p><a href="' . htmlspecialchars($verification_link) . '" style="display:inline-block;padding:10px 16px;background:#2b7cff;color:#fff;text-decoration:none;border-radius:4px;">Verify your email</a></p>' .
        '<p>If the button doesn\'t work, copy and paste the following URL into your browser:</p>' .
        '<p><small>' . htmlspecialchars($verification_link) . '</small></p>' .
        '<p>— The Artine Team</p>';

    $alt_body = "Hi $to_name\n\nPlease verify your email by visiting the following link:\n$verification_link\n\n-- The Artine Team";

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
