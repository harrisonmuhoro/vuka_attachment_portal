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

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error.']);
    exit;
}

$file     = $_FILES['photo'];
$maxSize  = 2 * 1024 * 1024; // 2 MB
$allowed  = ['image/jpeg', 'image/png', 'image/webp'];
$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

// Validate size
if ($file['size'] > $maxSize) {
    http_response_code(422);
    echo json_encode(['error' => 'Photo must be 2 MB or smaller.']);
    exit;
}

// Validate MIME type (read from file, never trust $_FILES['type'])
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Only JPEG, PNG, and WebP images are allowed.']);
    exit;
}

// Validate extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid file extension.']);
    exit;
}

// Sanitize filename — never use the original
$filename  = bin2hex(random_bytes(16)) . '.' . $ext;
$uploadDir = __DIR__ . '/../uploads/profile_photos/';
$destPath  = $uploadDir . $filename;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save photo. Please try again.']);
    exit;
}

// Delete the old photo if one exists
$oldStmt = $pdo->prepare("SELECT profile_photo FROM {$table} WHERE id = ?");
$oldStmt->execute([$userId]);
$oldPhoto = $oldStmt->fetchColumn();
if ($oldPhoto && file_exists($uploadDir . $oldPhoto)) {
    unlink($uploadDir . $oldPhoto);
}

// Save new filename to DB
$pdo->prepare("UPDATE {$table} SET profile_photo = ? WHERE id = ?")
    ->execute([$filename, $userId]);

echo json_encode([
    'success'   => true,
    'photo_url' => '/attachment/uploads/profile_photos/' . $filename,
]);
