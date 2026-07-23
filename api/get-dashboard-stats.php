<?php
/**
 * Get Dashboard Statistics API
 * GET /api/get-dashboard-stats.php
 * 
 * Provides dashboard statistics based on user role
 * Super Admin: System-wide stats
 * Department Admin: Department-specific stats
 * Students: Personal submission stats
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    $session = requireAuth();
    
    $stats = [];
    
    if ($session['role_name'] === 'super_admin') {
        // Super Admin stats - system-wide
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_users");
        $result = $stmt->fetch();
        $stats['total_admins'] = $result['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_users WHERE status = 'active'");
        $result = $stmt->fetch();
        $stats['active_admins'] = $result['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_users WHERE status = 'pending_activation'");
        $result = $stmt->fetch();
        $stats['pending_activation'] = $result['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $result = $stmt->fetch();
        $stats['total_students'] = $result['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM submissions");
        $result = $stmt->fetch();
        $stats['total_submissions'] = $result['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM departments");
        $result = $stmt->fetch();
        $stats['departments'] = $result['total'];
        
    } else if ($session['role_name'] === 'hr_coordinator' || $session['role_name'] === 'department_supervisor') {
        // HR / Department Supervisor stats - department-specific.
        // super_admin/hr scope to 'ALL'; supervisors to their own department.
        $scope = $GLOBALS['sessionManager']->getScopedDepartment($session);

        $where = ($scope === 'ALL') ? "1=1" : "s.department_applied = :dept";
        $stmt = $pdo->prepare("
            SELECT
                COUNT(s.id) as total_submissions,
                SUM(CASE WHEN s.status IN ('applied','pending') THEN 1 ELSE 0 END) as pending_submissions,
                SUM(CASE WHEN s.status IN ('accepted','deployed','ongoing') THEN 1 ELSE 0 END) as approved_submissions,
                SUM(CASE WHEN s.status = 'rejected' THEN 1 ELSE 0 END) as rejected_submissions
            FROM submissions s
            WHERE $where
        ");

        if ($scope === 'ALL') {
            $stmt->execute();
        } else {
            $stmt->execute([':dept' => $scope]);
        }
        $result = $stmt->fetch();

        if ($result) {
            $stats = $result;
        }

    } else if ($session['role_name'] === 'student') {
        // Student stats - personal
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_submissions,
                SUM(CASE WHEN status IN ('applied','pending') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status IN ('accepted','deployed','ongoing') THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM submissions
            WHERE user_id = ?
        ");
        
        $stmt->execute([$session['user_id']]);
        $result = $stmt->fetch();
        
        if ($result) {
            $stats = $result;
        }
    }
    
    ob_end_clean();
    json_response(true, [
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Get dashboard stats error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to retrieve dashboard statistics');
}

?>
