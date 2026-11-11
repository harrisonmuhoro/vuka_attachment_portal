<?php
/** Stream the authenticated account's own profile image (no URL bearer tokens). */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}
if (isset($_GET['token'])) {
    json_response(false, null, 'Tokens must be sent in the Authorization header', 400);
}
$session = requireAuth();
$isStudent = $session['user_type'] === 'student';
$table = $isStudent ? 'users' : 'admin_users';
$accountId = $isStudent ? (int)$session['user_id'] : (int)$session['admin_id'];
try {
    $stmt = $pdo->prepare("SELECT profile_photo, profile_photo_blob FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$accountId]);
    $account = $stmt->fetch();
    if (!$account || empty($account['profile_photo_blob'])) {
        http_response_code(404);
        exit;
    }
    $ext = strtolower(pathinfo((string)$account['profile_photo'], PATHINFO_EXTENSION));
    $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? null;
    if ($mime === null) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . strlen($account['profile_photo_blob']));
    echo $account['profile_photo_blob'];
} catch (Throwable $e) {
    error_log('Profile image request failed.');
    http_response_code(500);
}
?>
