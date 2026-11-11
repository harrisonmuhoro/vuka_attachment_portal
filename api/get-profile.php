<?php
/** Retrieve only the authenticated account's safe profile fields. */
require_once __DIR__ . '/../session-manager.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}
$session = requireAuth();

try {
    if (($session['user_type'] ?? '') === 'student') {
        $stmt = $pdo->prepare('SELECT full_name, email, phone, national_id, profile_photo, pending_email FROM users WHERE id = ?');
        $stmt->execute([(int)$session['user_id']]);
    } else {
        $stmt = $pdo->prepare('SELECT a.pf_number, a.email, a.phone, a.profile_photo, a.pending_email,
                                      r.role_name AS role, COALESCE(d.name, a.department) AS department
                               FROM admin_users a
                               JOIN roles r ON r.id = a.role_id
                               LEFT JOIN departments d ON d.id = a.department_id
                               WHERE a.id = ?');
        $stmt->execute([(int)$session['admin_id']]);
    }
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) json_response(false, null, 'Profile not found', 404);
    json_response(true, ['profile' => $profile]);
} catch (Throwable $e) {
    error_log('Profile retrieval failed.');
    json_response(false, null, 'Profile could not be retrieved', 500);
}
