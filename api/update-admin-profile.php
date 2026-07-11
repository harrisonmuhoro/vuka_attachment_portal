<?php
require_once '../session-manager.php';
require_once '../audit-logger.php';

requireAnyAdmin();

$phone = trim($_POST['phone'] ?? '');

$errors = [];
if (!empty($phone) && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
    $errors[] = 'Invalid phone number format.';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// NEVER allow pf_number, department_id, role_id, or is_active to be updated here
$stmt = $pdo->prepare("UPDATE admin_users
                        SET phone = ?, updated_at = NOW()
                        WHERE id = ?");
$stmt->execute([$phone, $session['admin_id']]);

logAudit($pdo, 'admin_profile_updated', "Admin ID {$session['admin_id']} updated profile");

echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
