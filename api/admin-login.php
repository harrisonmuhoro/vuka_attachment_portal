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

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/rate-limiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

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
        json_response(false, null, 'Invalid PF Number or password');
    }
    
    // Check account status
    if ($admin['status'] === 'pending_activation') {
        ob_end_clean();
        json_response(false, null, 'Account pending activation. Please contact Super Admin.');
    }
    
    if ($admin['status'] !== 'active') {
        ob_end_clean();
        json_response(false, null, 'Account is not active. Please contact administration.');
    }
    
    // Verify password
    if (!password_verify($password, $admin['password_hash'])) {
        recordLoginAttempt($pdo, 'admin-login', false, $pfNumber);
        ob_end_clean();
        json_response(false, null, 'Invalid PF Number or password');
    }
    
    // Create session directly (no session-manager dependency)
    $sessionToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 28800); // 8 hours, matches SessionManager::SESSION_TIMEOUT
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $pdo->prepare("
        INSERT INTO sessions (session_id, admin_id, user_type, role_id, ip_address, user_agent, expires_at, is_valid)
        VALUES (?, ?, 'admin', ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$sessionToken, $admin['id'], $admin['role_id'], $ipAddress, $userAgent, $expiresAt]);
    
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
        'token' => $sessionToken,
        'admin_id' => $admin['id'],
        'pf_number' => $admin['pf_number'],
        'full_name' => $admin['full_name'],
        'role' => $roleName,
        'department' => $department
    ]);
    
} catch (Exception $e) {
    error_log("Admin login error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Login failed: ' . $e->getMessage());
}
?>
