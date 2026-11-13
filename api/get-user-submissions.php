<?php
/**
 * Get User Submissions API
 * GET /api/get-user-submissions.php
 * 
 * Returns submissions for authenticated student
 * Only students can access their own submissions
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/authorization.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed', 405);
}

try {
    $session = requireStudent();
    Authorization::requirePermission($session, 'applications.view');
    Authorization::requireApplicantOwnership($session, (int)$session['user_id']);
    
    // Explicit applicant-facing column allowlist.
    // Internal administrative fields (review_notes, intern_pf_number, assigned_role,
    // assigned_station, last_updated_by, last_updated_at) are strictly excluded.
    $stmt = $pdo->prepare("
        SELECT
            id,
            user_id,
            national_id,
            full_name,
            email,
            vacancy_id,
            application_type,
            duration,
            insurance_cover,
            course_applying,
            institution_name,
            department_applied,
            status,
            placement_status,
            rejection_reason,
            submitted_at,
            created_at,
            updated_at
        FROM submissions
        WHERE user_id = ?
        ORDER BY submitted_at DESC
    ");
    
    $stmt->execute([(int)$session['user_id']]);
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_end_clean();
    json_response(true, [
        'submissions' => $submissions,
        'data' => [
            'submissions' => $submissions
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get user submissions error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to retrieve submissions');
}

?>
