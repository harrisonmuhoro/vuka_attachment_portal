<?php
/** Verify an applicant using a one-time, expiring code. */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/rate-limiter.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed', 405);
}
require_json_request();
checkRateLimit($pdo, 'verify-email', true, 10, 15);
recordLoginAttempt($pdo, 'verify-email', false);

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $nationalId = trim((string)($data['national_id'] ?? ''));
    $code = trim((string)($data['code'] ?? ''));
    if (!preg_match('/^\d{6,9}$/', $nationalId) || !preg_match('/^\d{6}$/', $code)) {
        ob_end_clean();
        json_response(false, null, 'Invalid or expired verification code', 400);
    }

    $pdo->beginTransaction();
    $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare("SELECT id, verification_code, verification_sent_at FROM users WHERE national_id = ? AND verified = 0 LIMIT 1{$lockClause}");
    $stmt->execute([$nationalId]);
    $user = $stmt->fetch();
    $sentAt = $user ? strtotime((string)$user['verification_sent_at']) : false;
    $storedCode = (string)($user['verification_code'] ?? '');
    $codeMatches = $storedCode !== '' && password_verify($code, $storedCode);
    // Accept a still-valid pre-migration plaintext code once, then clear it.
    if (!$codeMatches && preg_match('/^\d{6}$/', $storedCode)) {
        $codeMatches = hash_equals($storedCode, $code);
    }
    if (!$user || !$sentAt || $sentAt < time() - 900 || !$codeMatches) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'Invalid or expired verification code', 400);
    }

    $update = $pdo->prepare('UPDATE users SET verified = 1, verification_code = NULL, verification_sent_at = NULL WHERE id = ? AND verified = 0');
    $update->execute([(int)$user['id']]);
    if ($update->rowCount() !== 1) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'Invalid or expired verification code', 400);
    }
    $pdo->commit();
    ob_end_clean();
    json_response(true, ['message' => 'Email verified successfully']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Applicant email verification failed.');
    ob_end_clean();
    json_response(false, null, 'Verification could not be completed', 500);
}
?>
