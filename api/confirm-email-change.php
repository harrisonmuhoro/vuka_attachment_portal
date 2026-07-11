<?php
require_once '../session-manager.php';

$session = getSession();

$isStudent = isset($session['user_id']);
$userId    = $isStudent ? $session['user_id'] : $session['admin_id'];
$table     = $isStudent ? 'users' : 'admin_users';

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$code = trim($_POST['code'] ?? '');

$stmt = $pdo->prepare("SELECT pending_email, email_change_token, email_change_expires
                        FROM {$table} WHERE id = ?");
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || !$row['pending_email'] || !$row['email_change_token']) {
    http_response_code(400);
    echo json_encode(['error' => 'No pending email change found.']);
    exit;
}

if (strtotime($row['email_change_expires']) < time()) {
    http_response_code(400);
    echo json_encode(['error' => 'Verification code has expired. Please request a new one.']);
    exit;
}

if (!password_verify($code, $row['email_change_token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Incorrect verification code.']);
    exit;
}

// Swap email — clear pending fields
$pdo->prepare("UPDATE {$table}
               SET email = ?, pending_email = NULL,
                   email_change_token = NULL, email_change_expires = NULL
               WHERE id = ?")
    ->execute([$row['pending_email'], $userId]);

echo json_encode(['success' => true, 'message' => 'Email address updated successfully.']);
