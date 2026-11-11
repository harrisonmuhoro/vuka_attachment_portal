<?php
/** Authenticated applicant application submission with private BLOB storage. */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/notifications.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed', 405);
}

try {
    $session = requireStudent();
    $userId = (int)($session['user_id'] ?? 0);
    if ($userId <= 0) {
        ob_end_clean();
        json_response(false, null, 'Applicant account is unavailable', 401);
    }

    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        ob_end_clean();
        json_response(false, null, 'Upload exceeds the server request limit', 413);
    }

    // Fetch authoritative identity and lock the account row to serialize duplicate submissions.
    $pdo->beginTransaction();
    $lockClause = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare("SELECT id, national_id, full_name, email, verified, status FROM users WHERE id = ?{$lockClause}");
    $stmt->execute([$userId]);
    $applicant = $stmt->fetch();
    if (!$applicant || $applicant['status'] !== 'active' || (int)$applicant['verified'] !== 1) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'An active, verified applicant account is required', 403);
    }

    $providedNationalId = trim((string)($_POST['national_id'] ?? ''));
    if ($providedNationalId !== '' && !hash_equals((string)$applicant['national_id'], $providedNationalId)) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'Applicant identity does not match the signed-in account', 403);
    }

    $stmt = $pdo->prepare('SELECT id FROM submissions WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'You have already submitted an application', 409);
    }

    $vacancyId = isset($_POST['vacancy_id']) && $_POST['vacancy_id'] !== '' ? (int)$_POST['vacancy_id'] : null;
    $applicationType = trim((string)($_POST['application_type'] ?? 'attachment'));
    $departmentApplied = trim((string)($_POST['department_applied'] ?? ''));
    if (!in_array($applicationType, ['attachment', 'internship'], true)) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'Invalid application type', 422);
    }

    if ($vacancyId !== null) {
        $stmt = $pdo->prepare("SELECT department_name, vacancy_type, status, deadline_at FROM vacancies WHERE id = ?{$lockClause} LIMIT 1");
        $stmt->execute([$vacancyId]);
        $vacancy = $stmt->fetch();
        if (!$vacancy || $vacancy['status'] !== 'approved'
            || (!empty($vacancy['deadline_at']) && strtotime($vacancy['deadline_at']) < time())) {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'This vacancy is not accepting applications', 422);
        }
        $departmentApplied = (string)$vacancy['department_name'];
        $applicationType = (string)$vacancy['vacancy_type'];
    } else {
        if ($departmentApplied === '') {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'Choose a department before applying', 422);
        }
        $stmt = $pdo->prepare('SELECT name FROM departments WHERE name = ? LIMIT 1');
        $stmt->execute([$departmentApplied]);
        $department = $stmt->fetchColumn();
        if (!$department) {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'Choose a valid department', 422);
        }
        $departmentApplied = (string)$department;
    }

    $fields = [
        'duration' => 100,
        'insurance_cover' => 100,
        'course_applying' => 150,
        'institution_name' => 150,
    ];
    $values = [];
    foreach ($fields as $field => $maxLength) {
        $values[$field] = trim((string)($_POST[$field] ?? ''));
        if (mb_strlen($values[$field]) > $maxLength) {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'One or more application fields are too long', 422);
        }
    }

    $stmt = $pdo->prepare("INSERT INTO submissions (
        user_id, national_id, full_name, email, vacancy_id, application_type,
        duration, insurance_cover, course_applying, institution_name, department_applied, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'applied')");
    $stmt->execute([
        $userId, $applicant['national_id'], $applicant['full_name'], $applicant['email'],
        $vacancyId, $applicationType, $values['duration'], $values['insurance_cover'],
        $values['course_applying'], $values['institution_name'], $departmentApplied,
    ]);
    $submissionId = (int)$pdo->lastInsertId();

    $documentTypes = [
        'application_letter' => 'Application Letter',
        'campus_letter' => 'Campus Letter',
        'insurance_cert' => 'Insurance Certificate',
        'academic_certs' => 'Academic Certificates',
        'national_id_copy' => 'National ID Copy',
    ];
    $uploadedDocs = 0;
    foreach ($documentTypes as $fieldName => $label) {
        if (!isset($_FILES[$fieldName])) continue;
        $file = $_FILES[$fieldName];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('A document upload could not be processed');
        }
        $size = filesize($file['tmp_name']);
        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if ($size === false || $size <= 0 || $size > MAX_FILE_SIZE
            || !in_array($extension, ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException($label . ' is invalid or exceeds the 2 MB limit');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException($label . ' has an unsupported file type');
        }
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) throw new RuntimeException('A document upload could not be read');

        $insert = $pdo->prepare('INSERT INTO documents
            (submission_id, document_type, original_filename, file_path, mime_type, file_size, file_content)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$file['name']));
        $insert->execute([$submissionId, $fieldName, substr($filename, 0, 255), '', $mimeType, $size, $content]);
        $uploadedDocs++;
    }

    $pdo->commit();
    error_log('Applicant application submitted (submission_id=' . $submissionId . ').');
    // Notifications are best-effort and contain no applicant identifiers.
    notifyDepartmentAdmins($pdo, $departmentApplied, 'New application received', 'A new application is ready for review.', '../pages/hr_dashboard.php');

    ob_end_clean();
    json_response(true, [
        'message' => 'Application submitted successfully',
        'submission_id' => $submissionId,
        'documents_uploaded' => $uploadedDocs,
    ], null, 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Application submission rejected or failed.');
    ob_end_clean();
    $message = $e instanceof RuntimeException ? $e->getMessage() : 'Application could not be submitted';
    json_response(false, null, $message, 422);
}
?>
