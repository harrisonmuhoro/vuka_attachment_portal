<?php
/** All-or-nothing bulk review using the same workflow and scope rules as single updates. */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/application-workflow.php';
require_once __DIR__ . '/../lib/authorization.php';
require_once __DIR__ . '/../lib/notifications.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed', 405);
}
require_json_request();

try {
    $session = requireAnyAdmin();
    Authorization::requirePermission($session, 'applications.update_status');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        ob_end_clean();
        json_response(false, null, 'Invalid JSON input', 400);
    }
    $rawIds = $input['ids'] ?? null;
    if (!is_array($rawIds) || !$rawIds || count($rawIds) > 100) {
        ob_end_clean();
        json_response(false, null, 'Provide between 1 and 100 submission IDs', 422);
    }
    $ids = [];
    foreach ($rawIds as $rawId) {
        if (!(is_int($rawId) || (is_string($rawId) && ctype_digit($rawId))) || (int)$rawId <= 0) {
            ob_end_clean();
            json_response(false, null, 'Every submission ID must be a positive integer', 422);
        }
        $ids[] = (int)$rawId;
    }
    $ids = array_values(array_unique($ids));
    $requestedStatus = trim((string)($input['status'] ?? ''));
    $reason = trim((string)($input['rejection_reason'] ?? ''));
    $notes = trim((string)($input['review_notes'] ?? ''));
    $assignedRole = isset($input['assigned_role']) ? trim((string)$input['assigned_role']) : null;
    $assignedStation = isset($input['assigned_station']) ? trim((string)$input['assigned_station']) : null;
    $workflow = new ApplicationWorkflow($pdo);
    $status = $workflow->normalizeStatus($requestedStatus);
    if ($status === '') {
        ob_end_clean();
        json_response(false, null, 'A target status is required for bulk review', 422);
    }
    $workflow->validateReviewFields($status, $reason, $notes, $assignedRole, $assignedStation);

    $pdo->beginTransaction();
    $submissions = $workflow->lockScopedSubmissions($session, $ids);

    // Validate every record before writing any record: no silent partial updates.
    foreach ($ids as $id) {
        $workflow->validateTransition((string)$session['role_name'], (string)$submissions[$id]['status'], $status);
    }
    $updated = [];
    foreach ($ids as $id) {
        $submission = $submissions[$id];
        $workflow->applyTransition($session, $submission, $status, $reason, $notes, $assignedRole, $assignedStation);
        $updated[] = $submission;
    }
    $pdo->commit();

    foreach ($updated as $submission) {
        if (!empty($submission['user_id'])) {
            createNotification($pdo, (int)$submission['user_id'], 'student', 'Application Update',
                'Your application status is now: ' . $status . '.', '../pages/student_dashboard.php');
        }
    }

    ob_end_clean();
    json_response(true, ['message' => 'All selected applications were updated', 'updated' => count($updated), 'skipped' => 0]);
} catch (ApplicationWorkflowException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    json_response(false, null, $e->getMessage(), $e->getHttpStatus());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Bulk application update failed.');
    ob_end_clean();
    json_response(false, null, 'Bulk update could not be completed', 500);
}
