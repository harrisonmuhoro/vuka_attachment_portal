<?php
/**
 * PDF Letter Generation — Vuka Portal (Feature #2)
 * GET /api/generate-letter.php?submission_id=12&type=offer
 *
 * Streams a formatted PDF letter for a submission:
 *   - offer      → Offer of Attachment/Internship
 *   - rejection  → Application Outcome
 *   - deployment → Deployment Notification
 *
 * RBAC: any admin tier (super_admin, hr_coordinator, department_supervisor).
 * Department-scoped: non-ALL scopes may only generate letters for
 * submissions whose department_applied matches their department name.
 *
 * NOTE: this endpoint streams binary PDF, so it does NOT use ob_start /
 * json_response for the success path — auth + validation happen first and
 * no output precedes the PDF stream.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

// ---- Method guard (GET only) ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method not allowed';
    exit;
}

// ---- Auth guard (admin tier) ----------------------------------------------
$session = requireAnyAdmin();
$scope   = $GLOBALS['sessionManager']->getScopedDepartment($session);

// ---- Input validation ------------------------------------------------------
$submissionId = (int)($_GET['submission_id'] ?? 0);
$type         = strtolower(trim((string)($_GET['type'] ?? 'offer')));

if ($submissionId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing or invalid submission_id';
    exit;
}

if (!in_array($type, ['offer', 'rejection', 'deployment'], true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid letter type';
    exit;
}

try {
    // Fetch submission + vacancy title. submissions already holds the
    // applicant details directly — no users table join needed.
    $stmt = $pdo->prepare("
        SELECT s.id, s.national_id, s.full_name, s.email, s.course_applying,
               s.institution_name, s.department_applied, s.application_type,
               s.status, s.assigned_role, s.assigned_station,
               v.title AS vacancy_title
        FROM submissions s
        LEFT JOIN vacancies v ON s.vacancy_id = v.id
        WHERE s.id = ?
        LIMIT 1
    ");
    $stmt->execute([$submissionId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Submission not found';
        exit;
    }

    // Department scoping — non-ALL scopes are locked to their own department.
    if ($scope !== 'ALL' && $data['department_applied'] !== $scope) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden: submission is outside your department';
        exit;
    }

    buildPDF($data, $type, $session);
    exit;

} catch (Exception $e) {
    error_log('Generate letter error: ' . $e->getMessage());
    // Nothing has been streamed yet on the failure path.
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Failed to generate letter';
    exit;
}

/**
 * Build and stream the PDF letter as a forced download.
 *
 * @param array  $d       Submission row (+ vacancy_title).
 * @param string $type    offer | rejection | deployment.
 * @param array  $session Authenticated admin session.
 */
function buildPDF(array $d, string $type, array $session): void
{
    $dept        = $d['department_applied'] ?: 'GENERAL';
    $vacancy     = $d['vacancy_title'] ?: ($d['course_applying'] ?: 'the advertised position');
    $role        = $d['assigned_role'] ?: 'Attaché / Intern';
    $station     = $d['assigned_station'] ?: 'To be advised';
    $fullName    = $d['full_name'] ?: 'Applicant';
    $nationalId  = $d['national_id'] ?: 'N/A';

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetMargins(25, 25, 25);
    $pdf->SetAutoPageBreak(true, 25);

    // ---- Header --------------------------------------------------------
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->SetTextColor(30, 58, 95); // dark blue
    $pdf->Cell(0, 10, 'VUKA ATTACHMENT & INTERNSHIP PORTAL', 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 6, date('d F Y'), 0, 1, 'C');
    $pdf->Ln(8);

    // ---- Reference line ------------------------------------------------
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'REF: VKP/' . strtoupper($dept) . '/' . $d['id'], 0, 1);
    $pdf->Ln(4);

    // ---- Addressee -----------------------------------------------------
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, $fullName, 0, 1);
    $pdf->Cell(0, 6, 'National ID: ' . $nationalId, 0, 1);
    $pdf->Ln(6);

    // ---- Title ---------------------------------------------------------
    $titles = [
        'offer'      => 'OFFER OF ATTACHMENT/INTERNSHIP',
        'rejection'  => 'APPLICATION OUTCOME',
        'deployment' => 'DEPLOYMENT NOTIFICATION',
    ];
    $pdf->SetFont('Helvetica', 'BU', 11);
    $pdf->Cell(0, 8, $titles[$type], 0, 1);
    $pdf->Ln(3);

    // ---- Body ----------------------------------------------------------
    $pdf->SetFont('Helvetica', '', 10);

    if ($type === 'offer') {
        $body = "Dear {$fullName},\n\n" .
            "We are pleased to inform you that your application for the position of " .
            "{$vacancy} in the {$dept} Department has been successful. You are hereby " .
            "offered an attachment/internship placement.\n\n" .
            "Please report to the {$dept} Department with this letter and all original " .
            "documents as previously submitted. We look forward to welcoming you.";
    } elseif ($type === 'rejection') {
        $body = "Dear {$fullName},\n\n" .
            "Thank you for your interest in the {$vacancy} position in the {$dept} " .
            "Department. After careful review of all applications received, we regret " .
            "to inform you that your application was not successful at this time.\n\n" .
            "We appreciate the effort you invested and encourage you to apply again in " .
            "future intake periods.";
    } else { // deployment
        $body = "This is to confirm that {$fullName} (National ID: {$nationalId}) has " .
            "been officially deployed to the {$dept} Department as {$role}.\n\n" .
            "Workstation/Office: {$station}\n" .
            "Effective Date: " . date('d F Y') . "\n\n" .
            "The above named should report to the assigned station and adhere to all " .
            "departmental policies for the duration of the placement.";
    }

    // Latin-1 conversion: FPDF core fonts expect ISO-8859-1, not UTF-8.
    $pdf->MultiCell(0, 6, pdf_text($body));

    // ---- Signature block ----------------------------------------------
    $pdf->Ln(12);
    $pdf->Cell(0, 6, 'Authorized by:', 0, 1);
    $pdf->Ln(12);
    $pdf->Cell(0, 6, '____________________________', 0, 1);

    $signerName = $session['admin_name'] ?? 'Authorized Officer';
    $signerRole = signer_role_label($session['role_name'] ?? '');
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(0, 6, pdf_text($signerName), 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, pdf_text($signerRole), 0, 1);
    $pdf->Cell(0, 6, 'Vuka Attachment & Internship Portal', 0, 1);

    // ---- Stream as download -------------------------------------------
    $filename = $type . '_letter_' . $d['id'] . '.pdf';
    $pdf->Output('D', $filename);
}

/**
 * Map an internal role name to a human-readable signer title.
 */
function signer_role_label(string $roleName): string
{
    switch ($roleName) {
        case 'super_admin':
            return 'System Administrator';
        case 'hr_coordinator':
            return 'HR Coordinator';
        case 'department_supervisor':
            return 'Department Supervisor';
        default:
            return 'Authorized Officer';
    }
}

/**
 * Convert UTF-8 text to the Latin-1 (Windows-1252) encoding FPDF core
 * fonts require, so accented characters do not corrupt the stream.
 */
function pdf_text(string $text): string
{
    return iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: $text;
}
