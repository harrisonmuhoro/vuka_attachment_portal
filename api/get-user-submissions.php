<?php
/**
 * Get User Submissions API
 * GET /api/get-user-submissions.php
 * 
 * Returns submissions for authenticated student
 * Only students can access their own submissions
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    $session = requireStudent();
    
    // Get submissions for this student
    $stmt = $pdo->prepare("
        SELECT *
        FROM submissions
        WHERE user_id = ?
        ORDER BY submitted_at DESC
    ");
    
    $stmt->execute([$session['user_id']]);
    $submissions = $stmt->fetchAll();
    
    ob_end_clean();
    json_response(true, [
        'data' => [
            'submissions' => $submissions
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get user submissions error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to retrieve submissions');
}

?>
