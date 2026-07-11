<?php
require_once '../session-manager.php';
require_once '../audit-logger.php';

$session = getSession();

$isStudent   = isset($session['user_id']);
$userId      = $isStudent ? $session['user_id'] : $session['admin_id'];
$table       = $isStudent ? 'users' : 'admin_users';

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$currentPassword = $_POST['current_password'] ?? '';
$newPassword     = $_POST['new_password']     ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$errors = [];

if (empty($currentPassword)) {
    $errors[] = 'Current password is required.';
}
if (strlen($newPassword) < 8) {
    $errors[] = 'New password must be at least 8 characters.';
}
if ($newPassword !== $confirmPassword) {
    $errors[] = 'New passwords do not match.';
}
if ($newPassword === $currentPassword) {
    $errors[] = 'New password must be different from your current password.';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// Verify current password against DB
$stmt = $pdo->prepare("SELECT password FROM {$table} WHERE id = ?");
$stmt->execute([$userId]);
$stored = $stmt->fetchColumn();

if (!$stored || !password_verify($currentPassword, $stored)) {
    http_response_code(401);
    echo json_encode(['error' => 'Current password is incorrect.']);
    exit;
}

// Update
$hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
$pdo->prepare("UPDATE {$table} SET password = ?, updated_at = NOW() WHERE id = ?")
    ->execute([$hashed, $userId]);

// Invalidate all other sessions for this user (force re-login on other devices)
if ($isStudent) {
    $pdo->prepare("DELETE FROM sessions WHERE user_id = ? AND token != ?")
        ->execute([$userId, $session['token']]);
} else {
    $pdo->prepare("DELETE FROM sessions WHERE admin_id = ? AND token != ?")
        ->execute([$userId, $session['token']]);
}

logAudit($pdo, 'password_changed', ($isStudent ? 'Student' : 'Admin') . " ID {$userId} changed password");

echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
