<?php
require_once '../session-manager.php';
require_once '../lib/mailer.php';

$session = getSession();

// Works for both students and admins
$isStudent = isset($session['user_id']);
$userId    = $isStudent ? $session['user_id'] : $session['admin_id'];
$table     = $isStudent ? 'users' : 'admin_users';

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$newEmail = trim($_POST['new_email'] ?? '');

if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid email address.']);
    exit;
}

// Check if email is already taken
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?
                        UNION SELECT id FROM admin_users WHERE email = ?");
$stmt->execute([$newEmail, $newEmail]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'That email address is already in use.']);
    exit;
}

// Generate 6-digit code (not a URL token — simpler UX for the user)
$code    = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
$hashed  = password_hash($code, PASSWORD_BCRYPT);  // store hashed, compare on confirm

$pdo->prepare("UPDATE {$table}
               SET pending_email = ?, email_change_token = ?,
                   email_change_expires = ?
               WHERE id = ?")
    ->execute([$newEmail, $hashed, $expires, $userId]);

// Email the code to the NEW address
$html = "
    <p>You requested an email change on your Vuka Portal account.</p>
    <p>Your verification code is: <strong style='font-size:24px'>{$code}</strong></p>
    <p>This code expires in <strong>10 minutes</strong>.</p>
    <p>If you did not request this, ignore this email — your address has not changed.</p>";

sendMail($newEmail, '', 'Verify Your New Email — Vuka Portal', $html);

// Also alert the OLD email
$oldStmt = $pdo->prepare("SELECT email FROM {$table} WHERE id = ?");
$oldStmt->execute([$userId]);
$old = $oldStmt->fetchColumn();

if ($old) {
    $alertHtml = "
        <p>A request was made to change your Vuka Portal email address to
        <strong>{$newEmail}</strong>.</p>
        <p>If this was not you, contact your system administrator immediately.</p>";
    sendMail($old, '', 'Security Alert: Email Change Requested — Vuka Portal', $alertHtml);
}

echo json_encode(['success' => true,
    'message' => 'A verification code has been sent to your new email address.']);
