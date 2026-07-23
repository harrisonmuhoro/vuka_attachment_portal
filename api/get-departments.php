<?php
/**
 * Get Departments API
 * GET /api/get-departments.php
 * 
 * Retrieve all departments (Super Admin)
 * Used for admin creation and department management
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
    // Require admin authentication (super_admin, hr_coordinator, department_supervisor)
    if (ob_get_length() !== false) { ob_end_clean(); }
    $session = requireAnyAdmin();
    
    // Get all departments with admin count
    $stmt = $pdo->prepare("
        SELECT 
            d.id,
            d.name,
            d.description,
            d.min_admins,
            COUNT(CASE WHEN a.status = 'active' AND r.role_name IN ('department_supervisor','hr_coordinator') THEN 1 END) as admin_count
        FROM departments d
        LEFT JOIN admin_users a ON d.id = a.department_id
        LEFT JOIN roles r ON a.role_id = r.id
        GROUP BY d.id
        ORDER BY d.name
    ");
    
    $stmt->execute();
    $departments = $stmt->fetchAll();
    
    ob_end_clean();
    json_response(true, [
        'success' => true,
        'departments' => $departments
    ]);
    
} catch (Exception $e) {
    error_log("Get departments error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to retrieve departments');
}

?>
