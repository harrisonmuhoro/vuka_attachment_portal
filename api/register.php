<?php
/**
 * User Registration Endpoint
 * POST /api/register.php
 * 
 * Handles both new registration and resend verification code
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data === null) {
        ob_end_clean();
        json_response(false, null, 'Invalid JSON input');
    }
    
    // Handle resend verification code
    if (!empty($data['resend_code'])) {
        $nationalId = isset($data['national_id']) ? trim($data['national_id']) : '';
        
        if (empty($nationalId)) {
            ob_end_clean();
            json_response(false, null, 'Missing national ID');
        }
        
        // Find unverified user
        $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE national_id = ? AND verified = 0");
        $stmt->execute([$nationalId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            ob_end_clean();
            json_response(false, null, 'User not found or already verified');
        }
        
        // Generate new code and update
        $newCode = generate_verification_code();
        $stmt = $pdo->prepare("UPDATE users SET verification_code = ?, verification_sent_at = NOW() WHERE id = ?");
        $stmt->execute([$newCode, $user['id']]);
        
        ob_end_clean();
        json_response(true, [
            'message' => 'Verification code has been regenerated. Check your email.',
            'debug_code' => $newCode // REMOVE IN PRODUCTION
        ]);
    }
    
    // Normal registration flow
    $fullName = isset($data['full_name']) ? trim($data['full_name']) : '';
    $nationalId = isset($data['national_id']) ? trim($data['national_id']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    $registrationDate = isset($data['registration_date']) ? trim($data['registration_date']) : date('Y-m-d');
    
    if (empty($fullName) || empty($nationalId) || empty($email) || empty($password)) {
        ob_end_clean();
        json_response(false, null, 'Missing required fields');
    }
    
    // Validate National ID: must be 6-9 digits only
    if (!preg_match('/^\d{6,9}$/', $nationalId)) {
        ob_end_clean();
        json_response(false, null, 'National ID must be 6 to 9 digits (numbers only)');
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        json_response(false, null, 'Invalid email address');
    }
    
    // Validate email domain
    $allowedDomains = ['gmail.com', 'outlook.com', 'hotmail.com', 'yahoo.com', 'ymail.com', 'aol.com', 'icloud.com'];
    $emailDomain = strtolower(substr(strrchr($email, '@'), 1));
    if (!in_array($emailDomain, $allowedDomains)) {
        ob_end_clean();
        json_response(false, null, 'Email must be from: ' . implode(', ', $allowedDomains));
    }
    
    // Validate password strength
    if (strlen($password) < 6) {
        ob_end_clean();
        json_response(false, null, 'Password must be at least 6 characters');
    }
    
    // Check database connection
    if (!isset($pdo)) {
        ob_end_clean();
        json_response(false, null, 'Database connection not available');
    }
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE national_id = ? OR email = ?");
    $stmt->execute([$nationalId, $email]);
    
    if ($stmt->rowCount() > 0) {
        ob_end_clean();
        json_response(false, null, 'User with this ID or email already exists');
    }
    
    // Generate verification code
    $verificationCode = generate_verification_code();
    
    // Hash password
    $passwordHash = hash_password($password);
    
    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, national_id, email, password_hash, registration_date, verification_code, verification_sent_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([$fullName, $nationalId, $email, $passwordHash, $registrationDate, $verificationCode]);
    
    if (!$result) {
        ob_end_clean();
        json_response(false, null, 'Registration failed');
    }
    
    $userId = $pdo->lastInsertId();
    
    ob_end_clean();
    // NOTE: For local testing, we return the code. In production, remove 'debug_code'.
    json_response(true, [
        'message' => 'Registration successful. Please check your email for the verification code.',
        'userId' => $userId,
        'debug_code' => $verificationCode // REMOVE IN PRODUCTION
    ]);
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Registration failed: ' . $e->getMessage());
}
?>
