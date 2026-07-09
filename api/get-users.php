<?php
/**
 * Get Student Accounts API
 * GET /api/get-users.php
 * 
 * Protected: System Admin Only
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';

try {
    // Only Super Admin may list all student accounts.
    $session = requireSuperAdmin();

    // Fetch All Students with application status
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.national_id, u.email, u.status, u.verified, u.created_at,
               sub.status as application_status, sub.institution_name, sub.intern_pf_number,
               sub.department_applied, sub.course_applying
        FROM users u
        LEFT JOIN submissions sub ON u.id = sub.user_id
        ORDER BY u.created_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set application_status to 'not_applied' if null
    foreach ($students as &$s) {
        if (empty($s['application_status'])) {
            $s['application_status'] = 'not_applied';
        }
    }
    unset($s);

    ob_end_clean();
    json_response(true, ['students' => $students]);

} catch (Exception $e) {
    error_log("Get Students Error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Server error fetching students');
}
