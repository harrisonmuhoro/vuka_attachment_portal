<?php
/**
 * Verify Session API
 * GET /api/verify-session.php
 * 
 * Validates current session and returns role information
 * Backend-enforced role verification
 * Used by all dashboards to confirm user identity and permissions
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    $sessionManager = $GLOBALS['sessionManager'];
    $session = $sessionManager->validateSession();
    
    if (!$session) {
        ob_end_clean();
        json_response(false, [
            'authenticated' => false
        ], 'No valid session');
    }
    
    // Return verified session data
    ob_end_clean();
    json_response(true, [
        'authenticated' => true,
        'user_id' => $session['user_id'],
        'admin_id' => $session['admin_id'],
        'full_name' => $session['user_type'] === 'admin' ? $session['admin_name'] : $session['user_name'],
        'email' => $session['email'] ?? null,
        'role' => $session['role_name'],
        'role_level' => $session['role_level'],
        'user_type' => $session['user_type'],
        'pf_number' => $session['pf_number'] ?? null,
        'department_id' => $session['department_id'] ?? null,
        'department' => $session['department_name'] ?? null
    ]);
    
} catch (Exception $e) {
    error_log("Verify session error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Session verification failed');
}

?>
