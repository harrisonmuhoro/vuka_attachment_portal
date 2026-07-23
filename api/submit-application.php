<?php
/**
 * Submit Application Endpoint
 * POST /api/submit-application.php
 * 
 * Handles attachment application form + document uploads
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}
    // DEBUG LOGGING
    error_log("--- SUBMIT APPLICATION START ---");
    error_log("POST count: " . count($_POST));
    error_log("FILES count: " . count($_FILES));
    
    // Check for post_max_size exceeded
    if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max = ini_get('post_max_size');
        error_log("ERROR: post_max_size exceeded. Limit is $max. Content-Length is " . $_SERVER['CONTENT_LENGTH']);
        ob_end_clean();
        json_response(false, null, "File upload too large. Server limit is $max.");
    }

try {
    // Get form data (multipart/form-data)
    $nationalId = isset($_POST['national_id']) ? trim($_POST['national_id']) : '';
    $fullName = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $duration = isset($_POST['duration']) ? trim($_POST['duration']) : '';
    $insuranceCover = isset($_POST['insurance_cover']) ? trim($_POST['insurance_cover']) : '';
    $courseApplying = isset($_POST['course_applying']) ? trim($_POST['course_applying']) : '';
    $institutionName = isset($_POST['institution_name']) ? trim($_POST['institution_name']) : '';
    $departmentApplied = isset($_POST['department_applied']) ? trim($_POST['department_applied']) : '';
    $vacancyId = isset($_POST['vacancy_id']) ? (int)$_POST['vacancy_id'] : null;
    $applicationType = isset($_POST['application_type']) ? trim($_POST['application_type']) : 'attachment';
    
    // Validate required fields
    if (empty($nationalId) || empty($fullName) || empty($email)) {
        ob_end_clean();
        json_response(false, null, 'Missing required fields');
    }
    
    // Verify user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE national_id = ?");
    $stmt->execute([$nationalId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        ob_end_clean();
        json_response(false, null, 'User not found');
    }
    
    $userId = $user['id'];
    
    // Check if already submitted
    $stmt = $pdo->prepare("SELECT id FROM submissions WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    if ($stmt->rowCount() > 0) {
        ob_end_clean();
        json_response(false, null, 'You have already submitted an application');
    }

    // Enforce vacancy availability + application deadline (Feature #7)
    if (!empty($vacancyId)) {
        $vStmt = $pdo->prepare("SELECT status, deadline_at FROM vacancies WHERE id = ? LIMIT 1");
        $vStmt->execute([$vacancyId]);
        $vac = $vStmt->fetch();
        if ($vac) {
            if ($vac['status'] !== 'approved') {
                ob_end_clean();
                json_response(false, null, 'This vacancy is no longer accepting applications.');
            }
            if (!empty($vac['deadline_at']) && strtotime($vac['deadline_at']) < time()) {
                ob_end_clean();
                json_response(false, null, 'The application deadline for this vacancy has passed.');
            }
        }
    }

    // Begin transaction
    $pdo->beginTransaction();
    
    // Insert submission
    $stmt = $pdo->prepare("
        INSERT INTO submissions (
            user_id, national_id, full_name, email,
            vacancy_id, application_type, duration, insurance_cover, course_applying,
            institution_name, department_applied, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'applied')
    ");
    
    $stmt->execute([
        $userId, $nationalId, $fullName, $email,
        $vacancyId, $applicationType, $duration, $insuranceCover, $courseApplying,
        $institutionName, $departmentApplied
    ]);
    
    error_log("Submission record created for User ID: $userId");
    
    $submissionId = $pdo->lastInsertId();
    
    // Handle file uploads
    $documentTypes = [
        'application_letter' => 'Application Letter',
        'campus_letter' => 'Campus Letter',
        'insurance_cert' => 'Insurance Certificate',
        'academic_certs' => 'Academic Certificates',
        'national_id_copy' => 'National ID Copy'
    ];
    
    $uploadedDocs = [];
    
    foreach ($documentTypes as $fieldName => $docLabel) {
        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$fieldName];
            
            // Validate file size
            if ($file['size'] > MAX_FILE_SIZE) {
                $pdo->rollBack();
                ob_end_clean();
                json_response(false, null, "$docLabel exceeds the maximum file size of 2MB");
            }
            
            // Validate file type
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                $pdo->rollBack();
                ob_end_clean();
                json_response(false, null, "$docLabel has an invalid file type. Allowed: PDF, JPG, PNG");
            }
            
            // Validate MIME type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
                $pdo->rollBack();
                ob_end_clean();
                json_response(false, null, "$docLabel has an invalid MIME type");
            }
            
            // Extract file content
            $fileContent = file_get_contents($file['tmp_name']);
            if ($fileContent === false) {
                $pdo->rollBack();
                ob_end_clean();
                json_response(false, null, "Failed to read $docLabel");
            }
            
            // Insert document record with BLOB
            $stmt = $pdo->prepare("
                INSERT INTO documents (submission_id, document_type, original_filename, file_path, mime_type, file_size, file_content)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $submissionId,
                $fieldName,
                sanitize_filename($file['name']),
                '', // no relative file_path anymore
                $mimeType,
                $file['size'],
                $fileContent
            ]);
            
            $uploadedDocs[] = $fieldName;
        }
    }
    
    $pdo->commit();
    
    ob_end_clean();
    json_response(true, [
        'message' => 'Application submitted successfully',
        'submission_id' => $submissionId,
        'documents_uploaded' => count($uploadedDocs)
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Submission error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Submission failed: ' . $e->getMessage());
}
error_log("--- SUBMIT APPLICATION END ---");
?>
