<?php


class AuditLogger {
    
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Log an action performed by an admin
     * 
     * @param array $session Session data of the admin performing action
     * @param string $action Action type (create_admin, activate_admin, etc.)
     * @param string $entityType Type of entity affected
     * @param int $entityId ID of entity affected
     * @param array $oldValues Previous values (for updates)
     * @param array $newValues New values
     * @param int $targetAdminId If action affects another admin
     * @param string $details Additional details
     * @return bool Success
     */
    public function log(
        $session,
        $action,
        $entityType,
        $entityId = null,
        $oldValues = null,
        $newValues = null,
        $targetAdminId = null,
        $details = null
    ) {
        if (!$session || !isset($session['admin_id'])) {
            error_log("Audit: Cannot log action without valid admin session");
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (
                    admin_id, admin_pf, admin_role, action, entity_type, entity_id,
                    target_admin_id, target_admin_pf, old_values, new_values,
                    ip_address, user_agent, details, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, NOW()
                )
            ");
            
            // Get target admin PF if available
            $targetAdminPF = null;
            if ($targetAdminId) {
                $stmt2 = $this->pdo->prepare("SELECT pf_number FROM admin_users WHERE id = ?");
                $stmt2->execute([$targetAdminId]);
                $targetAdmin = $stmt2->fetch();
                $targetAdminPF = $targetAdmin['pf_number'] ?? null;
            }
            
            return $stmt->execute([
                $session['admin_id'],
                $session['pf_number'],
                $session['role_name'],
                $action,
                $entityType,
                $entityId,
                $targetAdminId,
                $targetAdminPF,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $details
            ]);
            
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log admin account creation
     * 
     * @param array $session Session of Super Admin creating account
     * @param int $newAdminId ID of newly created admin
     * @param string $role Role assigned
     * @param int $departmentId Department assigned
     * @return bool
     */
    public function logAdminCreation($session, $newAdminId, $role, $departmentId = null) {
        // Get new admin details
        $stmt = $this->pdo->prepare("
            SELECT id, pf_number, full_name, email, role_id, department_id 
            FROM admin_users 
            WHERE id = ?
        ");
        $stmt->execute([$newAdminId]);
        $admin = $stmt->fetch();
        
        return $this->log(
            $session,
            'create_admin',
            'admin_user',
            $newAdminId,
            null,
            [
                'pf_number' => $admin['pf_number'],
                'full_name' => $admin['full_name'],
                'email' => $admin['email'],
                'role' => $role,
                'department_id' => $departmentId
            ],
            $newAdminId,
            "New {$role} account created: {$admin['full_name']}"
        );
    }
    
    /**
     * Log admin activation
     * 
     * @param array $session Session of Super Admin activating
     * @param int $adminId ID of admin being activated
     * @return bool
     */
    public function logAdminActivation($session, $adminId) {
        $stmt = $this->pdo->prepare("
            SELECT pf_number, full_name, status 
            FROM admin_users 
            WHERE id = ?
        ");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();
        
        return $this->log(
            $session,
            'activate_admin',
            'admin_user',
            $adminId,
            ['status' => 'pending_activation'],
            ['status' => 'active'],
            $adminId,
            "Admin account activated: {$admin['pf_number']}"
        );
    }
    
    /**
     * Log admin deactivation
     * 
     * @param array $session Session of Super Admin deactivating
     * @param int $adminId ID of admin being deactivated
     * @param string $reason Reason for deactivation
     * @return bool
     */
    public function logAdminDeactivation($session, $adminId, $reason = null) {
        $stmt = $this->pdo->prepare("
            SELECT pf_number, full_name, status 
            FROM admin_users 
            WHERE id = ?
        ");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();
        
        return $this->log(
            $session,
            'deactivate_admin',
            'admin_user',
            $adminId,
            ['status' => 'active'],
            ['status' => 'inactive'],
            $adminId,
            "Admin account deactivated: {$admin['pf_number']}. Reason: {$reason}"
        );
    }
    
    /**
     * Log submission status change
     * 
     * @param array $session Session of admin changing status
     * @param int $submissionId ID of submission
     * @param string $oldStatus Previous status
     * @param string $newStatus New status
     * @param string $notes Review notes
     * @return bool
     */
    public function logSubmissionStatusChange($session, $submissionId, $oldStatus, $newStatus, $notes = null) {
        return $this->log(
            $session,
            'update_submission_status',
            'submission',
            $submissionId,
            ['status' => $oldStatus],
            ['status' => $newStatus],
            null,
            "Submission status changed: {$oldStatus} -> {$newStatus}. Notes: {$notes}"
        );
    }
    
    /**
     * Log login event
     * 
     * @param int $adminId Admin ID
     * @param string $pf Admin PF number
     * @param string $role Admin role
     * @return bool
     */
    public function logLogin($adminId, $pf, $role) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_logs (
                    admin_id, admin_pf, admin_role, action, entity_type,
                    ip_address, user_agent, created_at
                ) VALUES (
                    ?, ?, ?, 'login', 'admin_user',
                    ?, ?, NOW()
                )
            ");
            
            return $stmt->execute([
                $adminId,
                $pf,
                $role,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (Exception $e) {
            error_log("Login log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get audit log for an admin account
     * 
     * @param int $adminId ID of admin
     * @param int $limit Number of records to return
     * @return array Audit records
     */
    public function getAdminAuditLog($adminId, $limit = 100) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM audit_logs
                WHERE target_admin_id = ? OR admin_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$adminId, $adminId, $limit]);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get audit log error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAllAuditLogs($filters = [], $limit = 100, $offset = 0) {
        try {
            $query = "SELECT * FROM audit_logs WHERE 1=1";
            $params = [];
            
            if (!empty($filters['action'])) {
                $query .= " AND action = ?";
                $params[] = $filters['action'];
            }
            
            if (!empty($filters['admin_id'])) {
                $query .= " AND admin_id = ?";
                $params[] = $filters['admin_id'];
            }
            
            if (!empty($filters['start_date'])) {
                $query .= " AND created_at >= ?";
                $params[] = $filters['start_date'] . ' 00:00:00';
            }
            
            if (!empty($filters['end_date'])) {
                $query .= " AND created_at <= ?";
                $params[] = $filters['end_date'] . ' 23:59:59';
            }
            
            $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get all audit logs error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAuditStatistics() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    action,
                    COUNT(*) as count,
                    MAX(created_at) as last_occurrence
                FROM audit_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY action
                ORDER BY count DESC
            ");
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get audit statistics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * PRIVATE: Get client IP
     * 
     * @return string
     */
    private function getClientIP() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}

// Create global audit logger instance
if (!isset($GLOBALS['auditLogger'])) {
    $GLOBALS['auditLogger'] = new AuditLogger($pdo);
}

?>
