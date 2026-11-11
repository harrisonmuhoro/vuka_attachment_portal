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

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/password-policy.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}
require_json_request();
require_once __DIR__ . '/../lib/rate-limiter.php';
checkRateLimit($pdo, 'reset-password', true, 5, 15);
recordLoginAttempt($pdo, 'reset-password', false);

try {
    $data     = json_decode(file_get_contents('php://input'), true);
    $token    = isset($data['token']) ? trim($data['token']) : '';
    $password = isset($data['password']) ? $data['password'] : '';

    if (empty($token)) {
        ob_end_clean();
        json_response(false, null, 'Missing reset token', 400);
    }
    if (PasswordPolicy::validationError((string)$password) !== null) {
        ob_end_clean();
        json_response(false, null, 'Password must be at least 12 characters and within the configured maximum length.', 422);
    }

    // Validate token: exists, unused, not expired.
    $tokenHash = hash('sha256', $token);
    $pdo->beginTransaction();
    $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    // The second condition preserves reset links issued just before deployment;
    // new tokens are stored as SHA-256 digests.
    $stmt = $pdo->prepare("SELECT id, email, account_type FROM password_reset_tokens
                           WHERE token IN (?, ?) AND used = 0 AND expires_at > NOW() LIMIT 1{$lockClause}");
    $stmt->execute([$tokenHash, $token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'This reset link is invalid or has expired. Please request a new one.', 400);
    }

    // Update the correct table's password_hash column.
    $table  = $reset['account_type'] === 'admin' ? 'admin_users' : 'users';
    $hashed = PasswordPolicy::hash((string)$password);

    $upd = $pdo->prepare("UPDATE {$table} SET password_hash = ? WHERE email = ?");
    $upd->execute([$hashed, $reset['email']]);
    if ($upd->rowCount() !== 1) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'Password reset could not be completed', 400);
    }

    // Burn the token.
    $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?")
        ->execute([$reset['id']]);

    if ($reset['account_type'] === 'admin') {
        $pdo->prepare("UPDATE sessions SET is_valid = 0 WHERE admin_id = (SELECT id FROM admin_users WHERE email = ?)")
            ->execute([$reset['email']]);
    } else {
        $pdo->prepare("UPDATE sessions SET is_valid = 0 WHERE user_id = (SELECT id FROM users WHERE email = ?)")
            ->execute([$reset['email']]);
    }
    $pdo->commit();

    ob_end_clean();
    json_response(true, ['message' => 'Password updated successfully. You can now log in.']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Password reset request failed.');
    ob_end_clean();
    json_response(false, null, 'Failed to reset password. Please try again.');
}
