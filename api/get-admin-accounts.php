<?php
/**
 * Get Admin Accounts Endpoint
 * GET /api/get-admin-accounts.php
 * 
 * Super Admin only — lists all department admin accounts
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    // AUTHENTICATION: Verify Super Admin session
    $session = requireSuperAdmin();

    // Fetch all admin accounts (exclude the super admin themselves)
    $stmt = $pdo->prepare("
        SELECT a.id, a.pf_number, a.full_name, a.email, a.department, a.status, 
               a.created_at, a.last_login, r.role_name
        FROM admin_users a
        LEFT JOIN roles r ON a.role_id = r.id
        WHERE a.id != ?
        ORDER BY a.department ASC, a.created_at DESC
    ");
    $stmt->execute([$session['admin_id']]);
    $admins = $stmt->fetchAll();
    
    // Count admins per department
    $deptCounts = [];
    foreach ($admins as $a) {
        $dept = $a['department'] ?? 'Unknown';
        if (!isset($deptCounts[$dept])) $deptCounts[$dept] = 0;
        $deptCounts[$dept]++;
    }
    
    // Available departments
    $departments = ['ICT', 'Health', 'Procurement', 'Finance', 'HR', 'Education', 'Agriculture', 'Water', 'Transport'];
    
    ob_end_clean();
    json_response(true, [
        'admins' => $admins,
        'department_counts' => $deptCounts,
        'departments' => $departments,
        'max_per_department' => 2
    ]);
    
} catch (Exception $e) {
    error_log("Get admin accounts error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to fetch admin accounts');
}
?>
