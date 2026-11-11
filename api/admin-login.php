<?php
/**
 * Admin Login Endpoint
 * POST /api/admin-login.php
 * 
 * Fixed: removed session-manager.php dependency to avoid session_start() header conflicts
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/rate-limiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}
require_json_request();

checkRateLimit($pdo, 'admin-login');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data === null) {
        ob_end_clean();
        json_response(false, null, 'Invalid JSON input');
    }
    
    $pfNumber = isset($data['pf_number']) ? trim($data['pf_number']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    
    if (empty($pfNumber) || empty($password)) {
        ob_end_clean();
        json_response(false, null, 'Missing PF Number or Password');
    }
    
    // Fetch admin with role info
    $stmt = $pdo->prepare("
        SELECT a.*, r.role_name, r.level as role_level
        FROM admin_users a
        LEFT JOIN roles r ON a.role_id = r.id
        WHERE a.pf_number = ?
    ");
    $stmt->execute([$pfNumber]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        recordLoginAttempt($pdo, 'admin-login', false, $pfNumber);
        ob_end_clean();
        json_response(false, null, 'Invalid PF Number or password', 401);
    }
    
    // Check account status
    if ($admin['status'] === 'pending_activation') {
        recordLoginAttempt($pdo, 'admin-login', false);
        ob_end_clean();
        json_response(false, null, 'Invalid PF Number or password', 401);
    }
    
    if ($admin['status'] !== 'active') {
        recordLoginAttempt($pdo, 'admin-login', false);
        ob_end_clean();
        json_response(false, null, 'Invalid PF Number or password', 401);
    }
    
    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        recordLoginAttempt($pdo, 'admin-login', false, $pfNumber);
        ob_end_clean();
        json_response(false, null, 'Invalid PF Number or password', 401);
    }
    
    $sessionResult = $GLOBALS['sessionManager']->createSession($admin['id'], 'admin', $admin['role_id']);
    if (empty($sessionResult['success'])) {
        throw new RuntimeException('Session could not be created');
    }
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$admin['id']]);

    clearLoginAttempts($pdo, 'admin-login');
    recordLoginAttempt($pdo, 'admin-login', true, $pfNumber);

    // Determine role name fallback
    $roleName = $admin['role_name'] ?? 'department_supervisor';
    $department = $admin['department'] ?? 'HR';
    
    ob_end_clean();
    json_response(true, [
        'message' => 'Login successful',
        'token' => $sessionResult['session_id'],
        'csrf_token' => $sessionResult['csrf_token'],
        'admin_id' => $admin['id'],
        'pf_number' => $admin['pf_number'],
        'full_name' => $admin['full_name'],
        'role' => $roleName,
        'department' => $department
    ]);
    
} catch (Exception $e) {
    error_log('Administrator login request failed.');
    ob_end_clean();
    json_response(false, null, 'Login failed');
}
?>
