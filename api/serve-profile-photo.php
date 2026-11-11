<?php
/**
 * Serve Profile Photo Endpoint
 * GET /api/serve-profile-photo.php?type=user&id=XXX
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

if (isset($_GET['token'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
}

// Any logged-in user can view profile photos
$session = requireAuth();

try {
    $type = isset($_GET['type']) && $_GET['type'] === 'admin' ? 'admin_users' : 'users';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        if (isset($session['user_id'])) {
            $type = 'users';
            $id = (int)$session['user_id'];
        } elseif (isset($session['admin_id'])) {
            $type = 'admin_users';
            $id = (int)$session['admin_id'];
        } else {
            http_response_code(400);
            exit;
        }
    }
    
    $stmt = $pdo->prepare("SELECT profile_photo, profile_photo_blob FROM {$type} WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['profile_photo_blob'])) {
        // Return default avatar or 404
        http_response_code(404);
        exit;
    }
    
    $ext = strtolower(pathinfo($user['profile_photo'], PATHINFO_EXTENSION));
    $mime = 'image/jpeg';
    if ($ext === 'png') $mime = 'image/png';
    if ($ext === 'webp') $mime = 'image/webp';
    
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, max-age=3600');
    header('Pragma: public');
    header('Content-Length: ' . strlen($user['profile_photo_blob']));
    
    echo $user['profile_photo_blob'];
    exit;
    
} catch (Exception $e) {
    error_log("Serve profile photo error: " . $e->getMessage());
    http_response_code(500);
    exit;
}
?>
