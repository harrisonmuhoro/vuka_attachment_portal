<?php
/**
 * Universal Login Endpoint
 * POST /api/login.php
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
            SELECT id, full_name, email, national_id, password_hash, verified, registration_date, created_at
            FROM users 
            WHERE national_id = ?
        ");
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();

        if ($user && verify_password($password, $user['password_hash'])) {
            // Student Login Success
            
            // Check verification
            if (!$user['verified']) {
                ob_end_clean();
                json_response(false, [
                    'needsVerification' => true,
                    'national_id' => $user['national_id']
                ], 'Account not verified. Please verify your email first.');
            }

            // Check submission status
            $stmt = $pdo->prepare("SELECT id FROM submissions WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user['id']]);
            $alreadySubmitted = $stmt->rowCount() > 0;
            
            // Generate Token
            $sessionToken = bin2hex(random_bytes(32));
            
            // Store session
             $expiresAt = date('Y-m-d H:i:s', time() + 28800); // 8 hours
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt = $pdo->prepare("
                INSERT INTO sessions (session_id, user_id, user_type, role_id, ip_address, user_agent, expires_at, is_valid)
                VALUES (?, ?, 'student', 4, ?, ?, ?, 1)
            ");
            $stmt->execute([$sessionToken, $user['id'], $ipAddress, $userAgent, $expiresAt]);

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
                'token' => $sessionToken
            ]);
        }
    }
    
    // --- 2. Check if user is an ADMIN (National ID match in admin table) ---
    // If student login failed (or wasn't attempted/found), try admin
    
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE national_id = ?");
    $stmt->execute([$identifier]);
    $admin = $stmt->fetch();

    if ($admin && verify_password($password, $admin['password_hash'])) {
        // Admin Login Success
        $sessionToken = bin2hex(random_bytes(32));
        
         // Store session for admin
        $expiresAt = date('Y-m-d H:i:s', time() + 28800); // 8 hours
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $roleId = $admin['role_id']; // 1=Admin, 2=HR, 3=Supervisor
        $userType = 'admin';
        
        $stmt = $pdo->prepare("
            INSERT INTO sessions (session_id, user_id, admin_id, user_type, role_id, ip_address, user_agent, expires_at, is_valid)
            VALUES (?, NULL, ?, 'admin', ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$sessionToken, $admin['id'], $roleId, $ipAddress, $userAgent, $expiresAt]);

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
            'token' => $sessionToken
        ]);
    }

    // --- 3. Login Failed ---
    recordLoginAttempt($pdo, 'login', false, $identifier);
    ob_end_clean();
    json_response(false, null, 'Invalid credentials');

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Login failed: ' . $e->getMessage());
}

