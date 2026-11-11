<?php
/** Administrative review endpoint with server-derived identity and department scope. */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/email-templates.php';
require_once __DIR__ . '/../lib/notifications.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed', 405);
}
require_json_request();

try {
    // Reject unauthenticated and applicant requests before parsing or querying target data.
    $session = requireAnyAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        ob_end_clean();
        json_response(false, null, 'Invalid JSON input', 400);
    }
    $submissionId = filter_var($data['submission_id'] ?? $data['submissionId'] ?? null, FILTER_VALIDATE_INT);
    if (!$submissionId || $submissionId <= 0) {
        ob_end_clean();
        json_response(false, null, 'A valid submission ID is required', 422);
    }

    $notesProvided = array_key_exists('review_notes', $data) || array_key_exists('reviewNotes', $data);
    $reviewNotes = trim((string)($data['review_notes'] ?? $data['reviewNotes'] ?? ''));
    $requestedStatus = trim((string)($data['status'] ?? ''));
    $rejectionReason = trim((string)($data['rejection_reason'] ?? $data['rejectionReason'] ?? ''));
    $assignedRole = isset($data['assigned_role']) ? trim((string)$data['assigned_role']) : null;
    $assignedStation = isset($data['assigned_station']) ? trim((string)$data['assigned_station']) : null;
    if (strlen($reviewNotes) > 4000 || strlen($rejectionReason) > 1000
        || ($assignedRole !== null && strlen($assignedRole) > 150)
        || ($assignedStation !== null && strlen($assignedStation) > 150)) {
        ob_end_clean();
        json_response(false, null, 'One or more review fields exceed the allowed length', 422);
    }
    if ($requestedStatus === '' && !$notesProvided) {
        ob_end_clean();
        json_response(false, null, 'A status or review note is required', 422);
    }

    $statusAliases = ['pending_review' => 'pending', 'approved' => 'accepted'];
    $newStatus = $statusAliases[$requestedStatus] ?? $requestedStatus;
    $validStatuses = ['pending', 'accepted', 'rejected', 'deployed', 'ongoing', 'completed'];
    if ($newStatus !== '' && !in_array($newStatus, $validStatuses, true)) {
        ob_end_clean();
        json_response(false, null, 'Invalid status value', 422);
    }
    if ($newStatus === 'rejected' && $rejectionReason === '') {
        ob_end_clean();
        json_response(false, null, 'Rejection reason is required', 422);
    }

    $scope = $GLOBALS['sessionManager']->getScopedDepartment($session);
    if ($scope !== 'ALL' && (!is_string($scope) || $scope === '')) {
        ob_end_clean();
        json_response(false, null, 'Administrator department scope is unavailable', 403);
    }

    $pdo->beginTransaction();
    $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $query = 'SELECT id, user_id, full_name, email, department_applied, status, intern_pf_number, application_type FROM submissions WHERE id = ?';
    $queryParams = [$submissionId];
    if ($scope !== 'ALL') {
        $query .= ' AND department_applied = ?';
        $queryParams[] = $scope;
    }
    $stmt = $pdo->prepare($query . $lockClause);
    $stmt->execute($queryParams);
    $submission = $stmt->fetch();
    if (!$submission) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'Submission not found', 404);
    }

    $isSupervisor = $session['role_name'] === 'department_supervisor';
    $currentStatus = (string)$submission['status'];
    if ($newStatus !== '') {
        $transitions = [
            'applied' => ['pending', 'accepted', 'rejected'],
            'pending' => ['accepted', 'rejected'],
            'accepted' => ['deployed'],
            'deployed' => ['ongoing'],
            'ongoing' => ['completed'],
        ];
        if (!in_array($newStatus, $transitions[$currentStatus] ?? [], true)) {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'This status transition is not permitted', 409);
        }
        if ($isSupervisor && !in_array($currentStatus . '>' . $newStatus, ['accepted>deployed', 'deployed>ongoing', 'ongoing>completed'], true)) {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'This role cannot perform the requested status transition', 403);
        }
    } elseif ($reviewNotes === '') {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'Review notes cannot be empty', 422);
    }

    $pfNumber = (string)($session['pf_number'] ?? '');
    $department = (string)($session['department'] ?? '');
    $historyStatus = $newStatus !== '' ? $newStatus : 'note';
    $historyNotes = $reviewNotes;
    if ($newStatus !== '') {
        $internPf = $submission['intern_pf_number'];
        if ($newStatus === 'accepted' && empty($internPf) && ($submission['application_type'] ?? '') === 'internship') {
            $internPf = date('ymdHis') . sprintf('%02d', random_int(0, 99));
        }
        $update = $pdo->prepare('UPDATE submissions SET status = ?, rejection_reason = ?, intern_pf_number = ?,
                assigned_role = COALESCE(?, assigned_role), assigned_station = COALESCE(?, assigned_station),
                last_updated_by = ?, last_updated_at = NOW() WHERE id = ?');
        $update->execute([$newStatus, $newStatus === 'rejected' ? $rejectionReason : null,
            $internPf, $assignedRole, $assignedStation, $pfNumber, $submissionId]);
        if ($historyNotes === '') $historyNotes = $newStatus === 'rejected' ? 'Rejected: ' . $rejectionReason : 'Status changed to: ' . $newStatus;
    }

    $history = $pdo->prepare('INSERT INTO review_history (submission_id, reviewer_pf, reviewer_department, status, notes) VALUES (?, ?, ?, ?, ?)');
    $history->execute([$submissionId, $pfNumber, $department, $historyStatus, $historyNotes]);
    $historyId = (int)$pdo->lastInsertId();
    $pdo->commit();

    if ($newStatus !== '' && in_array($newStatus, ['accepted', 'rejected', 'deployed', 'ongoing', 'completed'], true)) {
        $departmentName = (string)$submission['department_applied'];
        if (!empty($submission['email'])) {
            $sent = sendMail((string)$submission['email'], (string)$submission['full_name'],
                'Application Update — ' . ucfirst($newStatus), emailStatusChanged((string)$submission['full_name'], $newStatus, $departmentName));
            if ($sent) {
                $pdo->prepare('UPDATE review_history SET email_sent = 1, email_sent_at = NOW() WHERE id = ?')->execute([$historyId]);
            }
        }
        if (!empty($submission['user_id'])) {
            $titles = ['accepted' => 'Application Accepted', 'rejected' => 'Application Update',
                'deployed' => 'You have been deployed', 'ongoing' => 'Placement is now ongoing',
                'completed' => 'Attachment completed'];
            createNotification($pdo, (int)$submission['user_id'], 'student', $titles[$newStatus],
                'Your application status is now: ' . $newStatus . '.', '../pages/student_dashboard.php');
        }
    }
    ob_end_clean();
    json_response(true, ['message' => $newStatus === '' ? 'Review notes saved successfully' : 'Status updated to ' . $newStatus]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Submission review update failed.');
    ob_end_clean();
    json_response(false, null, 'Submission update failed', 500);
}
?>
