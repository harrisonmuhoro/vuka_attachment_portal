<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed');
}

$session = requireAnyAdmin();

try {
    $documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $submissionId = isset($_GET['submissionId']) ? (int)$_GET['submissionId'] : 0;
    $documentType = isset($_GET['documentType']) ? trim($_GET['documentType']) : '';
    
    $document = null;
    
    // Lookup by document ID
    if ($documentId > 0) {
        $stmt = $pdo->prepare("
            SELECT d.original_filename, d.file_content, d.mime_type, s.full_name, s.department_applied
            FROM documents d
            JOIN submissions s ON d.submission_id = s.id
            WHERE d.id = ?
            LIMIT 1
        ");
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
    }
    // Fallback: lookup by submission ID + document type
    elseif ($submissionId > 0 && !empty($documentType)) {
        $stmt = $pdo->prepare("
            SELECT d.original_filename, d.file_content, d.mime_type, s.full_name, s.department_applied
            FROM documents d
            JOIN submissions s ON d.submission_id = s.id
            WHERE d.submission_id = ? AND d.document_type = ?
            LIMIT 1
        ");
        $stmt->execute([$submissionId, $documentType]);
        $document = $stmt->fetch();
    } else {
        json_response(false, null, 'Missing document ID or submission parameters');
    }
    
    if (!$document) {
        json_response(false, null, 'Document not found');
    }

    $scopedDept = $GLOBALS['sessionManager']->getScopedDepartment($session);
    if ($scopedDept !== null && $scopedDept !== 'ALL' && ($document['department_applied'] ?? null) !== $scopedDept) {
        http_response_code(403);
        json_response(false, null, 'Forbidden: document is outside your department');
    }

    if (empty($document['file_content'])) {
        json_response(false, null, 'File content not found in database');
    }
    
    // Stream file (do NOT send JSON headers - this is a binary response)
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Disposition: inline; filename="' . $document['original_filename'] . '"');
    header('Content-Length: ' . strlen($document['file_content']));
    header('Cache-Control: private, max-age=3600');
    header('Pragma: public');
    
    echo $document['file_content'];
    exit;
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    http_response_code(500);
    json_response(false, null, 'Download failed');
}
?>
