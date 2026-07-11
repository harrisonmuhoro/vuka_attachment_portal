<?php
/**
 * Generate Evaluation PDF — Vuka Portal (Feature #4)
 *
 * Streams a one-page PDF summary of an intern's performance evaluation.
 * Auth: admin tier OR the student who owns the submission (verified via
 * submissions.user_id).
 *
 * IMPORTANT: this endpoint streams binary. Method + auth are guarded FIRST and
 * every error path emits an HTTP status + JSON BEFORE any PDF bytes. The success
 * path performs NO buffering / JSON output so nothing precedes the PDF stream.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';

/** Emit a JSON error with status (only used BEFORE any PDF bytes are sent). */
function pdf_error(int $status, string $message): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    pdf_error(405, 'Method not allowed');
}

$sessionManager = $GLOBALS['sessionManager'];
$session = $sessionManager->validateSession();
if (!$session) {
    pdf_error(401, 'Unauthorized: Invalid or expired session');
}

$submissionId = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;
if ($submissionId <= 0) {
    pdf_error(400, 'submission_id is required');
}

try {
    $stmt = $pdo->prepare('SELECT id, user_id, full_name, national_id, email, department_applied,
                                  assigned_role, assigned_station, institution_name, course_applying, status
                           FROM submissions WHERE id = ?');
    $stmt->execute([$submissionId]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Evaluation PDF submission lookup failed: ' . $e->getMessage());
    pdf_error(500, 'Failed to load submission');
}

if (!$submission) {
    pdf_error(404, 'Submission not found');
}

// Authorization: admin tier (department-scoped) OR the owning student.
$isStudent = $sessionManager->isStudent($session);
if ($isStudent) {
    if ((int)$submission['user_id'] !== (int)$session['user_id']) {
        pdf_error(403, 'Forbidden: not your submission');
    }
} else {
    if (!$sessionManager->isAdminTier($session)) {
        pdf_error(403, 'Forbidden: insufficient permissions');
    }
    $scope = $sessionManager->getScopedDepartment($session);
    if ($scope !== 'ALL' && $submission['department_applied'] !== $scope) {
        pdf_error(403, 'Forbidden: outside your department');
    }
}

try {
    $evalStmt = $pdo->prepare('
        SELECT e.*, a.full_name AS evaluator_name, a.pf_number AS evaluator_pf
        FROM evaluations e
        LEFT JOIN admin_users a ON e.evaluator_id = a.id
        WHERE e.submission_id = ?
        LIMIT 1
    ');
    $evalStmt->execute([$submissionId]);
    $evaluation = $evalStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Evaluation PDF eval lookup failed: ' . $e->getMessage());
    pdf_error(500, 'Failed to load evaluation');
}

if (!$evaluation) {
    pdf_error(404, 'No evaluation exists for this submission');
}

// ---- From here on we stream PDF bytes only. No JSON, no buffering. ----
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

$fileName = 'evaluation_' . $submissionId . '.pdf';
$pdfBytes = build_evaluation_pdf($submission, $evaluation);

if (!headers_sent()) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($pdfBytes));
    header('Cache-Control: private, max-age=0, must-revalidate');
}
echo $pdfBytes;
exit;

/**
 * Build the one-page evaluation PDF and return it as a binary string.
 */
function build_evaluation_pdf(array $submission, array $evaluation): string
{
    $labels = [
        'attendance'    => 'Attendance',
        'technical'     => 'Technical Skills',
        'attitude'      => 'Attitude',
        'communication' => 'Communication',
        'initiative'    => 'Initiative',
    ];
    $recLabels = [
        'highly_recommended' => 'Highly Recommended',
        'recommended'        => 'Recommended',
        'not_recommended'    => 'Not Recommended',
    ];

    $clean = function ($v) {
        // FPDF core fonts are latin-1; normalise text safely.
        $v = (string)($v ?? '');
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $v);
    };

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // Header band.
    $pdf->SetFillColor(15, 122, 69); // Vuka green #0F7A45
    $pdf->Rect(0, 0, 210, 26, 'F');
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->SetXY(12, 7);
    $pdf->Cell(0, 8, $clean('Vuka Attachment & Internship Portal'), 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetX(12);
    $pdf->Cell(0, 6, $clean('Intern Performance Evaluation'), 0, 1, 'L');

    $pdf->SetTextColor(33, 37, 41);
    $pdf->Ln(12);

    // Reference line.
    $ref = 'VKP/' . strtoupper($submission['department_applied'] ?? 'GEN') . '/' . $submission['id'];
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 5, $clean('Ref: ' . $ref . '   |   Generated: ' . date('Y-m-d H:i')), 0, 1, 'R');
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Ln(2);

    // Intern details.
    $sectionHeader = function ($title) use ($pdf, $clean) {
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetFillColor(238, 242, 240);
        $pdf->Cell(0, 8, $clean('  ' . $title), 0, 1, 'L', true);
        $pdf->Ln(1);
    };
    $row = function ($label, $value) use ($pdf, $clean) {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(50, 7, $clean($label), 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 7, $clean($value !== '' && $value !== null ? $value : '-'), 0, 1, 'L');
    };

    $sectionHeader('Intern Details');
    $row('Full Name', $submission['full_name'] ?? '');
    $row('National ID', $submission['national_id'] ?? '');
    $row('Institution', $submission['institution_name'] ?? '');
    $row('Course', $submission['course_applying'] ?? '');
    $row('Department', $submission['department_applied'] ?? '');
    $row('Role / Station', trim(($submission['assigned_role'] ?? '') . ' / ' . ($submission['assigned_station'] ?? ''), ' /'));
    $pdf->Ln(3);

    // Scores table.
    $sectionHeader('Performance Scores (1 = Poor, 5 = Excellent)');
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetFillColor(15, 122, 69);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(120, 8, $clean('  Criterion'), 1, 0, 'L', true);
    $pdf->Cell(0, 8, $clean('Score'), 1, 1, 'C', true);
    $pdf->SetTextColor(33, 37, 41);

    $total = 0;
    $count = 0;
    $fill = false;
    foreach ($labels as $key => $label) {
        $score = (int)($evaluation[$key] ?? 0);
        $total += $score;
        $count++;
        $pdf->SetFillColor(247, 249, 248);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(120, 8, $clean('  ' . $label), 1, 0, 'L', $fill);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 8, $clean($score . ' / 5'), 1, 1, 'C', $fill);
        $fill = !$fill;
    }

    // Average row.
    $avg = $count > 0 ? round($total / $count, 1) : 0;
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetFillColor(238, 242, 240);
    $pdf->Cell(120, 8, $clean('  Overall Average'), 1, 0, 'L', true);
    $pdf->Cell(0, 8, $clean($avg . ' / 5'), 1, 1, 'C', true);
    $pdf->Ln(3);

    // Recommendation.
    $sectionHeader('Recommendation');
    $pdf->SetFont('Helvetica', 'B', 12);
    $rec = $recLabels[$evaluation['recommendation'] ?? ''] ?? ($evaluation['recommendation'] ?? '-');
    $pdf->Cell(0, 8, $clean($rec), 0, 1, 'L');
    $pdf->Ln(2);

    // Overall comment.
    $sectionHeader('Overall Comment');
    $pdf->SetFont('Helvetica', '', 10);
    $comment = $evaluation['overall_comment'] ?? '';
    $pdf->MultiCell(0, 6, $clean($comment !== '' && $comment !== null ? $comment : 'No additional comments provided.'));
    $pdf->Ln(4);

    // Evaluator + signature.
    $sectionHeader('Evaluator');
    $evaluatorName = $evaluation['evaluator_name'] ?? 'Supervisor';
    $evaluatorPf = $evaluation['evaluator_pf'] ?? '';
    $submittedAt = $evaluation['submitted_at'] ?? '';
    $row('Evaluated By', $evaluatorName . ($evaluatorPf ? ' (' . $evaluatorPf . ')' : ''));
    $row('Date', $submittedAt);

    $out = $pdf->Output('S');
    return (string)$out;
}
