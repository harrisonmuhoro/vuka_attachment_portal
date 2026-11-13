<?php
/**
 * Update Student Profile
 * POST /api/update-student-profile.php
 *
 * Updates the authenticated student's own full_name and phone.
 * Identity is always taken from the server-side session; the caller
 * cannot supply a user_id or change any privileged field.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../audit-logger.php';
require_once __DIR__ . '/../lib/authorization.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$session = requireStudent();
Authorization::requirePermission($session, 'profile.update_own');
Authorization::requireApplicantOwnership($session, (int)$session['user_id']);

$fullName = trim($_POST['full_name'] ?? '');
$phone    = trim($_POST['phone']     ?? '');

// --- Validation ---
$errors = [];

if (empty($fullName)) {
    $errors[] = 'Full name is required.';
} elseif (strlen($fullName) > 100) {
    $errors[] = 'Full name must be 100 characters or fewer.';
}

if ($phone !== '' && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
    $errors[] = 'Invalid phone number format.';
}

if ($errors) {
    json_response(false, null, implode(' ', $errors), 422);
}

// --- Update (only editable columns, never national_id or role) ---
$stmt = $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, updated_at = NOW() WHERE id = ?');
$stmt->execute([$fullName, $phone, (int)$session['user_id']]);

$GLOBALS['auditLogger']->log($session, 'student_profile_updated', 'user', (int)$session['user_id'],
    null, ['full_name' => $fullName, 'phone' => $phone]);

json_response(true, ['message' => 'Profile updated successfully.']);
