<?php
/**
 * Get Admin Accounts API
 * GET /api/get-admins.php
 * 
 * Protected: System Admin Only
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

try {
    // AUTHENTICATION: Verify Super Admin session
    $session = requireSuperAdmin();

    // Fetch All Admins with Role Names
    $stmt = $pdo->prepare("
        SELECT a.id, a.full_name, a.email, a.pf_number, a.department, a.status, a.created_at, r.role_name
        FROM admin_users a
        LEFT JOIN roles r ON a.role_id = r.id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    json_response(true, ['admins' => $admins]);

} catch (Exception $e) {
    error_log("Get Admins Error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Server error fetching admins');
}
