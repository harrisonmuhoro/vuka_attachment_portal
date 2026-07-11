<?php
/**
 * Interview Response — Vuka Portal (Feature #3)
 * POST /api/interview-response.php
 *   { "slot_id": 5, "response": "confirmed"|"conflict", "student_notes": "..." }
 *
 * A student confirms attendance or flags a conflict for THEIR OWN interview
 * slot. Ownership is verified through submissions.user_id.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

$session = requireStudent();
$userId  = (int)$session['user_id'];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $slotId   = (int)($input['slot_id'] ?? $input['slotId'] ?? 0);
    $response = trim((string)($input['response'] ?? ''));
    $notes    = trim((string)($input['student_notes'] ?? $input['studentNotes'] ?? ''));

    if ($slotId <= 0) {
        ob_end_clean();
        json_response(false, null, 'Missing slot ID');
    }
    if (!in_array($response, ['confirmed', 'conflict'], true)) {
        ob_end_clean();
        json_response(false, null, 'Response must be "confirmed" or "conflict"');
    }

    // Verify the slot belongs to one of this student's submissions.
    $stmt = $pdo->prepare("
        SELECT sl.id, sl.status, s.user_id, s.id AS submission_id, s.department_applied
        FROM interview_slots sl
        INNER JOIN submissions s ON s.id = sl.submission_id
        WHERE sl.id = ? AND s.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$slotId, $userId]);
    $slot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$slot) {
        ob_end_clean();
        json_response(false, null, 'Interview not found');
    }

    if (in_array($slot['status'], ['cancelled', 'completed'], true)) {
        ob_end_clean();
        json_response(false, null, 'This interview can no longer be responded to.');
    }

    $pdo->beginTransaction();

    // Record the response.
    $pdo->prepare("INSERT INTO interview_responses (slot_id, response, student_notes)
                   VALUES (?, ?, ?)")
        ->execute([$slotId, $response, ($notes === '' ? null : $notes)]);

    // Mirror onto the slot: confirmed attendance -> 'confirmed'.
    // A conflict does not auto-reschedule; the admin acts on it.
    if ($response === 'confirmed') {
        $pdo->prepare("UPDATE interview_slots SET status = 'confirmed', updated_at = NOW()
                       WHERE id = ? AND status IN ('scheduled','rescheduled')")
            ->execute([$slotId]);
    }

    $pdo->commit();

    // Notify department admins so a conflict/confirmation is visible.
    $title = $response === 'confirmed' ? 'Interview Confirmed' : 'Interview Conflict Flagged';
    $body  = $response === 'confirmed'
        ? ($session['full_name'] ?? 'A student') . ' confirmed their interview attendance.'
        : ($session['full_name'] ?? 'A student') . ' flagged a conflict with their interview.';
    if (!empty($slot['department_applied'])) {
        notifyDepartmentAdmins(
            $pdo,
            (string)$slot['department_applied'],
            $title,
            $body,
            '../pages/supervisor_dashboard.php'
        );
    }

    ob_end_clean();
    json_response(true, [
        'message' => $response === 'confirmed'
            ? 'Attendance confirmed. Thank you.'
            : 'Conflict flagged. The department will follow up with a new time.',
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Interview response error: ' . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to submit response');
}
