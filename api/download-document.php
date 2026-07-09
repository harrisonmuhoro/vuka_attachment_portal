<?php
/**
 * Download / View Document Endpoint
 * GET /api/download-document.php?id=XXX
 * GET /api/download-document.php?submissionId=XXX&documentType=XXX
 * 
 * Supports both lookup methods: by document ID, or by submission + type
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed');
}

try {
    $documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $submissionId = isset($_GET['submissionId']) ? (int)$_GET['submissionId'] : 0;
    $documentType = isset($_GET['documentType']) ? trim($_GET['documentType']) : '';
    
    $document = null;
    
    // Lookup by document ID
    if ($documentId > 0) {
        $stmt = $pdo->prepare("
            SELECT d.original_filename, d.file_path, d.mime_type, s.full_name
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
            SELECT d.original_filename, d.file_path, d.mime_type, s.full_name
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
    
    $filepath = UPLOAD_DIR . $document['file_path'];
    
    if (!file_exists($filepath)) {
        json_response(false, null, 'File not found on server');
    }
    
    // Stream file (do NOT send JSON headers - this is a binary response)
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Disposition: inline; filename="' . $document['original_filename'] . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: private, max-age=3600');
    header('Pragma: public');
    
    readfile($filepath);
    exit;
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    http_response_code(500);
    json_response(false, null, 'Download failed');
}
?>
