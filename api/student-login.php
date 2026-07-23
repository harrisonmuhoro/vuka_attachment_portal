<?php
/**
 * Student Login API
 * POST /api/student-login.php
 * 
 * Handles student authentication with secure session creation
 * Students CANNOT have admin privileges
 * Role validation happens on backend only
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../audit-logger.php';
require_once __DIR__ . '/../lib/rate-limiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

checkRateLimit($pdo, 'student-login');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data === null) {
        ob_end_clean();
        json_response(false, null, 'Invalid JSON input');
    }
    
    // Extract credentials
    $nationalId = isset($data['national_id']) ? trim($data['national_id']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    
    if (empty($nationalId) || empty($password)) {
        ob_end_clean();
        json_response(false, null, 'Missing credentials');
    }
    
    // Query database for student
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email, u.password_hash, u.verified, u.status
        FROM users u
        WHERE u.national_id = ?
    ");
    $stmt->execute([$nationalId]);
    $student = $stmt->fetch();
    
    if (!$student) {
        recordLoginAttempt($pdo, 'student-login', false, $nationalId);
        ob_end_clean();
        json_response(false, null, 'Invalid credentials');
    }
    
    // Check account status - BACKEND ENFORCED
    if ($student['status'] !== 'active') {
        ob_end_clean();
        json_response(false, null, 'Account is inactive or suspended');
    }
    
    // Verify password
    if (!password_verify($password, $student['password_hash'])) {
        recordLoginAttempt($pdo, 'student-login', false, $nationalId);
        ob_end_clean();
        json_response(false, null, 'Invalid credentials');
    }
    
    // Check email verification
    if (!$student['verified']) {
        ob_end_clean();
        json_response(false, null, 'Please verify your email before login');
    }
    
    // Get student role ID (student = role_id 4 in the 4-role model; resolved by name to stay correct)
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'student'");
    $stmt->execute();
    $studentRole = $stmt->fetch();
    
    if (!$studentRole) {
        throw new Exception('Student role not found in database');
    }
    
    // Create secure session
    $sessionManager = $GLOBALS['sessionManager'];
    $sessionResult = $sessionManager->createSession(
        $student['id'],
        'student',
        $studentRole['id']
    );
    
    if (!$sessionResult['success']) {
        ob_end_clean();
        json_response(false, null, 'Failed to create session');
    }
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$student['id']]);

    clearLoginAttempts($pdo, 'student-login');
    recordLoginAttempt($pdo, 'student-login', true, $nationalId);

    ob_end_clean();
    json_response(true, [
        // createSession sets a cookie, but Bearer clients need the session_id surfaced as 'token'
        'token' => $sessionResult['session_id'],
        'user_id' => $student['id'],
        'full_name' => $student['full_name'],
        'email' => $student['email'],
        'role' => 'student',
        'redirect' => 'pages/student_dashboard.php'
    ]);
    
} catch (Exception $e) {
    error_log("Student login error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Login failed');
}

?>
