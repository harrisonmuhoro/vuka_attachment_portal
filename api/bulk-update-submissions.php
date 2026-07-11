<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/notifications.php';

$session = requireAnyAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

$scope = $GLOBALS['sessionManager']->getScopedDepartment($session);

$allowedStatuses = ['accepted', 'rejected', 'pending', 'deployed', 'ongoing'];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $rawIds = isset($input['ids']) ? $input['ids'] : null;
    $status = isset($input['status']) ? trim((string)$input['status']) : '';
    $rejectionReason = isset($input['rejection_reason']) ? trim((string)$input['rejection_reason']) : '';
    $reviewNotes = isset($input['review_notes']) ? trim((string)$input['review_notes']) : '';

    // Validate: ids is a non-empty array
    if (!is_array($rawIds) || count($rawIds) === 0) {
        ob_end_clean();
        json_response(false, null, 'ids must be a non-empty array');
    }

    // Cast each to int and drop non-positive
    $ids = [];
    foreach ($rawIds as $rawId) {
        $castId = (int)$rawId;
        if ($castId > 0) {
            $ids[] = $castId;
        }
    }
    if (count($ids) === 0) {
        ob_end_clean();
        json_response(false, null, 'No valid submission ids provided');
    }

    // Validate: status must be one of the allowed values
    if (!in_array($status, $allowedStatuses, true)) {
        ob_end_clean();
        json_response(false, null, 'Invalid status value');
    }

    // Rejection requires a reason
    if ($status === 'rejected' && $rejectionReason === '') {
        ob_end_clean();
        json_response(false, null, 'Rejection reason is required for bulk rejection');
    }

    $rejectionParam = ($rejectionReason === '') ? null : $rejectionReason;
    $notesValue = ($reviewNotes === '') ? "Bulk update to {$status}" : $reviewNotes;

    $updated = 0;
    $skipped = 0;

    $pdo->beginTransaction();

    // Prepare statements once
    $selectStmt = $pdo->prepare('SELECT id, user_id, full_name, email, department_applied FROM submissions WHERE id = ?');
    $updateStmt = $pdo->prepare('UPDATE submissions SET status = ?, rejection_reason = COALESCE(?, rejection_reason), last_updated_by = ?, last_updated_at = NOW() WHERE id = ?');
    $historyStmt = $pdo->prepare('INSERT INTO review_history (submission_id, reviewer_pf, reviewer_department, status, notes) VALUES (?, ?, ?, ?, ?)');

    foreach ($ids as $id) {
        $selectStmt->execute([$id]);
        $row = $selectStmt->fetch(PDO::FETCH_ASSOC);

        // Verify exists and is in scope
        if (!$row) {
            $skipped++;
            continue;
        }
        if ($scope !== 'ALL' && $row['department_applied'] !== $scope) {
            $skipped++;
            continue;
        }

        $updateStmt->execute([$status, $rejectionParam, $session['admin_id'], $id]);

        $historyStmt->execute([
            $id,
            $session['pf_number'],
            $session['department'],
            $status,
            $notesValue,
        ]);

        if (!empty($row['user_id'])) {
            createNotification(
                $pdo,
                (int)$row['user_id'],
                'student',
                'Application Update',
                "Your application status is now: {$status}.",
                '../pages/student_dashboard.php'
            );
        }

        $updated++;
    }

    $pdo->commit();

    ob_end_clean();
    json_response(true, [
        'message' => "Updated {$updated} application(s)",
        'updated' => $updated,
        'skipped' => $skipped,
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Bulk update failed: ' . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Bulk update failed');
}
