<?php
/** Applicant registration and verification-code resend. */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/rate-limiter.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../lib/email-templates.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed', 405);
}
require_json_request();

checkRateLimit($pdo, 'register', true, 5, 15);
recordLoginAttempt($pdo, 'register', false);

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        ob_end_clean();
        json_response(false, null, 'Invalid JSON input', 400);
    }

    if (!empty($data['resend_code'])) {
        $nationalId = trim((string)($data['national_id'] ?? ''));
        if (!preg_match('/^\d{6,9}$/', $nationalId)) {
            ob_end_clean();
            json_response(false, null, 'If the account can receive a code, a new code will be sent.');
        }
        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE national_id = ? AND verified = 0 LIMIT 1');
        $stmt->execute([$nationalId]);
        $user = $stmt->fetch();
        if ($user) {
            $code = (string)random_int(100000, 999999);
            $hash = password_hash($code, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET verification_code = ?, verification_sent_at = NOW() WHERE id = ? AND verified = 0')
                ->execute([$hash, $user['id']]);
            sendMail((string)$user['email'], '', 'Verify Your Vuka Portal Account', emailVerificationCode($code));
        }
        ob_end_clean();
        json_response(true, ['message' => 'If the account can receive a code, a new code will be sent.']);
    }

    $fullName = trim((string)($data['full_name'] ?? ''));
    $nationalId = trim((string)($data['national_id'] ?? ''));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $password = (string)($data['password'] ?? '');
    $registrationDate = trim((string)($data['registration_date'] ?? date('Y-m-d')));
    if ($fullName === '' || !preg_match('/^\d{6,9}$/', $nationalId)
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8
        || mb_strlen($fullName) > 150
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $registrationDate)
        || strtotime($registrationDate) === false) {
        ob_end_clean();
        json_response(false, null, 'Please provide valid registration details (password must be at least 8 characters)', 422);
    }
    $allowedDomains = ['gmail.com', 'outlook.com', 'hotmail.com', 'yahoo.com', 'ymail.com', 'aol.com', 'icloud.com'];
    $emailDomain = strtolower(substr(strrchr($email, '@'), 1));
    if (!in_array($emailDomain, $allowedDomains, true)) {
        ob_end_clean();
        json_response(false, null, 'Email domain is not supported', 422);
    }

    $code = (string)random_int(100000, 999999);
    $codeHash = password_hash($code, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (full_name, national_id, email, password_hash, registration_date, verification_code, verification_sent_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$fullName, $nationalId, $email, password_hash($password, PASSWORD_DEFAULT), $registrationDate, $codeHash]);
    $userId = (int)$pdo->lastInsertId();
    sendMail($email, $fullName, 'Verify Your Vuka Portal Account', emailVerificationCode($code));

    ob_end_clean();
    json_response(true, ['message' => 'Registration received. If the details are valid, check your email for a verification code.', 'userId' => $userId], null, 201);
} catch (PDOException $e) {
    error_log('Applicant registration could not be completed.');
    ob_end_clean();
    json_response(false, null, 'Registration could not be completed. Check that your ID and email are not already registered.', 409);
} catch (Throwable $e) {
    error_log('Applicant registration failed.');
    ob_end_clean();
    json_response(false, null, 'Registration could not be completed', 500);
}
?>
