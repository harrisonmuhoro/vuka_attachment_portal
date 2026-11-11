<?php
/**
 * Universal Login Endpoint
 * POST /api/login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../lib/rate-limiter.php';
require_once __DIR__ . '/../lib/password-policy.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}
require_json_request();

// Block brute-force attempts before doing any credential work.
checkRateLimit($pdo, 'login');

try {
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    if ($data === null) {
        ob_end_clean();
        json_response(false, null, 'Invalid JSON input');
    }
    
    // Universal identifier field (can be National ID or PF Number)
    $identifier = $data['identifier'] ?? $data['national_id'] ?? $data['nationalId'] ?? $data['pf_number'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($identifier) || empty($password)) {
        ob_end_clean();
        json_response(false, null, 'Missing credentials');
    }

    // Validate ID format: 6-9 digits only
    if (!preg_match('/^\d{6,9}$/', $identifier)) {
        ob_end_clean();
        json_response(false, null, 'National ID must be 6 to 9 digits (numbers only)');
    }
    
    $identifier = trim($identifier);
    
    // --- 1. Check if user is a STUDENT (National ID: 6-8 digits) ---
    if (preg_match('/^\d{6,8}$/', $identifier)) {
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, national_id, password_hash, verified, status, registration_date, created_at
            FROM users 
            WHERE national_id = ?
        ");
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();

        if ($user && verify_password($password, $user['password_hash'])) {
            if ($user['status'] !== 'active') {
                recordLoginAttempt($pdo, 'login', false);
                ob_end_clean();
                json_response(false, null, 'Invalid credentials', 401);
            }
            // Student Login Success
            
            // Check verification
            if (!$user['verified']) {
                recordLoginAttempt($pdo, 'login', false);
                ob_end_clean();
                json_response(false, [
                    'needsVerification' => true,
                    'national_id' => $user['national_id']
                ], 'Account not verified. Please verify your email first.', 401);
            }
            PasswordPolicy::rehashIfNeeded($pdo, 'users', (int)$user['id'], (string)$password, (string)$user['password_hash']);

            // Check submission status
            $stmt = $pdo->prepare("SELECT id FROM submissions WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user['id']]);
            $alreadySubmitted = $stmt->rowCount() > 0;
            
            $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'student' LIMIT 1");
            $roleStmt->execute();
            $studentRoleId = (int)$roleStmt->fetchColumn();
            if ($studentRoleId <= 0) {
                throw new RuntimeException('Student role is unavailable');
            }
            $sessionResult = $GLOBALS['sessionManager']->createSession($user['id'], 'student', $studentRoleId);
            if (empty($sessionResult['success'])) {
                throw new RuntimeException('Session could not be created');
            }
            $sessionToken = $sessionResult['session_id'];
            $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

            // Determine redirect based on role (Student is role_id 4)
            $redirect = 'pages/student_dashboard.php';

            clearLoginAttempts($pdo, 'login');
            recordLoginAttempt($pdo, 'login', true, $identifier);

            ob_end_clean();
            json_response(true, [
                'role' => 'student',
                'redirect' => $redirect,
                'user' => [
                    'userId' => $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'national_id' => $user['national_id'],
                    'registration_date' => $user['registration_date'],
                    'alreadySubmitted' => $alreadySubmitted,
                ],
                'token' => $sessionToken,
                'csrf_token' => $sessionResult['csrf_token']
            ]);
        }
    }
    
    // --- 2. Check if user is an ADMIN (National ID match in admin table) ---
    // If student login failed (or wasn't attempted/found), try admin
    
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE national_id = ?");
    $stmt->execute([$identifier]);
    $admin = $stmt->fetch();

    if ($admin && verify_password($password, $admin['password_hash'])) {
        if ($admin['status'] !== 'active') {
            recordLoginAttempt($pdo, 'login', false);
            ob_end_clean();
            json_response(false, null, 'Invalid credentials', 401);
        }
        PasswordPolicy::rehashIfNeeded($pdo, 'admin_users', (int)$admin['id'], (string)$password, (string)$admin['password_hash']);
        // Admin Login Success
        $roleId = $admin['role_id']; // 1=Admin, 2=HR, 3=Supervisor
        $sessionResult = $GLOBALS['sessionManager']->createSession($admin['id'], 'admin', $roleId);
        if (empty($sessionResult['success'])) {
            throw new RuntimeException('Session could not be created');
        }
        $sessionToken = $sessionResult['session_id'];

        // Update last_login
        $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

        // Determine Redirect
        $redirect = 'index.php';
        if ($roleId == 1) $redirect = 'pages/admin_dashboard.php';
        elseif ($roleId == 2) $redirect = 'pages/hr_dashboard.php';
        elseif ($roleId == 3) $redirect = 'pages/supervisor_dashboard.php';

        clearLoginAttempts($pdo, 'login');
        recordLoginAttempt($pdo, 'login', true, $identifier);

        ob_end_clean();
        json_response(true, [
            'role' => 'admin',
            'role_id' => $roleId,
            'redirect' => $redirect,
            'full_name' => $admin['full_name'] ?? $identifier,
            'pf_number' => $admin['pf_number'],
            'department' => $admin['department'],
                'token' => $sessionToken,
                'csrf_token' => $sessionResult['csrf_token']
        ]);
    }

    // --- 3. Login Failed ---
    recordLoginAttempt($pdo, 'login', false, $identifier);
    ob_end_clean();
    json_response(false, null, 'Invalid credentials', 401);

} catch (Exception $e) {
    error_log('Login request failed.');
    ob_end_clean();
    json_response(false, null, 'Login failed');
}

