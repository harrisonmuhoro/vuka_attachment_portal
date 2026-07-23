<?php
/**
 * Logout API
 * POST /api/logout.php
 * 
 * Invalidates session and clears cookies
 * Logs logout event for audit trail
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    $sessionManager = $GLOBALS['sessionManager'];
    // Bearer clients have no cookie; invalidateSession() only falls back to cookie/PHP-session,
    // so pass the Bearer token explicitly when present.
    $sessionManager->invalidateSession(get_bearer_token());
    
    ob_end_clean();
    json_response(true, ['message' => 'Logged out successfully']);
    
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Logout failed');
}

?>
