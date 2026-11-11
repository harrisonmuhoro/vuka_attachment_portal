<?php
/** Return applicant-owned submissions or submissions within an admin scope. */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed', 405);
}

try {
    // Authentication and role scope are resolved before private data is queried.
    $candidate = $GLOBALS['sessionManager']->validateSession();
    if (!$candidate) {
        ob_end_clean();
        json_response(false, null, 'Authentication required', 401);
    }
    if ($candidate['role_name'] === 'student' && $candidate['user_type'] === 'student') {
        $session = requireStudent();
        $isApplicant = true;
        $where = 's.user_id = ?';
        $scopeParams = [(int)$session['user_id']];
    } elseif ($GLOBALS['sessionManager']->isAdminTier($candidate)) {
        $session = requireAnyAdmin();
        $isApplicant = false;
        $scope = $GLOBALS['sessionManager']->getScopedDepartment($session);
        if ($scope === 'ALL') {
            $requestedDepartment = trim((string)($_GET['department'] ?? ''));
            $where = $requestedDepartment !== '' ? 's.department_applied = ?' : '1 = 1';
            $scopeParams = $requestedDepartment !== '' ? [$requestedDepartment] : [];
        } elseif (is_string($scope) && $scope !== '') {
            $where = 's.department_applied = ?';
            $scopeParams = [$scope];
        } else {
            ob_end_clean();
            json_response(false, null, 'Administrator department scope is unavailable', 403);
        }
    } else {
        ob_end_clean();
        json_response(false, null, 'Forbidden', 403);
    }

    $singleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($singleId !== null && $singleId !== false) {
        if ($singleId <= 0) {
            ob_end_clean();
            json_response(false, null, 'Submission not found', 404);
        }
        $stmt = $pdo->prepare("SELECT s.id, s.user_id, s.national_id, s.full_name, s.email,
                s.duration, s.insurance_cover, s.course_applying, s.institution_name,
                s.department_applied, s.application_type, s.status, s.placement_status,
                s.rejection_reason, s.review_notes, s.intern_pf_number, s.assigned_role,
                s.assigned_station, s.submitted_at, v.title AS vacancy_title,
                (SELECT COUNT(*) FROM documents d WHERE d.submission_id = s.id) AS document_count
            FROM submissions s LEFT JOIN vacancies v ON v.id = s.vacancy_id
            WHERE s.id = ? AND {$where} LIMIT 1");
        $stmt->execute(array_merge([$singleId], $scopeParams));
        $submission = $stmt->fetch();
        if (!$submission) {
            ob_end_clean();
            json_response(false, null, 'Submission not found', 404);
        }
        if ($isApplicant) {
            // Applicant feedback is limited to the applicant-facing fields stored on the submission.
            unset($submission['intern_pf_number'], $submission['assigned_role'], $submission['assigned_station'], $submission['review_notes']);
            $history = [];
        } else {
            $historyStmt = $pdo->prepare('SELECT id, status, notes, reviewed_at FROM review_history WHERE submission_id = ? ORDER BY reviewed_at DESC');
            $historyStmt->execute([$singleId]);
            $history = $historyStmt->fetchAll();
        }
        $docsStmt = $pdo->prepare('SELECT id, document_type, original_filename, mime_type, file_size, uploaded_at FROM documents WHERE submission_id = ? ORDER BY id');
        $docsStmt->execute([$singleId]);
        $documents = $docsStmt->fetchAll();
        ob_end_clean();
        json_response(true, [
            'submission' => $submission,
            'review_history' => $history,
            'documents' => $documents,
        ]);
    }

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : null;
    $perPage = min(50, max(5, (int)($_GET['per_page'] ?? 20)));
    $offset = $page === null ? 0 : ($page - 1) * $perPage;
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM submissions s WHERE {$where}");
    $countStmt->execute($scopeParams);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT s.id, s.user_id, s.national_id, s.full_name, s.email, s.duration,
                s.insurance_cover, s.course_applying, s.institution_name, s.department_applied,
                s.application_type, s.status, s.placement_status, s.rejection_reason, s.review_notes,
                s.intern_pf_number, s.assigned_role, s.assigned_station, s.submitted_at,
                v.title AS vacancy_title,
                (SELECT COUNT(*) FROM documents d WHERE d.submission_id = s.id) AS document_count
            FROM submissions s LEFT JOIN vacancies v ON v.id = s.vacancy_id
            WHERE {$where} ORDER BY s.submitted_at DESC";
    if ($page !== null) $sql .= ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($scopeParams);
    $submissions = $stmt->fetchAll();

    $docStmt = $pdo->prepare('SELECT id, document_type, original_filename, mime_type, file_size FROM documents WHERE submission_id = ? ORDER BY id');
    foreach ($submissions as &$submission) {
        if ($isApplicant) {
            unset($submission['intern_pf_number'], $submission['assigned_role'], $submission['assigned_station'], $submission['review_notes']);
        }
        $docStmt->execute([(int)$submission['id']]);
        $submission['documents'] = $docStmt->fetchAll();
    }
    unset($submission);

    $payload = ['submissions' => $submissions, 'count' => count($submissions)];
    if ($page !== null) {
        $payload['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }
    ob_end_clean();
    json_response(true, $payload);
} catch (Throwable $e) {
    error_log('Submission retrieval failed.');
    ob_end_clean();
    json_response(false, null, 'Failed to fetch submissions', 500);
}
?>
