<?php
/**
 * Update Admin Profile
 * POST /api/update-admin-profile.php
 *
 * Updates the authenticated administrator's own phone number.
 * Identity is always taken from the server-side session; the caller
 * cannot supply an admin_id or change any privileged field.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../audit-logger.php';
require_once __DIR__ . '/../lib/authorization.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$session = requireAnyAdmin();
Authorization::requirePermission($session, 'profile.update_own');

$phone = trim($_POST['phone'] ?? '');

$errors = [];
if ($phone !== '' && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
    $errors[] = 'Invalid phone number format.';
}

if ($errors) {
    json_response(false, null, implode(' ', $errors), 422);
}

// NEVER allow pf_number, department_id, role_id, or status to be updated here.
$stmt = $pdo->prepare('UPDATE admin_users SET phone = ?, updated_at = NOW() WHERE id = ?');
$stmt->execute([$phone, (int)$session['admin_id']]);

$GLOBALS['auditLogger']->log($session, 'admin_profile_updated', 'admin_user', (int)$session['admin_id'],
    null, ['phone' => $phone]);

json_response(true, ['message' => 'Profile updated successfully.']);
