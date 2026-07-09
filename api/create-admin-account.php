<?php
/**
 * Create Admin Account API
 * POST /api/create-admin-account.php
 * 
 * SUPER ADMIN ONLY
 * Creates department admin accounts (max 2 per department)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    // AUTHENTICATION: Verify Super Admin session
    $session = requireSuperAdmin();

    // Parse input
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        ob_end_clean();
        json_response(false, null, 'Invalid JSON input');
    }
    
    $pfNumber = isset($data['pf_number']) ? trim($data['pf_number']) : '';
    $fullName = isset($data['full_name']) ? trim($data['full_name']) : '';
    $nationalId = isset($data['national_id']) ? trim($data['national_id']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    $department = isset($data['department']) ? trim($data['department']) : '';
    
    // Validate
    if (empty($fullName) || empty($pfNumber) || empty($nationalId) || empty($email) || empty($password) || empty($department)) {
        ob_end_clean();
        json_response(false, null, 'All fields are required');
    }
    
    if (strlen($password) < 6) {
        ob_end_clean();
        json_response(false, null, 'Password must be at least 6 characters');
    }

    // Validate National ID
    if (!preg_match('/^\d{6,9}$/', $nationalId)) {
        ob_end_clean();
        json_response(false, null, 'National ID must be 6 to 9 digits');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ob_end_clean();
        json_response(false, null, 'Invalid email address');
    }
    
    // Check max 2 per department
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM admin_users WHERE department = ? AND status != 'deleted'");
    $stmt->execute([$department]);
    $count = $stmt->fetch()['cnt'];
    
    if ($count >= 2) {
        ob_end_clean();
        json_response(false, null, "Department '$department' already has the maximum of 2 admin accounts");
    }
    
    // Check PF number uniqueness
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE pf_number = ?");
    $stmt->execute([$pfNumber]);
    if ($stmt->rowCount() > 0) {
        ob_end_clean();
        json_response(false, null, 'PF Number already exists');
    }
    
    // Check email uniqueness
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        ob_end_clean();
        json_response(false, null, 'Email already in use');
    }
    
    $roleId = isset($data['role_id']) ? (int)$data['role_id'] : 3; // Default to Supervisor (3)
    
    // Validate Role ID (Must be 2=HR or 3=Supervisor)
    // Super Admin (1) cannot be created via this API for security, or can be if you want
    if (!in_array($roleId, [2, 3])) {
         // Optionally allow creating other admins if needed, but for now restrict to HR/Supervisor
         // matching the frontend dropdown
         $roleId = 3; 
    }
    
    // Get department ID
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
    $stmt->execute([$department]);
    $deptInfo = $stmt->fetch();
    $departmentId = $deptInfo ? $deptInfo['id'] : null;

    // Create account
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO admin_users (national_id, pf_number, full_name, email, password_hash, department, department_id, role_id, status, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
    ");
    $stmt->execute([$nationalId, $pfNumber, $fullName, $email, $hashedPassword, $department, $departmentId, $roleId, $session['admin_id']]);
    
    $newId = $pdo->lastInsertId();

    // Log Creation History
    $stmt = $pdo->prepare("
        INSERT INTO admin_creation_history (created_admin_id, created_by_admin_id, role_id, department_id, activation_status)
        VALUES (?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$newId, $session['admin_id'], $roleId, $departmentId]);
    
    ob_end_clean();
    json_response(true, [
        'message' => "Admin account created for $fullName in $department department",
        'admin_id' => $newId
    ]);
    
} catch (Exception $e) {
    error_log("Create admin error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to create admin account: ' . $e->getMessage());
}
?>
