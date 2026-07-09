<?php
/**
 * Get Submissions Endpoint
 * GET /api/get-submissions.php?department=XXX
 * GET /api/get-submissions.php?id=XXX (single submission with review history)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    // If requesting a single submission (e.g. for review history)
    $singleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($singleId > 0) {
        // Get submission details
        $stmt = $pdo->prepare("
            SELECT s.*, COUNT(d.id) as document_count
            FROM submissions s
            LEFT JOIN documents d ON s.id = d.submission_id
            WHERE s.id = ?
            GROUP BY s.id
        ");
        $stmt->execute([$singleId]);
        $submission = $stmt->fetch();
        
        // Get review history for this submission
        $stmt = $pdo->prepare("
            SELECT * FROM review_history 
            WHERE submission_id = ? 
            ORDER BY reviewed_at DESC
        ");
        $stmt->execute([$singleId]);
        $reviewHistory = $stmt->fetchAll();
        
        // Get documents for this submission
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE submission_id = ?");
        $stmt->execute([$singleId]);
        $documents = $stmt->fetchAll();
        
        ob_end_clean();
        json_response(true, [
            'submission' => $submission,
            'review_history' => $reviewHistory,
            'documents' => $documents
        ]);
    }
    
    // List all submissions for a department
    $requestedDept = isset($_GET['department']) ? trim($_GET['department']) : '';
    $department = '';
    
    // Get authorized department scope from the session (Bearer-first via session-manager).
    // Only resolve an admin scope when a token is present; the public/student path
    // (user_id param, below) must stay reachable without authentication.
    if (get_bearer_token()) {
        $session = $GLOBALS['sessionManager']->validateSession();
        if ($session && $GLOBALS['sessionManager']->isAdminTier($session)) {
            $scope = $GLOBALS['sessionManager']->getScopedDepartment($session);

            // super_admin & hr_coordinator -> 'ALL' (may narrow to a requested dept);
            // department_supervisor -> restricted to their own department.
            if ($scope === 'ALL') {
                $department = !empty($requestedDept) ? $requestedDept : 'ALL';
            } else {
                $department = $scope;
            }
        }
    }
    
    // If no token session found (e.g. public view or direct student history check),
    // fallback to requested dept or user_id (handled below)
    if (empty($department)) {
         $department = $requestedDept;
    }
    
    if (empty($department)) {
         // Check if fetching for specific user (Student Portal)
         $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
         if ($userId <= 0) {
             // If no user_id and no dept, default to ALL if authorized (handled by query builder)
             // But for safety, let's keep it empty and let the query builder handle it
         }
    }
    
    // Build query
    $query = "
        SELECT 
            s.id,
            s.user_id,
            s.national_id,
            s.full_name,
            s.email,
            s.duration,
            s.insurance_cover,
            s.course_applying,
            s.institution_name,
            s.department_applied,
            s.application_type,
            s.status,
            s.placement_status,
            s.rejection_reason,
            s.review_notes,
            s.intern_pf_number,
            s.assigned_role,
            s.assigned_station,
            s.submitted_at,
            v.title as vacancy_title,
            COUNT(d.id) as document_count
        FROM submissions s
        LEFT JOIN documents d ON s.id = d.submission_id
        LEFT JOIN vacancies v ON s.vacancy_id = v.id
    ";
    
    $params = [];
    if (!empty($department) && $department !== 'ALL') {
        $query .= " WHERE s.department_applied = ?";
        $params[] = $department;
    } elseif (isset($userId) && $userId > 0) {
        $query .= " WHERE s.user_id = ?";
        $params[] = $userId;
    }
    
    $query .= " GROUP BY s.id ORDER BY s.submitted_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $submissions = $stmt->fetchAll();
    
    // For each submission, get its documents
    foreach ($submissions as &$sub) {
        $docStmt = $pdo->prepare("SELECT id, document_type, original_filename, mime_type FROM documents WHERE submission_id = ?");
        $docStmt->execute([$sub['id']]);
        $sub['documents'] = $docStmt->fetchAll();
    }
    unset($sub);
    
    ob_end_clean();
    json_response(true, [
        'submissions' => $submissions,
        'count' => count($submissions)
    ]);
    
} catch (Exception $e) {
    error_log("Get submissions error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to fetch submissions');
}
?>
