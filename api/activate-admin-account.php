<?php
/**
 * Activate Admin Account API
 * POST /api/activate-admin-account.php
 * 
 * SUPER ADMIN ONLY
 * Activates pending admin accounts
 * Admin can only login after activation
 * Strict backend role verification
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../audit-logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    // AUTHENTICATION: Verify Super Admin session
    $session = requireSuperAdmin();
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if ($data === null) {
        ob_end_clean();
        json_response(false, null, 'Invalid JSON input');
    }
    
    // Get admin ID to activate
    $adminId = isset($data['admin_id']) ? (int)$data['admin_id'] : null;
    
    if (!$adminId) {
        ob_end_clean();
        json_response(false, null, 'Missing admin_id');
    }
    
    // Get admin account
    $stmt = $pdo->prepare("
        SELECT a.id, a.pf_number, a.full_name, a.status, a.role_id, 
               r.role_name, d.name as department_name
        FROM admin_users a
        JOIN roles r ON a.role_id = r.id
        LEFT JOIN departments d ON a.department_id = d.id
        WHERE a.id = ?
    ");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        ob_end_clean();
        json_response(false, null, 'Admin account not found');
    }
    
    // Can only activate pending_activation accounts
    if ($admin['status'] !== 'pending_activation') {
        ob_end_clean();
        json_response(false, null, 'Can only activate accounts with pending_activation status');
    }
    
    // Only admin-tier accounts should be activated through this (super admin is already active)
    if (!in_array($admin['role_name'], ['hr_coordinator', 'department_supervisor'], true)) {
        ob_end_clean();
        json_response(false, null, 'Invalid account type for this endpoint');
    }
    
    // Activate the account
    $stmt = $pdo->prepare("
        UPDATE admin_users 
        SET status = 'active', activated_at = NOW(), activated_by = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$session['admin_id'], $adminId]);
    
    if (!$result) {
        throw new Exception('Failed to activate admin account');
    }
    
    // Log the activation
    $auditLogger = $GLOBALS['auditLogger'];
    $auditLogger->logAdminActivation($session, $adminId);
    
    // Update admin creation history
    $stmt = $pdo->prepare("
        UPDATE admin_creation_history 
        SET activation_status = 'activated', activated_at = NOW(), activation_by_admin_id = ?
        WHERE created_admin_id = ?
    ");
    $stmt->execute([$session['admin_id'], $adminId]);
    
    ob_end_clean();
    json_response(true, [
        'admin_id' => $adminId,
        'pf_number' => $admin['pf_number'],
        'full_name' => $admin['full_name'],
        'status' => 'active',
        'department' => $admin['department_name'],
        'message' => 'Admin account activated successfully. Account can now login.'
    ]);
    
} catch (Exception $e) {
    error_log("Activate admin error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to activate admin account');
}

?>
