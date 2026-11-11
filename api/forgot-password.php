<?php
/**
 * Forgot Password — Vuka Portal (Feature #9)
 * POST /api/forgot-password.php   { "email": "user@example.com" }
 *
 * Generates a single-use, 1-hour reset token and emails a reset link.
 * Always returns success (never reveals whether an email is registered).
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/email-templates.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    $data  = json_decode(file_get_contents('php://input'), true);
    $email = isset($data['email']) ? trim($data['email']) : '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        json_response(false, null, 'Please enter a valid email address');
    }

    // Look up in students first, then admins.
    $accountType = null;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $accountType = 'student';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $accountType = 'admin';
        }
    }

    // Generate + store token, then email the link. Only if the account exists.
    if ($accountType !== null) {
        // Invalidate any prior unused tokens for this email.
        $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE email = ? AND used = 0")
            ->execute([$email]);

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        $pdo->prepare("INSERT INTO password_reset_tokens (email, token, account_type, expires_at)
                       VALUES (?, ?, ?, ?)")
            ->execute([$email, $token, $accountType, $expiresAt]);

        $resetUrl = rtrim(APP_URL, '/') . '/pages/reset-password.php?token=' . $token;
        sendMail($email, '', 'Reset Your Vuka Portal Password', emailPasswordReset($resetUrl));
    }

    // Uniform response — never disclose account existence.
    ob_end_clean();
    json_response(true, ['message' => 'If that email is registered, a password reset link has been sent.']);

} catch (Exception $e) {
    error_log('Forgot password error: ' . $e->getMessage());
    ob_end_clean();
    // Still return the uniform message to avoid leaking internal state.
    json_response(true, ['message' => 'If that email is registered, a password reset link has been sent.']);
}
