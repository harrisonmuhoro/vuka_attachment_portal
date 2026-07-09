# SECURE RBAC DEPLOYMENT CHECKLIST & VERIFICATION

## Pre-Deployment Verification

### Database Preparation
- [ ] **Database backup created**
  ```bash
  mysqldump -u root -p vuka_attachment_portal > backup-pre-deployment.sql
  ```

- [ ] **Run RBAC schema update**
  ```bash
  mysql -u root -p vuka_attachment_portal < database-rbac.sql
  ```

- [ ] **Verify tables created**
  ```sql
  SHOW TABLES;
  -- Should include: roles, departments, admin_users, sessions, audit_logs, etc.
  ```

- [ ] **Verify Super Admin account exists**
  ```sql
  SELECT * FROM admin_users WHERE pf_number = 'SUPER/ADMIN/001';
  ```

- [ ] **Verify roles table populated**
  ```sql
  SELECT * FROM roles;
  -- Should have: super_admin (1), department_admin (2), student (3)
  ```

- [ ] **Verify departments table populated**
  ```sql
  SELECT * FROM departments LIMIT 1;
  ```

### File Deployment
- [ ] **Core PHP files deployed**
  - [ ] `www/config.php` - Database configuration
  - [ ] `www/session-manager.php` - Session handling
  - [ ] `www/audit-logger.php` - Audit logging

- [ ] **API endpoints deployed**
  - [ ] `www/api/student-login.php`
  - [ ] `www/api/admin-login.php`
  - [ ] `www/api/logout.php`
  - [ ] `www/api/verify-session.php`
  - [ ] `www/api/create-admin-account.php`
  - [ ] `www/api/activate-admin-account.php`
  - [ ] `www/api/deactivate-admin-account.php`
  - [ ] `www/api/get-admin-accounts.php`
  - [ ] `www/api/get-audit-log.php`
  - [ ] `www/api/get-departments.php`
  - [ ] `www/api/get-dashboard-stats.php`
  - [ ] `www/api/get-user-info.php`
  - [ ] `www/api/get-user-submissions.php`

- [ ] **Frontend files deployed**
  - [ ] `www/admin-login.html` - Secured admin login
  - [ ] `www/student-login.html` - Student login
  - [ ] `www/super-admin-dashboard.html` - Super Admin dashboard
  - [ ] `www/department-admin-dashboard.html` - Department Admin dashboard
  - [ ] `www/student-dashboard.html` - Student dashboard

- [ ] **File permissions set correctly**
  ```bash
  chmod 755 /var/www/html/www/
  chmod 755 /var/www/html/www/api/
  chmod 755 /var/www/html/www/uploads/
  chmod 600 /var/www/html/www/config.php
  ```

- [ ] **Documentation deployed**
  - [ ] `www/RBAC_IMPLEMENTATION_GUIDE.md`
  - [ ] `www/SECURITY_CHECKLIST.md`

## Post-Deployment Testing

### Session Management Tests
- [ ] **Session creation works**
  - Login as student → Check session_id in:
    - Session database
    - Browser cookies
  
- [ ] **Session validation works**
  - Request protected API with valid session_id → Success
  - Request protected API with invalid session_id → 401 Unauthorized
  - Request with expired session_id → 401 Unauthorized

- [ ] **Session invalidation works**
  - Login → Session created
  - Logout → Session.is_valid = 0
  - Try logout session_id → 401 Unauthorized

- [ ] **IP/User agent validation works**
  - Session works with same IP
  - Session fails if user-agent changes

### Authentication Tests - Student Login
- [ ] **Valid student login**
  - POST /api/student-login.php with valid credentials
  - Response: success=true, redirect=/student-dashboard.html

- [ ] **Invalid student login**
  - POST with wrong password → error message
  - POST with non-existent student → error message

- [ ] **Unverified student cannot login**
  - Create student with verified=0
  - Attempt login → "Please verify your email"

- [ ] **Inactive student cannot login**
  - Deactivate student account (status=inactive)
  - Attempt login → "Account is inactive or suspended"

### Authentication Tests - Admin Login
- [ ] **Valid admin login**
  - POST /api/admin-login.php with valid credentials
  - Response includes: admin_id, pf_number, role, department
  - Redirect correct: super_admin → super-admin-dashboard, dept_admin → department-admin-dashboard

- [ ] **Pending activation admin cannot login**
  - Create admin with status=pending_activation
  - Attempt login → "Account not yet activated by Super Admin"

- [ ] **Invalid admin login**
  - POST with wrong password → "Invalid credentials"
  - POST with non-existent PF → "Invalid credentials"

- [ ] **Deactivated admin cannot login**
  - Deactivate admin (status=inactive)
  - Attempt login → "Account is inactive"

### Authorization Tests - Role-Based Access
- [ ] **Super Admin can access Super Admin APIs**
  - Call /api/create-admin-account.php → Success
  - Call /api/activate-admin-account.php → Success
  - Call /api/deactivate-admin-account.php → Success
  - Call /api/get-admin-accounts.php → Success
  - Call /api/get-audit-log.php → Success

- [ ] **Department Admin cannot access Super Admin APIs**
  - Call /api/create-admin-account.php as Dept Admin → 403 Forbidden
  - Call /api/activate-admin-account.php as Dept Admin → 403 Forbidden
  - Call /api/deactivate-admin-account.php as Dept Admin → 403 Forbidden

- [ ] **Student cannot access any admin APIs**
  - Call any admin API as student → 403 Forbidden or early logout

- [ ] **Department Admin isolation works**
  - Login as Dept Admin for HR
  - Call /api/get-submissions.php → Only HR submissions
  - Cannot access Finance department submissions

- [ ] **Student cannot access admin dashboards**
  - Login as student
  - Navigate to /super-admin-dashboard.html → Redirected to login
  - Navigate to /department-admin-dashboard.html → Redirected to login

### Admin Account Management Tests

#### Create Department Admin
- [ ] **Super Admin can create account**
  - POST /api/create-admin-account.php with all fields
  - Response: success=true, admin_id, status=pending_activation
  - Database: admin_users record created with status=pending_activation

- [ ] **Duplicate PF number prevented**
  - Try create with existing PF number
  - Response: error about duplicate

- [ ] **Duplicate email prevented**
  - Try create with existing email
  - Response: error about duplicate

- [ ] **New account cannot login yet**
  - Create account
  - Try login with temp password
  - Response: "Account not yet activated"

- [ ] **Audit log entry created**
  - Create account → Check audit_logs table
  - Should have: action=create_admin, admin_id, pf_number, department_id

#### Activate Admin Account
- [ ] **Super Admin can activate**
  - Create account (status=pending_activation)
  - Call /api/activate-admin-account.php
  - Response: success=true, status=active
  - Database: admin_users.status = active, activated_at set, activated_by set

- [ ] **Only pending_activation can be activated**
  - Try activate already active account
  - Response: error "Can only activate pending_activation"

- [ ] **Admin can login after activation**
  - Create → Activate → Login with temp password → Success

- [ ] **Audit log entry created**
  - Activate account → Check audit_logs
  - Should have: action=activate_admin, target_admin_pf logged

#### Deactivate Admin Account
- [ ] **Super Admin can deactivate**
  - Activate account (status=active)
  - Call /api/deactivate-admin-account.php
  - Response: success=true, status=inactive
  - Database: admin_users.status = inactive

- [ ] **Cannot deactivate self**
  - Login as Super Admin
  - Try deactivate own account
  - Response: error "Cannot deactivate your own Super Admin account"

- [ ] **Admin immediately logged out**
  - Activate admin → Admin logs in
  - Super Admin deactivates → Admin's session invalidated
  - Admin tries next request → 401 Unauthorized

- [ ] **Audit log entry created**
  - Deactivate account → Check audit_logs
  - Should have: action=deactivate_admin, reason logged

### Department Isolation Tests
- [ ] **Dept Admin sees only own department admins**
  - Login as HR Dept Admin
  - GET /api/get-admin-accounts.php
  - Results filtered to department_id=HR's ID only

- [ ] **Dept Admin sees only own department submissions**
  - Login as Finance Dept Admin
  - GET /api/get-submissions.php
  - Results filtered to department_applied='Finance' only

- [ ] **Super Admin sees all departments**
  - Login as Super Admin
  - GET /api/get-admin-accounts.php
  - See admins from all departments

### Audit Logging Tests
- [ ] **Login events logged**
  - Admin logs in → audit_logs entry with action=login
  - Check: admin_id, admin_pf, admin_role, ip_address logged

- [ ] **Admin creation logged**
  - Create admin → audit_logs entry with action=create_admin
  - Check: old_values=null, new_values contains admin details

- [ ] **Admin activation logged**
  - Activate admin → audit_logs entry with action=activate_admin
  - Check: old_values.status=pending_activation, new_values.status=active

- [ ] **Admin deactivation logged**
  - Deactivate admin → audit_logs entry with action=deactivate_admin
  - Check: reason field populated

- [ ] **Audit log queryable**
  - GET /api/get-audit-log.php
  - Filter by action
  - Filter by date range
  - Pagination works

### Session Timeout Tests
- [ ] **Session expires after timeout**
  - Login → Check session expires_at
  - Wait for expiration
  - Try request → 401 Unauthorized

- [ ] **Activity extends timeout**
  - Login → Request API every 5 minutes
  - Session should not expire
  - After inactivity, session expires

### Security Header Tests
- [ ] **Session cookie has Secure flag**
  - Check browser dev tools
  - Cookie should have "Secure" flag (HTTPS environments)

- [ ] **Session cookie has HttpOnly flag**
  - JavaScript cannot access session_id cookie
  - Prevents XSS attacks

- [ ] **Session cookie has SameSite attribute**
  - Prevents CSRF attacks

## Frontend Security Verification

### Dashboard Access Control
- [ ] **Super Admin dashboard requires Super Admin role**
  - Log out
  - Visit /super-admin-dashboard.html
  - Should redirect to /admin-login.html
  - Login as Dept Admin → Redirected to /department-admin-dashboard.html
  - Login as Super Admin → Access granted

- [ ] **Department Admin dashboard requires Dept Admin role**
  - Login as student → Cannot access
  - Login as Super Admin → Can access (optional based on design)
  - Login as Dept Admin → Can access

- [ ] **Student dashboard requires Student role**
  - Login as admin → Cannot access
  - Login as student → Can access

### Frontend Cannot Escalate Privileges
- [ ] **No hidden admin forms**
  - Student login form cannot create admin account
  - No JavaScript can modify role

- [ ] **Role displayed but not at risk from DOM manipulation**
  - Role shown in UI for reference only
  - Every API call checks role from database
  - Changing DOM doesn't grant access

- [ ] **Staff cannot create unauthorized accounts**
  - Try create admin account without going through API
  - Audit logs show attempt

## Minimum Admin Requirements

- [ ] **Each department minimum admins enforced**
  - Department requires minimum 2 admins
  - Try deactivate admin in department with only 2
  - System prevents or warns before deactivation

## Performance & Monitoring

- [ ] **Database performance acceptable**
  - Session validation < 100ms
  - Admin account queries < 200ms
  - Audit log queries < 500ms

- [ ] **Audit logs not causing performance issues**
  - Large audit logs don't slow down API
  - Pagination implemented correctly

- [ ] **Session cleanup working**
  - Old sessions removed after expiration
  - cleanupExpiredSessions() running correctly

## Security Compliance Tests

- [ ] **OWASP Top 10 considerations**
  - [ ] No SQL injection (using prepared statements)
  - [ ] No XSS (proper HTML escaping)
  - [ ] No CSRF (session validation)
  - [ ] Broken auth handled (status checking)
  - [ ] Broken access control handled (role verification)

- [ ] **Sensitive information not leaked**
  - Passwords never logged
  - Sensitive fields not displayed unnecessarily
  - Error messages don't reveal system info

- [ ] **Audit trail complete**
  - All admin actions logged
  - Cannot bypass audit logging
  - Logs include enough context for investigation

## Production Readiness

- [ ] **HTTPS enforced**
  - All connections secured with SSL/TLS
  - Secure cookies set properly
  - Forces HTTPS redirect

- [ ] **Logging configured**
  - PHP error logs directory set
  - Log files have appropriate permissions
  - Log rotation implemented

- [ ] **Backups working**
  - Database backups run daily
  - Backups tested for restore capability
  - Off-site backup copy maintained

- [ ] **Monitoring alerts configured**
  - Alert on multiple failed logins
  - Alert on deactivation of accounts
  - Alert on audit log sensitive actions

- [ ] **Documentation updated**
  - RBAC_IMPLEMENTATION_GUIDE.md deployed
  - Admin procedures documented
  - Incident response plan created

## Sign-Off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Security Officer | | | |
| System Administrator | | | |
| Project Manager | | | |

---

## Incident Response

### If Unauthorized Access Detected
1. Check audit logs immediately
2. Identify affected admin account
3. Verify account status
4. Review session records
5. Clear affected sessions
6. Notify account owner
7. Document incident

### If Privilege Escalation Attempt Detected
1. Log attempt details
2. Check if escalation successful (check role in database)
3. Reset account credentials
4. Review account activity
5. File security incident report
6. Consider account deactivation

### If Audit Log Tampering Suspected
1. Check database integrity
2. Review recent changes to audit_logs table
3. Check database backup integrity
4. File security incident report
5. Consider forensic analysis

---

## Post-Implementation Training

- [ ] **Super Admin trained on:**
  - Creating admin accounts
  - Activating/deactivating accounts
  - Reviewing audit logs
  - Emergency procedures

- [ ] **Department Admin trained on:**
  - Reviewing submissions
  - Status management
  - System navigation
  - Password security

- [ ] **IT Support trained on:**
  - Account lockout resolution
  - Password reset procedures
  - Backup/restore procedures
  - Monitoring dashboard

---

Document Version: 1.0.0
Last Updated: February 2026
Status: Ready for Deployment
