<?php
/** Change one submission using the shared application workflow service. */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/application-workflow.php';
require_once __DIR__ . '/../lib/authorization.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/email-templates.php';
require_once __DIR__ . '/../lib/notifications.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed', 405);
}
require_json_request();

try {
    $session = requireAnyAdmin();
    Authorization::requirePermission($session, 'applications.update_status');
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
    $notes = trim((string)($data['review_notes'] ?? $data['reviewNotes'] ?? ''));
    $requestedStatus = trim((string)($data['status'] ?? ''));
    $reason = trim((string)($data['rejection_reason'] ?? $data['rejectionReason'] ?? ''));
    $assignedRole = isset($data['assigned_role']) ? trim((string)$data['assigned_role']) : null;
    $assignedStation = isset($data['assigned_station']) ? trim((string)$data['assigned_station']) : null;
    if ($requestedStatus === '' && (!$notesProvided || $notes === '')) {
        ob_end_clean();
        json_response(false, null, 'A status or non-empty review note is required', 422);
    }

    $workflow = new ApplicationWorkflow($pdo);
    $newStatus = $requestedStatus === '' ? '' : $workflow->normalizeStatus($requestedStatus);
    $workflow->validateReviewFields($newStatus, $reason, $notes, $assignedRole, $assignedStation);

    $pdo->beginTransaction();
    $submissions = $workflow->lockScopedSubmissions($session, [(int)$submissionId]);
    $submission = $submissions[(int)$submissionId];
    $historyId = null;
    if ($newStatus !== '') {
        $historyId = $workflow->applyTransition($session, $submission, $newStatus, $reason, $notes, $assignedRole, $assignedStation);
    } else {
        $workflow->recordReviewNote($session, $submission, $notes);
    }
    $pdo->commit();

    if ($newStatus !== '' && !empty($submission['email'])) {
        $sent = sendMail((string)$submission['email'], (string)$submission['full_name'],
            'Application Update — ' . ucfirst($newStatus),
            emailStatusChanged((string)$submission['full_name'], $newStatus, (string)$submission['department_applied']));
        if ($sent && $historyId !== null) {
            $pdo->prepare('UPDATE review_history SET email_sent = 1, email_sent_at = NOW() WHERE id = ?')->execute([$historyId]);
        }
    }
    if ($newStatus !== '' && !empty($submission['user_id'])) {
        createNotification($pdo, (int)$submission['user_id'], 'student', 'Application Update',
            'Your application status is now: ' . $newStatus . '.', '../pages/student_dashboard.php');
    }

    ob_end_clean();
    json_response(true, ['message' => $newStatus === '' ? 'Review notes saved successfully' : 'Status updated to ' . $newStatus]);
} catch (ApplicationWorkflowException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    json_response(false, null, $e->getMessage(), $e->getHttpStatus());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Submission review update failed.');
    ob_end_clean();
    json_response(false, null, 'Submission update failed', 500);
}
