<?php
/** Applicant withdrawal through the shared application workflow rules. */
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/application-workflow.php';
require_once __DIR__ . '/../lib/authorization.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}
$session = requireStudent();
Authorization::requireApplicantOwnership($session, (int)$session['user_id']);
require_json_request();
$input = json_decode(file_get_contents('php://input'), true);
$submissionId = is_array($input) ? filter_var($input['submission_id'] ?? $input['submissionId'] ?? null, FILTER_VALIDATE_INT) : false;
if (!$submissionId || $submissionId <= 0) json_response(false, null, 'A valid submission ID is required', 422);

try {
    $workflow = new ApplicationWorkflow($pdo);
    $pdo->beginTransaction();
    $submission = $workflow->lockApplicantSubmission($session, (int)$submissionId);
    $workflow->withdrawForApplicant($session, $submission);
    $pdo->commit();
    json_response(true, ['message' => 'Application withdrawn successfully.']);
} catch (ApplicationWorkflowException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(false, null, $e->getMessage(), $e->getHttpStatus());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Application withdrawal failed.');
    json_response(false, null, 'Application withdrawal could not be completed', 500);
}
