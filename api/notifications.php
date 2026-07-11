<?php
/**
 * Notifications API — Vuka Portal (Feature #5)
 * GET  /api/notifications.php                  -> recent notifications + unread_count
 * POST /api/notifications.php {action:mark_read} -> mark all as read
 *
 * Works for both students and admins; recipient is derived from the session.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';

$session = requireAuth(); // any authenticated user (student or admin)

// Resolve recipient id + role type from the validated session.
if (($session['user_type'] ?? '') === 'admin') {
    $recipientId = (int)($session['admin_id'] ?? 0);
    $roleType    = 'admin';
} else {
    $recipientId = (int)($session['user_id'] ?? 0);
    $roleType    = 'student';
}

if ($recipientId <= 0) {
    ob_end_clean();
    json_response(false, null, 'Unable to resolve recipient');
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        $data   = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $data['action'] ?? '';

        if ($action === 'mark_read') {
            $pdo->prepare("UPDATE notifications SET is_read = 1
                           WHERE recipient_id = ? AND role_type = ? AND is_read = 0")
                ->execute([$recipientId, $roleType]);
            ob_end_clean();
            json_response(true, ['message' => 'Marked as read']);
        }

        ob_end_clean();
        json_response(false, null, 'Unknown action');
    }

    // GET — recent 20 notifications + unread count
    $stmt = $pdo->prepare("SELECT id, title, body, link, is_read, created_at
                           FROM notifications
                           WHERE recipient_id = ? AND role_type = ?
                           ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$recipientId, $roleType]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications
                                WHERE recipient_id = ? AND role_type = ? AND is_read = 0");
    $countStmt->execute([$recipientId, $roleType]);
    $unread = (int) $countStmt->fetchColumn();

    ob_end_clean();
    json_response(true, [
        'notifications' => $notifications,
        'unread_count'  => $unread,
    ]);

} catch (Exception $e) {
    error_log('Notifications error: ' . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to load notifications');
}
