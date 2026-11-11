<?php
/**
 * Session Manager - Secure Session Handling with Role-Based Access Control
 * Handles session creation, validation, and role-based authorization
 * 
 * Security Features:
 * - Session validation on every protected request
 * - Role verification from database (not from session/cookies)
 * - IP address and user agent validation
 * - Session expiration handling
 * - CSRF token generation and validation
 */

require_once __DIR__ . '/config/config.php';

// Native PHP session is only a fallback transport now (Bearer token is primary).
// Start it defensively so including this file after output doesn't emit warnings.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

class SessionManager {

    const SESSION_TIMEOUT = 28800; // 8 hours (matches login endpoints)

    // 4-role model (matches roles table + login redirects)
    const SUPER_ADMIN_ROLE_ID   = 1; // super_admin
    const HR_ROLE_ID            = 2; // hr_coordinator
    const SUPERVISOR_ROLE_ID    = 3; // department_supervisor
    const STUDENT_ROLE_ID       = 4; // student

    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create session after successful authentication
     * BACKEND CONTROLLED - Role must be verified from database
     * 
     * @param int $userId or $adminId
     * @param string $userType 'student' or 'admin'
     * @param int $roleId - Retrieved from database
     * @return array Session data
     */
    public function createSession($user_or_admin_id, $userType, $roleId, $isAdmin = false) {
        try {
            // Validate parameters
            if (!in_array($userType, ['student', 'admin'])) {
                throw new Exception('Invalid user type');
            }
            
            // Generate secure session ID
            $sessionId = bin2hex(random_bytes(32));
            $ipAddress = $this->getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $expiresAt = date('Y-m-d H:i:s', time() + self::SESSION_TIMEOUT);
            
            // Determine if admin or student
            $stmt = $this->pdo->prepare("
                INSERT INTO sessions (
                    session_id, user_id, admin_id, user_type, role_id, 
                    ip_address, user_agent, created_at, last_activity, expires_at, is_valid
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, 1
                )
            ");
            
            if ($userType === 'admin') {
                $result = $stmt->execute([
                    $sessionId, 
                    null,
                    $user_or_admin_id,
                    'admin',
                    $roleId,
                    $ipAddress,
                    $userAgent,
                    $expiresAt
                ]);
            } else {
                $result = $stmt->execute([
                    $sessionId,
                    $user_or_admin_id,
                    null,
                    'student',
                    $roleId,
                    $ipAddress,
                    $userAgent,
                    $expiresAt
                ]);
            }
            
            if (!$result) {
                throw new Exception('Failed to create session');
            }
            
            // Store in secure cookie and session
            setcookie(
                'session_id',
                $sessionId,
                time() + self::SESSION_TIMEOUT,
                '/',
                '',
                true, // Secure (HTTPS only)
                true  // HttpOnly (prevents JavaScript access)
            );
            
            $_SESSION['session_id'] = $sessionId;
            $_SESSION['user_type'] = $userType;
            $_SESSION['user_id'] = $userType === 'student' ? $user_or_admin_id : null;
            $_SESSION['admin_id'] = $userType === 'admin' ? $user_or_admin_id : null;
            
            return [
                'success' => true,
                'session_id' => $sessionId,
                'user_type' => $userType,
                'expires_in' => self::SESSION_TIMEOUT
            ];
            
        } catch (Exception $e) {
            error_log("Session creation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Session creation failed'];
        }
    }
    
    /**
     * Validate session and get verified role from database
     * CRITICAL: Role is verified from database, not from session data
     * This prevents users from manipulating their role
     * 
     * @param string $sessionId
     * @return array|false Session data with verified role
     */
    public function validateSession($sessionId = null) {
        try {
            if (!$sessionId) {
                // Bearer token first (frontend uses sessionStorage + Authorization header),
                // then cookie/PHP-session as fallback for server-rendered pages.
                $sessionId = get_bearer_token()
                    ?? $_COOKIE['session_id']
                    ?? $_SESSION['session_id']
                    ?? null;
            }

            error_log("SessionManager validateSession - Received Token: " . ($sessionId ? $sessionId : "NONE") . "\n", 3, __DIR__ . "/debug.log");
            error_log("SessionManager validateSession - HTTP_AUTHORIZATION: " . ($_SERVER['HTTP_AUTHORIZATION'] ?? "NONE") . "\n", 3, __DIR__ . "/debug.log");


            if (!$sessionId) {
                return false;
            }

            // Get session from database. `department` is the admin_users free-text column
            // used for isolation; `department_name` comes from the departments FK.
            $stmt = $this->pdo->prepare("
                SELECT s.*,
                       r.role_name, r.level as role_level,
                       u.id as user_id, u.full_name as user_name, u.email as user_email, u.status as user_status,
                       a.id as admin_id, a.pf_number, a.full_name as admin_name, a.email as admin_email,
                       a.status as admin_status, a.department, a.department_id, d.name as department_name
                FROM sessions s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN admin_users a ON s.admin_id = a.id
                LEFT JOIN roles r ON s.role_id = r.id
                LEFT JOIN departments d ON a.department_id = d.id
                WHERE s.session_id = ? AND s.is_valid = 1 AND s.expires_at > NOW()
                LIMIT 1
            ");
            
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();
            
            if (!$session) {
                error_log("Session not found in DB or expired. Token: $sessionId\n", 3, __DIR__ . "/debug.log");
                return false;
            }

            // Log IP/UA anomalies for audit, but do not hard-reject:
            // the 256-bit random session token is the primary guard, and strict
            // UA/IP matching breaks legitimate clients behind proxies / mobile networks.
            $this->validateSecurityHeaders($session);

            // CRITICAL: Verify account status
            if ($session['user_type'] === 'student') {
                if ($session['user_status'] !== 'active') {
                    error_log("Session validation failed: Student status is not active (" . $session['user_status'] . ")\n", 3, __DIR__ . "/debug.log");
                    return false;
                }
            } else {
                // Admin account must be active
                if ($session['admin_status'] !== 'active') {
                    error_log("Session validation failed: Admin status is not active (" . $session['admin_status'] . ")\n", 3, __DIR__ . "/debug.log");
                    return false;
                }
            }

            // Normalize a single 'email' + 'full_name' regardless of user type
            $session['email'] = $session['user_type'] === 'admin'
                ? ($session['admin_email'] ?? null)
                : ($session['user_email'] ?? null);
            $session['full_name'] = $session['user_type'] === 'admin'
                ? ($session['admin_name'] ?? null)
                : ($session['user_name'] ?? null);

            // Update last activity
            $this->updateLastActivity($sessionId);

            return $session;
            
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            error_log("Exception: " . $e->getMessage() . "\n", 3, __DIR__ . "/debug.log");
            return false;
        }
    }
    
    /**
     * Check if user has required role
     * Backend-enforced role checking
     * 
     * @param array $session
     * @param string|array $requiredRole
     * @return bool
     */
    public function hasRole($session, $requiredRole) {
        if (!$session) {
            return false;
        }
        
        if (is_array($requiredRole)) {
            return in_array($session['role_name'], $requiredRole);
        }
        
        return $session['role_name'] === $requiredRole;
    }
    
    /**
     * Check if admin can manage a department
     * Prevents admins from accessing other departments
     * 
     * @param array $session
     * @param int $departmentId
     * @return bool
     */
    public function canManageDepartment($session, $departmentId) {
        if (!$session) {
            return false;
        }
        
        // Super admin and HR coordinators can manage all departments
        if ($session['role_name'] === 'super_admin' || $session['role_name'] === 'hr_coordinator') {
            return true;
        }

        // Department supervisor can only manage their own
        if ($session['role_name'] === 'department_supervisor') {
            return (int)$session['department_id'] === (int)$departmentId;
        }

        return false;
    }

    /**
     * Get the department this session is scoped to (free-text column on admin_users).
     * 'ALL' or null means no restriction (super_admin / HR).
     */
    public function getScopedDepartment($session) {
        if (!$session) return null;
        if (in_array($session['role_name'], ['super_admin', 'hr_coordinator'], true)) {
            return 'ALL';
        }
        return $session['department'] ?? null;
    }
    
    /**
     * Get user role level (for permission hierarchies)
     * Super Admin (1) > Department Admin (2) > Student (3)
     * 
     * @param array $session
     * @return int
     */
    public function getRoleLevel($session) {
        return $session['role_level'] ?? 999;
    }
    
    /**
     * Check if user is Super Admin
     * 
     * @param array $session
     * @return bool
     */
    public function isSuperAdmin($session) {
        return $session['role_name'] === 'super_admin';
    }
    
    /**
     * Check if user is HR Coordinator
     *
     * @param array $session
     * @return bool
     */
    public function isHR($session) {
        return $session['role_name'] === 'hr_coordinator';
    }

    /**
     * Check if user is Department Supervisor
     *
     * @param array $session
     * @return bool
     */
    public function isSupervisor($session) {
        return $session['role_name'] === 'department_supervisor';
    }

    /**
     * Check if user is any admin-tier role (not a student)
     *
     * @param array $session
     * @return bool
     */
    public function isAdminTier($session) {
        return in_array($session['role_name'], ['super_admin', 'hr_coordinator', 'department_supervisor'], true);
    }
    
    /**
     * Check if user is Student
     * 
     * @param array $session
     * @return bool
     */
    public function isStudent($session) {
        return $session['role_name'] === 'student';
    }
    
    /**
     * Invalidate session (logout or security incident)
     * 
     * @param string $sessionId
     * @return bool
     */
    public function invalidateSession($sessionId = null) {
        try {
            if (!$sessionId) {
                $sessionId = $_COOKIE['session_id'] ?? $_SESSION['session_id'] ?? null;
            }
            
            if (!$sessionId) {
                return false;
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE sessions 
                SET is_valid = 0 
                WHERE session_id = ?
            ");
            
            $result = $stmt->execute([$sessionId]);
            
            // Clear cookies and session
            setcookie('session_id', '', time() - 3600, '/');
            session_destroy();
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Session invalidation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate CSRF token for form protection
     * 
     * @return string
     */
    public function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     * 
     * @param string $token
     * @return bool
     */
    public function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * PRIVATE: Validate IP and user agent consistency
     * 
     * @param array $session
     * @return bool
     */
    private function validateSecurityHeaders($session) {
        $currentIP = $this->getClientIP();
        $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Log-only anomaly detection (see validateSession for rationale).
        if ($session['ip_address'] !== $currentIP) {
            error_log("Session IP change: {$session['ip_address']} -> {$currentIP} (session {$session['session_id']})");
        }
        if ($session['user_agent'] !== $currentUA) {
            error_log("Session UA change detected (session {$session['session_id']})");
        }

        return true;
    }
    
    /**
     * PRIVATE: Update session last activity
     * 
     * @param string $sessionId
     * @return bool
     */
    private function updateLastActivity($sessionId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE sessions 
                SET last_activity = NOW() 
                WHERE session_id = ?
            ");
            return $stmt->execute([$sessionId]);
        } catch (Exception $e) {
            error_log("Update last activity error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * PRIVATE: Get client IP address
     * Handles proxies and various server configurations
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
    
    /**
     * Clean up expired sessions (run periodically)
     * 
     * @return int Number of deleted sessions
     */
    public function cleanupExpiredSessions() {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM sessions 
                WHERE expires_at < NOW()
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}

// Create global session manager instance
if (!isset($GLOBALS['sessionManager'])) {
    $GLOBALS['sessionManager'] = new SessionManager($pdo);
}

/**
 * Require authentication for protected routes
 * This function MUST be called on every protected page/API endpoint
 * 
 * @param string|array $requiredRole Role(s) required to access
 * @param bool $allowMultipleRoles Whether to allow multiple roles
 * @return array Session data
 */
function requireAuth($requiredRole = null, $allowMultipleRoles = false) {
    $sessionManager = $GLOBALS['sessionManager'];
    
    $session = $sessionManager->validateSession();
    
    if (!$session) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
        }
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized: Invalid or expired session',
            'authenticated' => false,
            'redirect' => '/index.php'
        ]);
        exit;
    }

    // Check role if specified (accepts a single role or an array of allowed roles)
    if ($requiredRole !== null) {
        $hasRole = $sessionManager->hasRole($session, $requiredRole);

        if (!$hasRole) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(403);
            }
            echo json_encode([
                'success' => false,
                'error' => 'Forbidden: Insufficient permissions',
                'user_role' => $session['role_name']
            ]);
            exit;
        }
    }

    return $session;
}

/**
 * Require Super Admin role
 * 
 * @return array Session data
 */
function requireSuperAdmin() {
    return requireAuth('super_admin');
}

/**
 * Require HR Coordinator role
 *
 * @return array Session data
 */
function requireHR() {
    return requireAuth('hr_coordinator');
}

/**
 * Require Department Supervisor role
 *
 * @return array Session data
 */
function requireSupervisor() {
    return requireAuth('department_supervisor');
}

/**
 * Require Student role
 *
 * @return array Session data
 */
function requireStudent() {
    return requireAuth('student');
}

/**
 * Require any admin-tier role (super_admin, hr_coordinator, department_supervisor).
 * Optionally pass a subset of allowed admin roles.
 *
 * @param array $roles
 * @return array Session data
 */
function requireAnyAdmin($roles = ['super_admin', 'hr_coordinator', 'department_supervisor']) {
    return requireAuth($roles);
}

/**
 * Backward-compatible alias — old code called requireDepartmentAdmin().
 * Now maps to supervisor OR HR OR super_admin (any admin tier).
 *
 * @return array Session data
 */
function requireDepartmentAdmin() {
    return requireAuth(['super_admin', 'hr_coordinator', 'department_supervisor']);
}

?>
