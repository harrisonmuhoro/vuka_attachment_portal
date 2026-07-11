<?php
/**
 * Reset Password — Vuka Portal (Feature #9)
 * POST /api/reset-password.php   { "token": "...", "password": "..." }
 *
 * Validates a single-use, unexpired token and updates the password_hash
 * on the correct account table. Marks the token used.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    $data     = json_decode(file_get_contents('php://input'), true);
    $token    = isset($data['token']) ? trim($data['token']) : '';
    $password = isset($data['password']) ? $data['password'] : '';

    if (empty($token)) {
        ob_end_clean();
        json_response(false, null, 'Missing reset token');
    }
    if (strlen($password) < 8) {
        ob_end_clean();
        json_response(false, null, 'Password must be at least 8 characters');
    }

    // Validate token: exists, unused, not expired.
    $stmt = $pdo->prepare("SELECT id, email, account_type FROM password_reset_tokens
                           WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        ob_end_clean();
        json_response(false, null, 'This reset link is invalid or has expired. Please request a new one.');
    }

    // Update the correct table's password_hash column.
    $table  = $reset['account_type'] === 'admin' ? 'admin_users' : 'users';
    $hashed = hash_password($password); // bcrypt cost 10 (config.php helper)

    $upd = $pdo->prepare("UPDATE {$table} SET password_hash = ? WHERE email = ?");
    $upd->execute([$hashed, $reset['email']]);

    // Burn the token.
    $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?")
        ->execute([$reset['id']]);

    ob_end_clean();
    json_response(true, ['message' => 'Password updated successfully. You can now log in.']);

} catch (Exception $e) {
    error_log('Reset password error: ' . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to reset password. Please try again.');
}
