<?php
/**
 * Delete Submission Endpoint
 * POST /api/delete-submission.php
 * Vuka Attachment Portal — Phase 2.2
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/authorization.php';
require_once __DIR__ . '/../audit-logger.php';
require_once __DIR__ . '/../lib/cleanup-path-validator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed', 405);
}

$submissionId = null;

try {
    // 1. Require an admin-tier session and verify centralized delete permission
    $session = requireAnyAdmin();
    Authorization::requirePermission($session, 'applications.delete');
    $scopedDept = $GLOBALS['sessionManager']->getScopedDepartment($session);

    // 2. Validate input
    $data = json_decode(file_get_contents('php://input'), true);
    $rawId = $data['submission_id'] ?? $data['submissionId'] ?? null;

    if (empty($rawId) || !is_numeric($rawId) || (int)$rawId <= 0) {
        ob_end_clean();
        json_response(false, null, 'Missing or invalid submission ID', 400);
    }
    $submissionId = (int)$rawId;

    $isMysql = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql');
    $lockClause = $isMysql ? ' FOR UPDATE' : '';

    // 3. Begin single atomic database transaction
    $pdo->beginTransaction();

    // 4. Lock and verify target submission where supported
    $stmt = $pdo->prepare("SELECT id, department_applied FROM submissions WHERE id = ?" . $lockClause);
    $stmt->execute([$submissionId]);
    $submission = $stmt->fetch();

    if (!$submission) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'Submission not found', 404);
    }

    // 5. Verify actor's department scope
    if ($scopedDept !== 'ALL' && $scopedDept !== $submission['department_applied']) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'Unauthorized: Cannot delete submission from other department', 403);
    }

    // 6. Retrieve associated document paths while records are protected
    $stmt = $pdo->prepare("SELECT id, file_path FROM documents WHERE submission_id = ?" . $lockClause);
    $stmt->execute([$submissionId]);
    $documents = $stmt->fetchAll();

    // 7. Insert cleanup queue records for every eligible physical file
    $queueStmt = $pdo->prepare('INSERT INTO submission_cleanup_queue (submission_id, file_path) VALUES (?, ?)');
    foreach ($documents as $doc) {
        $rawPath = trim((string)($doc['file_path'] ?? ''));
        if ($rawPath === '') {
            continue; // BLOB-stored file without physical disk representation
        }
        $canonicalPath = CleanupPathValidator::normalizeForQueue($rawPath);
        if ($canonicalPath === '') {
            continue;
        }
        $queueStmt->execute([$submissionId, $canonicalPath]);
    }

    // 8. Delete the submission record
    $stmt = $pdo->prepare("DELETE FROM submissions WHERE id = ?");
    $stmt->execute([$submissionId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Submission deletion did not affect exactly one record');
    }

    // 9. Record deletion audit event
    $audit = new AuditLogger($pdo);
    $auditSuccess = $audit->log(
        $session,
        'delete_submission',
        'submission',
        $submissionId,
        ['department_applied' => $submission['department_applied']],
        null,
        null,
        'Submission deleted — cleanup queued atomically'
    );
    if (!$auditSuccess) {
        throw new RuntimeException('Deletion audit could not be recorded');
    }

    // 10. Commit transaction: submission deletion, queue insertion, and audit succeed together
    $pdo->commit();

    ob_end_clean();
    json_response(true, ['message' => 'Submission deleted successfully']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete submission error for ID " . var_export($submissionId, true) . ": " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to delete submission', 500);
}
?>
