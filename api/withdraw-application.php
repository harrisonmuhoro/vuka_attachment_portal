<?php
/**
 * Withdraw Application — Vuka Portal (Feature #12)
 * POST /api/withdraw-application.php   { "submission_id": 12 }
 *
 * A student may withdraw their OWN application while it is still
 * 'applied' or 'pending'. Once an admin has acted, withdrawal is blocked.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

$session = requireStudent();

try {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $submissionId = (int)($data['submission_id'] ?? $data['submissionId'] ?? 0);

    if ($submissionId <= 0) {
        ob_end_clean();
        json_response(false, null, 'Missing submission ID');
    }

    // Verify ownership + current status.
    $stmt = $pdo->prepare("SELECT id, status FROM submissions WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$submissionId, (int)$session['user_id']]);
    $sub = $stmt->fetch();

    if (!$sub) {
        ob_end_clean();
        json_response(false, null, 'Application not found');
    }

    if (!in_array($sub['status'], ['applied', 'pending'], true)) {
        ob_end_clean();
        json_response(false, null, 'This application can no longer be withdrawn — it has already been reviewed.');
    }

    $pdo->prepare("UPDATE submissions SET status = 'withdrawn', last_updated_at = NOW() WHERE id = ?")
        ->execute([$submissionId]);

    // Record in review history for the audit trail.
    $pdo->prepare("INSERT INTO review_history (submission_id, reviewer_pf, reviewer_department, status, notes)
                   VALUES (?, ?, ?, 'withdrawn', 'Withdrawn by applicant')")
        ->execute([$submissionId, 'SELF', '']);

    ob_end_clean();
    json_response(true, ['message' => 'Application withdrawn successfully.']);

} catch (Exception $e) {
    error_log('Withdraw application error: ' . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to withdraw application.');
}
