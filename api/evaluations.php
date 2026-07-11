<?php
/**
 * Intern Performance Evaluation API — Vuka Portal (Feature #4)
 *
 * POST (department_supervisor): submit/upsert an evaluation for a submission whose
 *   attachment is 'ongoing' or 'completed', scoped to the supervisor's department.
 *   One evaluation per submission (UNIQUE submission_id). On submit, the submission
 *   status is moved to 'completed'. Student is notified (+ optional email).
 *
 * GET (admin tier OR the student who owns the submission): fetch the evaluation
 *   for a submission_id, scope-checked.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/notifications.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/email-templates.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleGet($pdo);
} elseif ($method === 'POST') {
    handlePost($pdo);
} else {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

/**
 * GET — return the evaluation for a submission_id.
 * Admin tier: scoped by department. Student: only their own submission.
 */
function handleGet(PDO $pdo)
{
    $sessionManager = $GLOBALS['sessionManager'];
    $session = $sessionManager->validateSession();

    if (!$session) {
        ob_end_clean();
        http_response_code(401);
        json_response(false, null, 'Unauthorized: Invalid or expired session');
    }

    $submissionId = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;
    if ($submissionId <= 0) {
        ob_end_clean();
        json_response(false, null, 'submission_id is required');
    }

    try {
        $stmt = $pdo->prepare('SELECT id, user_id, full_name, department_applied, status FROM submissions WHERE id = ?');
        $stmt->execute([$submissionId]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$submission) {
            ob_end_clean();
            http_response_code(404);
            json_response(false, null, 'Submission not found');
        }

        // Scope check.
        $isStudent = $sessionManager->isStudent($session);
        if ($isStudent) {
            if ((int)$submission['user_id'] !== (int)$session['user_id']) {
                ob_end_clean();
                http_response_code(403);
                json_response(false, null, 'Forbidden: not your submission');
            }
        } else {
            if (!$sessionManager->isAdminTier($session)) {
                ob_end_clean();
                http_response_code(403);
                json_response(false, null, 'Forbidden: insufficient permissions');
            }
            $scope = $sessionManager->getScopedDepartment($session);
            if ($scope !== 'ALL' && $submission['department_applied'] !== $scope) {
                ob_end_clean();
                http_response_code(403);
                json_response(false, null, 'Forbidden: outside your department');
            }
        }

        $evalStmt = $pdo->prepare('
            SELECT e.id, e.submission_id, e.evaluator_id, e.attendance, e.technical,
                   e.attitude, e.communication, e.initiative, e.overall_comment,
                   e.recommendation, e.submitted_at,
                   a.full_name AS evaluator_name, a.pf_number AS evaluator_pf
            FROM evaluations e
            LEFT JOIN admin_users a ON e.evaluator_id = a.id
            WHERE e.submission_id = ?
            LIMIT 1
        ');
        $evalStmt->execute([$submissionId]);
        $evaluation = $evalStmt->fetch(PDO::FETCH_ASSOC);

        ob_end_clean();
        if (!$evaluation) {
            json_response(true, [
                'evaluation' => null,
                'has_evaluation' => false,
                'submission' => [
                    'id' => (int)$submission['id'],
                    'full_name' => $submission['full_name'],
                    'department_applied' => $submission['department_applied'],
                    'status' => $submission['status'],
                ],
            ]);
        }

        // Cast score fields to int for clean JSON.
        foreach (['attendance', 'technical', 'attitude', 'communication', 'initiative'] as $k) {
            $evaluation[$k] = (int)$evaluation[$k];
        }

        json_response(true, [
            'evaluation' => $evaluation,
            'has_evaluation' => true,
            'submission' => [
                'id' => (int)$submission['id'],
                'full_name' => $submission['full_name'],
                'department_applied' => $submission['department_applied'],
                'status' => $submission['status'],
            ],
        ]);
    } catch (Exception $e) {
        error_log('Evaluation GET failed: ' . $e->getMessage());
        ob_end_clean();
        json_response(false, null, 'Failed to load evaluation');
    }
}

/**
 * POST — submit/upsert an evaluation (department_supervisor only).
 */
function handlePost(PDO $pdo)
{
    $session = requireSupervisor();
    $sessionManager = $GLOBALS['sessionManager'];
    $scope = $sessionManager->getScopedDepartment($session);

    // Accept JSON body, fall back to form-encoded ($_POST).
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $submissionId = isset($input['submission_id']) ? (int)$input['submission_id'] : 0;
    if ($submissionId <= 0) {
        ob_end_clean();
        json_response(false, null, 'submission_id is required');
    }

    $criteria = ['attendance', 'technical', 'attitude', 'communication', 'initiative'];
    $scores = [];
    foreach ($criteria as $c) {
        $val = isset($input[$c]) ? (int)$input[$c] : 0;
        if ($val < 1 || $val > 5) {
            ob_end_clean();
            json_response(false, null, "Invalid score for {$c} (must be 1-5)");
        }
        $scores[$c] = $val;
    }

    $overallComment = isset($input['overall_comment']) ? trim((string)$input['overall_comment']) : '';
    $recommendation = isset($input['recommendation']) ? trim((string)$input['recommendation']) : '';
    $allowedRecommendations = ['highly_recommended', 'recommended', 'not_recommended'];
    if (!in_array($recommendation, $allowedRecommendations, true)) {
        ob_end_clean();
        json_response(false, null, 'Invalid recommendation value');
    }

    try {
        $stmt = $pdo->prepare('SELECT id, user_id, full_name, email, department_applied, status FROM submissions WHERE id = ?');
        $stmt->execute([$submissionId]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$submission) {
            ob_end_clean();
            http_response_code(404);
            json_response(false, null, 'Submission not found');
        }

        // Department scope check.
        if ($scope !== 'ALL' && $submission['department_applied'] !== $scope) {
            ob_end_clean();
            http_response_code(403);
            json_response(false, null, 'Forbidden: submission is outside your department');
        }

        // Attachment must be ongoing or completed to be evaluated.
        if (!in_array($submission['status'], ['ongoing', 'completed'], true)) {
            ob_end_clean();
            json_response(false, null, 'Intern must be ongoing or completed to be evaluated');
        }

        $pdo->beginTransaction();

        // Upsert (UNIQUE submission_id) — gracefully updates an existing evaluation.
        $upsert = $pdo->prepare('
            INSERT INTO evaluations
                (submission_id, evaluator_id, attendance, technical, attitude,
                 communication, initiative, overall_comment, recommendation)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                evaluator_id    = VALUES(evaluator_id),
                attendance      = VALUES(attendance),
                technical       = VALUES(technical),
                attitude        = VALUES(attitude),
                communication   = VALUES(communication),
                initiative      = VALUES(initiative),
                overall_comment = VALUES(overall_comment),
                recommendation  = VALUES(recommendation)
        ');
        $upsert->execute([
            $submissionId,
            $session['admin_id'],
            $scores['attendance'],
            $scores['technical'],
            $scores['attitude'],
            $scores['communication'],
            $scores['initiative'],
            ($overallComment === '' ? null : $overallComment),
            $recommendation,
        ]);

        // Move submission to 'completed' on evaluation submit.
        $statusChanged = false;
        if ($submission['status'] !== 'completed') {
            $pdo->prepare("UPDATE submissions SET status = 'completed', last_updated_by = ?, last_updated_at = NOW() WHERE id = ?")
                ->execute([$session['admin_id'], $submissionId]);
            $statusChanged = true;

            // Mirror to review_history (same columns as bulk-update-submissions.php).
            $pdo->prepare('INSERT INTO review_history (submission_id, reviewer_pf, reviewer_department, status, notes) VALUES (?, ?, ?, ?, ?)')
                ->execute([
                    $submissionId,
                    $session['pf_number'],
                    $session['department'],
                    'completed',
                    'Attachment completed — performance evaluation submitted.',
                ]);
        }

        $pdo->commit();

        // Notify + email student (best-effort, never breaks the primary action).
        if (!empty($submission['user_id'])) {
            createNotification(
                $pdo,
                (int)$submission['user_id'],
                'student',
                'Performance Evaluation Available',
                'Your supervisor has submitted your attachment performance evaluation. You can now view and download it.',
                '../pages/student_dashboard.php'
            );

            if (!empty($submission['email'])) {
                try {
                    $html = emailStatusChanged(
                        $submission['full_name'] ?? 'Student',
                        'completed',
                        $submission['department_applied'] ?? 'your'
                    );
                    sendMail($submission['email'], $submission['full_name'] ?? 'Student', 'Your Attachment Evaluation Is Ready', $html);
                } catch (Exception $mailEx) {
                    error_log('Evaluation email failed: ' . $mailEx->getMessage());
                }
            }
        }

        ob_end_clean();
        json_response(true, [
            'message' => 'Evaluation submitted successfully',
            'submission_id' => $submissionId,
            'status_changed_to_completed' => $statusChanged,
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Evaluation POST failed: ' . $e->getMessage());
        ob_end_clean();
        json_response(false, null, 'Failed to submit evaluation');
    }
}
