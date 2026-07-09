<?php
/**
 * Update Submission Status Endpoint
 * POST /api/update-submission.php
 * 
 * Used by admins to change submission status and add review notes
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
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data === null) {
        ob_end_clean();
        json_response(false, null, 'Invalid JSON input');
    }
    
    // Support both frontend field name conventions
    $submissionId = $data['submission_id'] ?? $data['submissionId'] ?? null;
    $newStatus = $data['status'] ?? null;
    $rejectionReason = $data['rejection_reason'] ?? $data['rejectionReason'] ?? '';
    $reviewNotes = $data['review_notes'] ?? $data['reviewNotes'] ?? '';
    $pfNumber = $data['pf_number'] ?? $data['pfNumber'] ?? '';
    $department = $data['department'] ?? '';
    $assignedRole = $data['assigned_role'] ?? $data['assignedRole'] ?? null;
    $assignedStation = $data['assigned_station'] ?? $data['assignedStation'] ?? null;
    
    if (empty($submissionId)) {
        ob_end_clean();
        json_response(false, null, 'Missing submission ID');
    }
    
    // If only saving review notes (no status change)
    if (empty($newStatus) && !empty($reviewNotes)) {
        // Get admin info from token if pfNumber missing
        if (empty($pfNumber)) {
            $session = $GLOBALS['sessionManager']->validateSession();
            if ($session && $GLOBALS['sessionManager']->isAdminTier($session)) {
                $pfNumber = $session['pf_number'];
                $department = $session['department'];
            }
        }

        // Add review note to history
        $stmt = $pdo->prepare("
            INSERT INTO review_history (submission_id, reviewer_pf, reviewer_department, status, notes)
            VALUES (?, ?, ?, 'note', ?)
        ");
        $stmt->execute([$submissionId, $pfNumber, $department, $reviewNotes]);
        
        ob_end_clean();
        json_response(true, ['message' => 'Review notes saved successfully']);
    }
    
    // Status update flow
    if (empty($newStatus)) {
        ob_end_clean();
        json_response(false, null, 'Missing status');
    }
    
    $validStatuses = ['not_applied', 'applied', 'pending', 'accepted', 'rejected', 'deployed', 'ongoing',
                       'pending_review', 'approved']; // keep old values for backwards compat
    if (!in_array($newStatus, $validStatuses)) {
        ob_end_clean();
        json_response(false, null, 'Invalid status value');
    }
    
    // Map old status names to new ones
    if ($newStatus === 'pending_review') $newStatus = 'pending';
    if ($newStatus === 'approved') $newStatus = 'accepted';
    
    if ($newStatus === 'rejected' && empty($rejectionReason)) {
        ob_end_clean();
        json_response(false, null, 'Rejection reason is required');
    }
    
    // Verify submission exists
    $stmt = $pdo->prepare("SELECT id, department_applied, intern_pf_number, application_type FROM submissions WHERE id = ?");
    $stmt->execute([$submissionId]);
    $submission = $stmt->fetch();
    
    if (!$submission) {
        ob_end_clean();
        json_response(false, null, 'Submission not found');
    }
    
    // Get admin info from token if pfNumber missing
    if (empty($pfNumber)) {
        $session = $GLOBALS['sessionManager']->validateSession();
        if ($session && $GLOBALS['sessionManager']->isAdminTier($session)) {
            $pfNumber = $session['pf_number'];
            $department = $session['department'];
        }
    }

    // Auto-generate intern PF number when accepted — ONLY for internships
    $internPf = $submission['intern_pf_number']; // keep existing if already set
    $appType = $submission['application_type'] ?? 'attachment';
    if ($newStatus === 'accepted' && empty($internPf) && $appType === 'internship') {
        // 12-digit PF: yymmddHHmmss + 2 random digits
        $internPf = date('ymdHis') . sprintf('%02d', rand(0, 99));
        // Ensure 12 digits
        $internPf = substr($internPf, 0, 12);
    }
    
    // Update submission status
    $stmt = $pdo->prepare("
        UPDATE submissions 
        SET status = ?, 
            rejection_reason = ?,
            intern_pf_number = ?,
            assigned_role = COALESCE(?, assigned_role),
            assigned_station = COALESCE(?, assigned_station),
            last_updated_by = ?,
            last_updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $rejectionReason, $internPf, $assignedRole, $assignedStation, $pfNumber, $submissionId]);
    
    // Add to review history
    $notes = $reviewNotes ?: ($newStatus === 'rejected' ? "Rejected: $rejectionReason" : "Status changed to: $newStatus");
    $stmt = $pdo->prepare("
        INSERT INTO review_history (submission_id, reviewer_pf, reviewer_department, status, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$submissionId, $pfNumber, $department, $newStatus, $notes]);
    
    $responseData = ['message' => "Status updated to $newStatus"];
    if ($internPf) $responseData['intern_pf_number'] = $internPf;
    
    ob_end_clean();
    json_response(true, $responseData);
    
} catch (Exception $e) {
    error_log("Update submission error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Update failed: ' . $e->getMessage());
}
?>
