<?php
/**
 * Delete Submission Endpoint
 * POST /api/delete-submission.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    // Require an admin-tier session; derive department scope from it.
    $session = requireAnyAdmin();
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
    
    // Get document file paths before deletion
    $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE submission_id = ?");
    $stmt->execute([$submissionId]);
    $documents = $stmt->fetchAll();
    
    // Delete files from filesystem
    foreach ($documents as $doc) {
        $filepath = UPLOAD_DIR . $doc['file_path'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
    
    // Delete from database (cascade handles documents and review_history)
    $stmt = $pdo->prepare("DELETE FROM submissions WHERE id = ?");
    $stmt->execute([$submissionId]);
    
    ob_end_clean();
    json_response(true, ['message' => 'Submission deleted successfully']);
    
} catch (Exception $e) {
    error_log("Delete submission error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to delete submission');
}
?>
