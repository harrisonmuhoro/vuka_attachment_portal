<?php
/**
 * Get User Info API
 * GET /api/get-user-info.php
 * 
 * Returns authenticated user information
 * Student or Admin based on session
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
    $session = requireAuth();
    
    $userInfo = [];
    
    if ($session['user_type'] === 'student') {
        // Get student info
        $stmt = $pdo->prepare("
            SELECT id, full_name, national_id, email, registration_date, verified, status, last_login
            FROM users
            WHERE id = ?
        ");
        
        $stmt->execute([$session['user_id']]);
        $userInfo = $stmt->fetch();
        
    } else if ($session['user_type'] === 'admin') {
        // Get admin info
        $stmt = $pdo->prepare("
            SELECT id, pf_number, full_name, email, department_id, status, last_login
            FROM admin_users
            WHERE id = ?
        ");
        
        $stmt->execute([$session['admin_id']]);
        $userInfo = $stmt->fetch();
    }
    
    ob_end_clean();
    json_response(true, [
        'data' => $userInfo
    ]);
    
} catch (Exception $e) {
    error_log("Get user info error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to retrieve user information');
}

?>
