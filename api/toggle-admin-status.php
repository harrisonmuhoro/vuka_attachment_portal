<?php
/**
 * Toggle Admin Account Status
 * POST /api/toggle-admin-status.php
 * 
 * SUPER ADMIN ONLY
 * Activates/deactivates department admin accounts
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    // AUTHENTICATION: Verify Super Admin session
    $session = requireSuperAdmin();

    $data = json_decode(file_get_contents('php://input'), true);
    $adminId = isset($data['admin_id']) ? (int)$data['admin_id'] : 0;
    $newStatus = trim($data['status'] ?? '');
    
    if (!$adminId || empty($newStatus)) {
        ob_end_clean();
        json_response(false, null, 'Missing admin_id or status');
    }
    
    $validStatuses = ['active', 'inactive', 'pending_activation', 'deleted'];
    if (!in_array($newStatus, $validStatuses)) {
        ob_end_clean();
        json_response(false, null, 'Invalid status value');
    }
    
    // Prevent modifying own account
    if ($adminId == $session['admin_id']) {
        ob_end_clean();
        json_response(false, null, 'Cannot modify your own account');
    }
    
    // Verify target admin exists
    $stmt = $pdo->prepare("SELECT id, full_name, department FROM admin_users WHERE id = ?");
    $stmt->execute([$adminId]);
    $target = $stmt->fetch();
    
    if (!$target) {
        ob_end_clean();
        json_response(false, null, 'Admin account not found');
    }
    
    // Update status
    $stmt = $pdo->prepare("UPDATE admin_users SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $adminId]);
    
    // If deactivating, invalidate their sessions
    if ($newStatus === 'inactive' || $newStatus === 'deleted') {
        $stmt = $pdo->prepare("UPDATE sessions SET is_valid = 0 WHERE admin_id = ?");
        $stmt->execute([$adminId]);
    }
    
    $statusLabel = ucfirst(str_replace('_', ' ', $newStatus));
    ob_end_clean();
    json_response(true, [
        'message' => "{$target['full_name']} ({$target['department']}) is now: $statusLabel"
    ]);
    
} catch (Exception $e) {
    error_log("Toggle admin status error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to update admin status');
}
?>
