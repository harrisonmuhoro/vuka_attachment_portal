<?php
/** Stream a document only to its applicant or an authorized administrator. */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

try {
    $candidate = $GLOBALS['sessionManager']->validateSession();
    if (!$candidate) json_response(false, null, 'Authentication required', 401);

    $params = [];
    if ($candidate['role_name'] === 'student' && $candidate['user_type'] === 'student') {
        $session = requireStudent();
        $scopeSql = 's.user_id = ?';
        $params[] = (int)$session['user_id'];
    } elseif ($GLOBALS['sessionManager']->isAdminTier($candidate)) {
        $session = requireAnyAdmin();
        $scope = $GLOBALS['sessionManager']->getScopedDepartment($session);
        if ($scope === 'ALL') {
            $scopeSql = '1 = 1';
        } elseif (is_string($scope) && $scope !== '') {
            $scopeSql = 's.department_applied = ?';
            $params[] = $scope;
        } else {
            json_response(false, null, 'Administrator department scope is unavailable', 403);
        }
    } else {
        json_response(false, null, 'Forbidden', 403);
    }

    $documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $submissionId = filter_input(INPUT_GET, 'submissionId', FILTER_VALIDATE_INT);
    $documentType = trim((string)($_GET['documentType'] ?? ''));
    if ($documentId !== null && $documentId !== false && $documentId > 0) {
        $identifierSql = 'd.id = ?';
        $identifierParams = [$documentId];
    } elseif ($submissionId !== null && $submissionId !== false && $submissionId > 0 && $documentType !== '') {
        $identifierSql = 'd.submission_id = ? AND d.document_type = ?';
        $identifierParams = [$submissionId, $documentType];
    } else {
        json_response(false, null, 'Missing document ID or submission parameters', 400);
    }

    $stmt = $pdo->prepare("SELECT d.original_filename, d.file_content, d.mime_type
        FROM documents d JOIN submissions s ON s.id = d.submission_id
        WHERE {$identifierSql} AND {$scopeSql} LIMIT 1");
    $stmt->execute(array_merge($identifierParams, $params));
    $document = $stmt->fetch();
    if (!$document || $document['file_content'] === null) {
        json_response(false, null, 'Document not found', 404);
    }
    $allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($document['mime_type'], $allowedMime, true)) {
        json_response(false, null, 'Document type cannot be served', 415);
    }
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$document['original_filename']));
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Disposition: attachment; filename="' . ($filename ?: 'document') . '"');
    header('Content-Length: ' . strlen($document['file_content']));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $document['file_content'];
} catch (Throwable $e) {
    error_log('Document download failed.');
    http_response_code(500);
    json_response(false, null, 'Download failed', 500);
}
?>
