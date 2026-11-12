<?php
/**
 * Delete Submission Endpoint
 * POST /api/delete-submission.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/authorization.php';
require_once __DIR__ . '/../audit-logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    // Require an admin-tier session; derive department scope from it.
    $session = requireAnyAdmin();
    Authorization::requirePermission($session, 'applications.delete');
    $scopedDept = $GLOBALS['sessionManager']->getScopedDepartment($session);

    $data = json_decode(file_get_contents('php://input'), true);

    // Support both field name conventions
    $submissionId = $data['submission_id'] ?? $data['submissionId'] ?? null;

    if (empty($submissionId)) {
        ob_end_clean();
        json_response(false, null, 'Missing submission ID');
    }

    $submissionId = (int)$submissionId;

    // Verify submission exists
    $stmt = $pdo->prepare("SELECT id, department_applied FROM submissions WHERE id = ?");
    $stmt->execute([$submissionId]);
    $submission = $stmt->fetch();

    if (!$submission) {
        ob_end_clean();
        json_response(false, null, 'Submission not found');
    }

    // Department isolation: super_admin/hr ('ALL') may delete any; supervisors only their own.
    if ($scopedDept !== 'ALL' && $scopedDept !== $submission['department_applied']) {
        ob_end_clean();
        json_response(false, null, 'Unauthorized: Cannot delete submission from other department');
    }
    
    // Capture paths before deletion; physical cleanup happens only after commit.
    $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE submission_id = ?");
    $stmt->execute([$submissionId]);
    $documents = $stmt->fetchAll();
    
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("DELETE FROM submissions WHERE id = ?");
    $stmt->execute([$submissionId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Submission deletion did not affect exactly one record');
    }
    $audit = new AuditLogger($pdo);
    if (!$audit->log($session, 'delete_submission', 'submission', $submissionId,
        ['department_applied' => $submission['department_applied']], null, null,
        'Submission deleted after authorization and database commit preparation')) {
        throw new RuntimeException('Deletion audit could not be recorded');
    }
    $pdo->commit();

    $cleanupFailures = [];
    foreach ($documents as $doc) {
        $relative = (string)($doc['file_path'] ?? '');
        if ($relative === '') continue;
        try {
            $queue = $pdo->prepare('INSERT INTO submission_cleanup_queue (submission_id, file_path) VALUES (?, ?)');
            $queue->execute([$submissionId, $relative]);
        } catch (Throwable $queueError) {
            $cleanupFailures[] = $relative;
            error_log('Submission cleanup queue insert failed for ' . $submissionId . ': ' . $queueError->getMessage());
        }
    }
    if ($cleanupFailures) {
        error_log('Submission file cleanup pending for submission ' . $submissionId . ': ' . implode(',', $cleanupFailures));
    }
    
    ob_end_clean();
    json_response(true, ['message' => 'Submission deleted successfully', 'cleanup_failures' => $cleanupFailures]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Delete submission error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to delete submission');
}
?>
