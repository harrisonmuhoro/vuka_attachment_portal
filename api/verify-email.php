<?php
/**
 * Verify Email Endpoint
 * POST /api/verify-email.php
 */

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed');
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['national_id']) || empty($data['code'])) {
        json_response(false, null, 'Missing required fields');
    }
    
    $nationalId = trim($data['national_id']);
    $code = trim($data['code']);
    
    // Check verification code
    $stmt = $pdo->prepare("
        SELECT id, verification_code FROM users 
        WHERE national_id = ? AND verified = 0
    ");
    $stmt->execute([$nationalId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        json_response(false, null, 'User not found or already verified');
    }
    
    if ((string)$user['verification_code'] !== (string)$code) {
        json_response(false, null, 'Invalid verification code');
    }
    
    // Mark user as verified
    $stmt = $pdo->prepare("
        UPDATE users 
        SET verified = 1, verification_code = NULL 
        WHERE id = ?
    ");
    
    if (!$stmt->execute([$user['id']])) {
        json_response(false, null, 'Verification failed');
    }
    
    json_response(true, [
        'message' => 'Email verified successfully',
        'userId' => $user['id']
    ]);
    
} catch (Exception $e) {
    error_log("Email verification error: " . $e->getMessage());
    json_response(false, null, 'Verification failed');
}
?>
