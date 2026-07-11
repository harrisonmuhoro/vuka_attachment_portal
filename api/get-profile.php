<?php
require_once '../session-manager.php';

$session = getSession();

if (isset($session['user_id'])) {
    // Student
    $stmt = $pdo->prepare("SELECT full_name, email, phone, national_id,
                                   profile_photo, pending_email
                            FROM users WHERE id = ?");
    $stmt->execute([$session['user_id']]);
} else {
    // Admin (any tier)
    $stmt = $pdo->prepare("SELECT pf_number, email, phone, profile_photo,
                                   pending_email,
                                   r.name AS role, d.name AS department
                            FROM admin_users a
                            JOIN roles r ON r.id = a.role_id
                            LEFT JOIN departments d ON d.id = a.department_id
                            WHERE a.id = ?");
    $stmt->execute([$session['admin_id']]);
}

$profile = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$profile) {
    http_response_code(404);
    echo json_encode(['error' => 'Profile not found']);
    exit;
}

echo json_encode(['success' => true, 'profile' => $profile]);
