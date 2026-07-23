<?php
/**
 * Interview Slots — Vuka Portal (Feature #3)
 *
 * GET                       -> list interview slots (department-scoped) + latest student response
 * POST action=create        -> create a slot for a submission, move it to 'interview', notify + email
 * POST action=update_status -> cancel / reschedule / complete a slot (scope-checked)
 *
 * Auth: any admin-tier role (supervisor OR HR OR super_admin). Department
 * scoping is enforced via submissions.department_applied (NAME string).
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/notifications.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/email-templates.php';

$session = requireAnyAdmin();
$scope   = $GLOBALS['sessionManager']->getScopedDepartment($session);

$method = $_SERVER['REQUEST_METHOD'];

/* ------------------------------------------------------------------ */
/* GET — list slots visible to this admin                             */
/* ------------------------------------------------------------------ */
if ($method === 'GET') {
    try {
        // Latest response per slot via a correlated subquery join.
        $sql = "
            SELECT
                sl.id,
                sl.submission_id,
                sl.scheduled_at,
                sl.location,
                sl.notes,
                sl.status,
                sl.created_by,
                sl.created_at,
                sl.updated_at,
                s.full_name       AS student_name,
                s.national_id     AS national_id,
                s.email           AS student_email,
                s.department_applied AS department,
                s.status          AS submission_status,
                ir.response       AS student_response,
                ir.student_notes  AS student_response_notes,
                ir.responded_at   AS responded_at
            FROM interview_slots sl
            INNER JOIN submissions s ON s.id = sl.submission_id
            LEFT JOIN interview_responses ir
                   ON ir.id = (
                        SELECT ir2.id FROM interview_responses ir2
                        WHERE ir2.slot_id = sl.id
                        ORDER BY ir2.responded_at DESC, ir2.id DESC
                        LIMIT 1
                   )
        ";

        $params = [];
        if ($scope !== 'ALL') {
            $sql .= " WHERE s.department_applied = ?";
            $params[] = $scope;
        }
        $sql .= " ORDER BY sl.scheduled_at ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_end_clean();
        json_response(true, ['slots' => $slots, 'count' => count($slots)]);
    } catch (Exception $e) {
        error_log('Interview slots list error: ' . $e->getMessage());
        ob_end_clean();
        json_response(false, null, 'Failed to load interview slots');
    }
}

/* ------------------------------------------------------------------ */
/* POST                                                               */
/* ------------------------------------------------------------------ */
if ($method !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$action = isset($input['action']) ? trim((string)$input['action']) : '';

/* ---- helper: fetch a slot + its submission, enforcing scope ------- */
function fetchScopedSlot(PDO $pdo, int $slotId, string $scope): ?array {
    $stmt = $pdo->prepare("
        SELECT sl.id, sl.submission_id, sl.status,
               s.user_id, s.full_name, s.email, s.department_applied
        FROM interview_slots sl
        INNER JOIN submissions s ON s.id = sl.submission_id
        WHERE sl.id = ?
        LIMIT 1
    ");
    $stmt->execute([$slotId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    if ($scope !== 'ALL' && $row['department_applied'] !== $scope) {
        return null; // out of scope — treat as not found
    }
    return $row;
}

/* ================= CREATE ========================================= */
if ($action === 'create') {
    $submissionId = (int)($input['submission_id'] ?? $input['submissionId'] ?? 0);
    $scheduledAt  = trim((string)($input['scheduled_at'] ?? $input['scheduledAt'] ?? ''));
    $location     = trim((string)($input['location'] ?? ''));
    $notes        = trim((string)($input['notes'] ?? ''));

    if ($submissionId <= 0) {
        ob_end_clean();
        json_response(false, null, 'Missing submission ID');
    }
    if ($scheduledAt === '') {
        ob_end_clean();
        json_response(false, null, 'Interview date/time is required');
    }
    if ($location === '') {
        ob_end_clean();
        json_response(false, null, 'Interview location is required');
    }

    // Normalise 'YYYY-MM-DDTHH:MM' (datetime-local) -> 'YYYY-MM-DD HH:MM:SS'
    $ts = strtotime($scheduledAt);
    if ($ts === false) {
        ob_end_clean();
        json_response(false, null, 'Invalid date/time format');
    }
    $scheduledAtSql = date('Y-m-d H:i:s', $ts);

    try {
        // Verify submission exists and is in scope.
        $stmt = $pdo->prepare("SELECT id, user_id, full_name, email, department_applied FROM submissions WHERE id = ? LIMIT 1");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sub) {
            ob_end_clean();
            json_response(false, null, 'Application not found');
        }
        if ($scope !== 'ALL' && $sub['department_applied'] !== $scope) {
            ob_end_clean();
            json_response(false, null, 'You are not authorised to schedule for this department');
        }

        $pdo->beginTransaction();

        $ins = $pdo->prepare("
            INSERT INTO interview_slots (submission_id, scheduled_at, location, notes, status, created_by)
            VALUES (?, ?, ?, ?, 'scheduled', ?)
        ");
        $ins->execute([
            $submissionId,
            $scheduledAtSql,
            $location,
            ($notes === '' ? null : $notes),
            (int)$session['admin_id'],
        ]);
        $slotId = (int)$pdo->lastInsertId();

        // Advance submission to 'interview'.
        $pdo->prepare("UPDATE submissions SET status = 'interview', last_updated_by = ?, last_updated_at = NOW() WHERE id = ?")
            ->execute([(int)$session['admin_id'], $submissionId]);

        // Audit trail.
        $pdo->prepare("INSERT INTO review_history (submission_id, reviewer_pf, reviewer_department, status, notes)
                       VALUES (?, ?, ?, 'interview', ?)")
            ->execute([
                $submissionId,
                $session['pf_number'],
                $session['department'],
                "Interview scheduled for {$scheduledAtSql} at {$location}",
            ]);

        $pdo->commit();

        // Notify + email the student (best-effort, outside the transaction).
        $whenText   = date('l, j M Y \a\t g:i A', $ts);
        $deptName   = $sub['department_applied'] ?: 'the';
        if (!empty($sub['user_id'])) {
            createNotification(
                $pdo,
                (int)$sub['user_id'],
                'student',
                'Interview Scheduled',
                "You have an interview on {$whenText} at {$location}. Please confirm or flag a conflict.",
                '../pages/student_dashboard.php'
            );
        }
        if (!empty($sub['email'])) {
            $html = emailInterviewScheduled((string)$sub['full_name'], $whenText, $location, (string)$deptName);
            sendMail((string)$sub['email'], (string)$sub['full_name'], 'Interview Invitation — Vuka Portal', $html);
        }

        ob_end_clean();
        json_response(true, [
            'slot_id' => $slotId,
            'message' => 'Interview scheduled and the applicant has been notified.',
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Interview create error: ' . $e->getMessage());
        ob_end_clean();
        json_response(false, null, 'Failed to schedule interview');
    }
}

/* ================= UPDATE STATUS ================================== */
if ($action === 'update_status') {
    $slotId    = (int)($input['slot_id'] ?? $input['slotId'] ?? 0);
    $newStatus = trim((string)($input['status'] ?? ''));
    $notes     = trim((string)($input['notes'] ?? ''));
    // Optional reschedule payload.
    $newWhen   = trim((string)($input['scheduled_at'] ?? ''));
    $newLoc    = trim((string)($input['location'] ?? ''));

    $allowed = ['cancelled', 'rescheduled', 'completed'];

    if ($slotId <= 0) {
        ob_end_clean();
        json_response(false, null, 'Missing slot ID');
    }
    if (!in_array($newStatus, $allowed, true)) {
        ob_end_clean();
        json_response(false, null, 'Invalid status. Allowed: cancelled, rescheduled, completed');
    }

    try {
        $slot = fetchScopedSlot($pdo, $slotId, $scope);
        if (!$slot) {
            ob_end_clean();
            json_response(false, null, 'Interview slot not found');
        }

        $pdo->beginTransaction();

        if ($newStatus === 'rescheduled') {
            $sets   = "status = 'rescheduled', updated_at = NOW()";
            $params = [];
            if ($newWhen !== '') {
                $ts = strtotime($newWhen);
                if ($ts === false) {
                    $pdo->rollBack();
                    ob_end_clean();
                    json_response(false, null, 'Invalid reschedule date/time');
                }
                $sets .= ", scheduled_at = ?";
                $params[] = date('Y-m-d H:i:s', $ts);
            }
            if ($newLoc !== '') {
                $sets .= ", location = ?";
                $params[] = $newLoc;
            }
            $params[] = $slotId;
            $pdo->prepare("UPDATE interview_slots SET {$sets} WHERE id = ?")->execute($params);
        } else {
            $pdo->prepare("UPDATE interview_slots SET status = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newStatus, $slotId]);
        }

        // Reflect terminal states on the submission where it makes sense.
        // 'completed' interview -> submission stays 'interview' (admin decides next);
        // 'cancelled' -> revert submission to 'accepted' so it can be re-scheduled.
        if ($newStatus === 'cancelled') {
            $pdo->prepare("UPDATE submissions SET status = 'accepted', last_updated_by = ?, last_updated_at = NOW()
                           WHERE id = ? AND status = 'interview'")
                ->execute([(int)$session['admin_id'], (int)$slot['submission_id']]);
        }

        // Audit trail on the submission.
        $historyStatus = ($newStatus === 'cancelled') ? 'accepted' : 'interview';
        $noteText = $notes !== '' ? $notes : "Interview {$newStatus}";
        $pdo->prepare("INSERT INTO review_history (submission_id, reviewer_pf, reviewer_department, status, notes)
                       VALUES (?, ?, ?, ?, ?)")
            ->execute([
                (int)$slot['submission_id'],
                $session['pf_number'],
                $session['department'],
                $historyStatus,
                $noteText,
            ]);

        $pdo->commit();

        // Best-effort notification.
        if (!empty($slot['user_id'])) {
            createNotification(
                $pdo,
                (int)$slot['user_id'],
                'student',
                'Interview Update',
                "Your interview has been marked: {$newStatus}.",
                '../pages/student_dashboard.php'
            );
        }

        ob_end_clean();
        json_response(true, ['message' => "Interview {$newStatus}."]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Interview update_status error: ' . $e->getMessage());
        ob_end_clean();
        json_response(false, null, 'Failed to update interview');
    }
}

ob_end_clean();
json_response(false, null, 'Unknown action');
