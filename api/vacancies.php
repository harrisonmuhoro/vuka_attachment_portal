<?php
/**
 * Vacancies API
 * Handles CRUD for vacancies
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    // 1. GET Requests (List Vacancies)
    if ($method === 'GET') {
        // Auto-close any approved vacancies whose deadline has passed (Feature #7).
        $pdo->exec("UPDATE vacancies SET status = 'closed'
                    WHERE deadline_at IS NOT NULL AND deadline_at < NOW() AND status = 'approved'");

        $department = isset($_GET['department']) ? $_GET['department'] : null;
        $status = isset($_GET['status']) ? $_GET['status'] : 'approved'; // Default to approved (public view)
        
        $query = "SELECT v.*, u.full_name as creator_name 
                  FROM vacancies v 
                  LEFT JOIN admin_users u ON v.created_by = u.id 
                  WHERE 1=1";
        $params = [];
        
        if ($department) {
            $query .= " AND v.department_name = ?";
            $params[] = $department;
        }
        
        if ($status !== 'all') {
            $query .= " AND v.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY v.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $vacancies = $stmt->fetchAll();
        
        ob_end_clean();
        json_response(true, ['vacancies' => $vacancies]);
    }
    
    // 2. POST Requests (Create, Update Status)
    if ($method === 'POST') {
        // Require an admin-tier session (create allowed for supervisor/hr/super_admin).
        $session = requireAnyAdmin();

        $action = $input['action'] ?? '';

        // --- CREATE VACANCY (Supervisor) ---
        if ($action === 'create') {
            // Supervisors only (or Admin/HR) — all admin tiers may create.

            $title = $input['title'];
            $desc = $input['description'];
            $skills = $input['skills'];
            $count = $input['positions_count'];
            $deptName = $session['department']; // Enforce their department
            
            // If HR/Admin, allow setting department
            if ($session['role_name'] === 'hr_coordinator' || $session['role_name'] === 'super_admin') {
                $deptName = $input['department'] ?? $deptName;
            }

            // Optional application deadline (Feature #7)
            $deadlineAt = !empty($input['deadline_at']) ? $input['deadline_at'] : null;

            $stmt = $pdo->prepare("INSERT INTO vacancies (department_name, title, description, skills_required, positions_count, vacancy_type, deadline_at, status, created_by)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
            $vacancyType = $input['vacancy_type'] ?? 'attachment';
            $stmt->execute([$deptName, $title, $desc, $skills, $count, $vacancyType, $deadlineAt, $session['admin_id']]);
            
            ob_end_clean();
            json_response(true, ['message' => 'Vacancy request submitted for approval.']);
        }
        
        // --- APPROVE/REJECT VACANCY (HR) ---
        if ($action === 'update_status') {
             if ($session['role_name'] !== 'hr_coordinator' && $session['role_name'] !== 'super_admin') {
                 json_response(false, null, 'Unauthorized: HR access only');
             }
             
             $vacancyId = $input['vacancy_id'];
             $newStatus = $input['status']; // approved, closed, rejected?
             
             $stmt = $pdo->prepare("UPDATE vacancies SET status = ? WHERE id = ?");
             $stmt->execute([$newStatus, $vacancyId]);
             
             ob_end_clean();
             json_response(true, ['message' => "Vacancy status updated to $newStatus"]);
        }

        // --- DELETE/END VACANCY (Supervisor/Admin) ---
        if ($action === 'delete' || $action === 'end') {
            $vacancyId = $input['vacancy_id'];
            
            // Verify ownership/permission
            $stmt = $pdo->prepare("SELECT department_name FROM vacancies WHERE id = ?");
            $stmt->execute([$vacancyId]);
            $vac = $stmt->fetch();
            
            if (!$vac) {
                ob_end_clean();
                json_response(false, null, 'Vacancy not found');
            }
            
            if ($session['role_name'] === 'department_supervisor' && $vac['department_name'] !== $session['department']) {
                ob_end_clean();
                json_response(false, null, 'Unauthorized: Cannot modify other department vacancies');
            }
            
            if ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM vacancies WHERE id = ?");
                $stmt->execute([$vacancyId]);
                ob_end_clean();
                json_response(true, ['message' => 'Vacancy deleted successfully.']);
            } else if ($action === 'end') {
                $stmt = $pdo->prepare("UPDATE vacancies SET status = 'closed' WHERE id = ?");
                $stmt->execute([$vacancyId]);
                ob_end_clean();
                json_response(true, ['message' => 'Vacancy marked as over.']);
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Vacancy API Error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Server Error: ' . $e->getMessage());
}
?>
