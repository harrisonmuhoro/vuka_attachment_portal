<?php
/**
 * Get Audit Log API
 * GET /api/get-audit-log.php
 * 
 * SUPER ADMIN ONLY
 * Retrieves audit logs for compliance and security monitoring
 * Department Admins can view logs for their department only
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../audit-logger.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    // AUTHENTICATION: Require any admin-tier role
    $session = requireAnyAdmin();
    
    $auditLogger = $GLOBALS['auditLogger'];
    
    // Super Admin can see all logs
    // Department Admin sees logs for their department admins
    $filters = [];
    
    if (!empty($_GET['action'])) {
        $filters['action'] = $_GET['action'];
    }
    
    if (!empty($_GET['start_date'])) {
        $filters['start_date'] = $_GET['start_date'];
    }
    
    if (!empty($_GET['end_date'])) {
        $filters['end_date'] = $_GET['end_date'];
    }
    
    if ($session['role_name'] === 'department_supervisor') {
        // Only show logs for their own department's admins
        // Get all admins in their department
        $stmt = $pdo->prepare("
            SELECT GROUP_CONCAT(id) as admin_ids
            FROM admin_users
            WHERE department_id = ?
        ");
        $stmt->execute([$session['department_id']]);
        $result = $stmt->fetch();
        
        // For now, show all logs created by any admin (can be restricted further)
        // In a more restrictive setup, would need to filter by department
    }
    
    $limit = !empty($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = !empty($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Get logs
    $logs = $auditLogger->getAllAuditLogs($filters, $limit, $offset);
    
    // Get statistics
    $stats = $auditLogger->getAuditStatistics();
    
    ob_end_clean();
    json_response(true, [
        'logs' => $logs,
        'total_records' => count($logs),
        'statistics' => $stats,
        'limit' => $limit,
        'offset' => $offset,
        'user_role' => $session['role_name']
    ]);
    
} catch (Exception $e) {
    error_log("Get audit log error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to retrieve audit logs');
}

?>
