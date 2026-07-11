<?php
require_once '../session-manager.php';
require_once '../audit-logger.php';

requireStudent();

$fullName = trim($_POST['full_name'] ?? '');
$phone    = trim($_POST['phone']     ?? '');

// --- Validation ---
$errors = [];

if (empty($fullName)) {
    $errors[] = 'Full name is required.';
} elseif (strlen($fullName) > 100) {
    $errors[] = 'Full name must be 100 characters or fewer.';
}

if (!empty($phone) && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
    $errors[] = 'Invalid phone number format.';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// --- Update (only editable columns, never national_id or role) ---
$stmt = $pdo->prepare("UPDATE users
                        SET full_name = ?, phone = ?, updated_at = NOW()
                        WHERE id = ?");
$stmt->execute([$fullName, $phone, $session['user_id']]);

logAudit($pdo, 'student_profile_updated', "User ID {$session['user_id']} updated profile");

echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
