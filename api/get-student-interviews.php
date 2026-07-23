<?php
/**
 * Get Student Interviews — Vuka Portal (Feature #3)
 * GET /api/get-student-interviews.php
 *
 * Returns the logged-in student's interview slots (across all of their
 * submissions), most-recent / upcoming first, each with the student's latest
 * response if any. Ownership is enforced via submissions.user_id.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

$session = requireStudent();
$userId  = (int)$session['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT
            sl.id,
            sl.submission_id,
            sl.scheduled_at,
            sl.location,
            sl.notes,
            sl.status,
            sl.created_at,
            sl.updated_at,
            s.department_applied AS department,
            s.status             AS submission_status,
            ir.response          AS student_response,
            ir.student_notes     AS student_response_notes,
            ir.responded_at      AS responded_at
        FROM interview_slots sl
        INNER JOIN submissions s ON s.id = sl.submission_id
        LEFT JOIN interview_responses ir
               ON ir.id = (
                    SELECT ir2.id FROM interview_responses ir2
                    WHERE ir2.slot_id = sl.id
                    ORDER BY ir2.responded_at DESC, ir2.id DESC
                    LIMIT 1
               )
        WHERE s.user_id = ?
          AND sl.status <> 'cancelled'
        ORDER BY sl.scheduled_at DESC
    ");
    $stmt->execute([$userId]);
    $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    json_response(true, [
        'interviews' => $slots,
        'count'      => count($slots),
    ]);
} catch (Exception $e) {
    error_log('Get student interviews error: ' . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to load interviews');
}
