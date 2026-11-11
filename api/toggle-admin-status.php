<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../audit-logger.php';
require_once __DIR__ . '/../lib/admin-lifecycle.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_response(false, null, 'Method not allowed', 405);
try {
    $session = requireSuperAdmin();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) json_response(false, null, 'Invalid JSON input', 400);
    $target = AdminLifecycle::change($pdo, $session, (int)($input['admin_id'] ?? 0), trim((string)($input['status'] ?? '')), trim((string)($input['reason'] ?? '')));
    json_response(true, ['admin_id' => (int)$target['id'], 'status' => $target['status'], 'message' => 'Administrator status updated']);
} catch (Throwable $e) {
    $status = (int)$e->getCode();
    if ($status < 400 || $status > 599) $status = 500;
    json_response(false, null, $status === 500 ? 'Failed to update admin status' : $e->getMessage(), $status);
}
