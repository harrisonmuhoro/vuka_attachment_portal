<?php
/**
 * Deactivate Admin Account API
 * POST /api/deactivate-admin-account.php
 * 
 * SUPER ADMIN ONLY
 * Deactivates active admin accounts
 * Admin cannot login after deactivation
 * Maintains audit trail for compliance
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
    
    // Get admin ID to deactivate
    $adminId = isset($data['admin_id']) ? (int)$data['admin_id'] : null;
    $reason = isset($data['reason']) ? trim($data['reason']) : '';
    
    if (!$adminId) {
        ob_end_clean();
        json_response(false, null, 'Missing admin_id');
    }
    
    // Prevent Super Admin from deactivating themselves
    if ($adminId === $session['admin_id']) {
        ob_end_clean();
        json_response(false, null, 'Cannot deactivate your own Super Admin account');
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
    
    // Can only deactivate active accounts
    if ($admin['status'] !== 'active') {
        ob_end_clean();
        json_response(false, null, 'Admin account is not currently active');
    }
    
    // Deactivate the account
    $stmt = $pdo->prepare("
        UPDATE admin_users 
        SET status = 'inactive', updated_at = NOW(), updated_by = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$session['admin_id'], $adminId]);
    
    if (!$result) {
        throw new Exception('Failed to deactivate admin account');
    }
    
    // Log the deactivation
    $auditLogger = $GLOBALS['auditLogger'];
    $auditLogger->logAdminDeactivation($session, $adminId, $reason);
    
    // Invalidate any existing sessions for this admin
    $stmt = $pdo->prepare("
        UPDATE sessions 
        SET is_valid = 0 
        WHERE admin_id = ?
    ");
    $stmt->execute([$adminId]);
    
    // Update admin creation history if exists
    $stmt = $pdo->prepare("
        UPDATE admin_creation_history 
        SET deactivation_reason = ?, deactivated_at = NOW()
        WHERE created_admin_id = ? AND deactivated_at IS NULL
    ");
    $stmt->execute([$reason, $adminId]);
    
    ob_end_clean();
    json_response(true, [
        'admin_id' => $adminId,
        'pf_number' => $admin['pf_number'],
        'full_name' => $admin['full_name'],
        'status' => 'inactive',
        'department' => $admin['department_name'],
        'message' => 'Admin account deactivated successfully. All active sessions have been terminated.'
    ]);
    
} catch (Exception $e) {
    error_log("Deactivate admin error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to deactivate admin account');
}

?>
