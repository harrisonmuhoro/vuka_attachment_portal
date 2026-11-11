<?php
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../audit-logger.php';
require_once __DIR__ . '/../lib/password-policy.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}
$session = requireAuth();
$isStudent = $session['user_type'] === 'student';
$userId = $isStudent ? (int)$session['user_id'] : (int)$session['admin_id'];
$table = $isStudent ? 'users' : 'admin_users';
if ($userId <= 0) json_response(false, null, 'Unauthorized', 401);

$currentPassword = (string)($_POST['current_password'] ?? '');
$newPassword = (string)($_POST['new_password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');
if ($currentPassword === '' || PasswordPolicy::validationError($newPassword) !== null || $newPassword !== $confirmPassword
    || $newPassword === $currentPassword) {
    json_response(false, null, 'Provide your current password and a different matching password of at least 12 characters.', 422);
}

try {
    $stmt = $pdo->prepare("SELECT password_hash FROM {$table} WHERE id = ?");
    $stmt->execute([$userId]);
    $storedHash = $stmt->fetchColumn();
    if (!is_string($storedHash) || !password_verify($currentPassword, $storedHash)) {
        json_response(false, null, 'Current password is incorrect', 401);
    }
    $pdo->beginTransaction();
    $hash = PasswordPolicy::hash($newPassword);
    $pdo->prepare("UPDATE {$table} SET password_hash = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$hash, $userId]);
    if ($isStudent) {
        $pdo->prepare('UPDATE sessions SET is_valid = 0 WHERE user_id = ? AND session_id <> ?')
            ->execute([$userId, $session['session_id']]);
    } else {
        $pdo->prepare('UPDATE sessions SET is_valid = 0 WHERE admin_id = ? AND session_id <> ?')
            ->execute([$userId, $session['session_id']]);
    }
    $pdo->commit();
    if (!$isStudent && isset($GLOBALS['auditLogger'])) {
        $GLOBALS['auditLogger']->log($session, 'password_changed', 'admin_account', $userId);
    }
    json_response(true, ['message' => 'Password changed successfully']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Password change failed.');
    json_response(false, null, 'Password change could not be completed', 500);
}
?>
