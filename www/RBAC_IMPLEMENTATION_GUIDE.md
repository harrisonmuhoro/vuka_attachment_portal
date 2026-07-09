# VUKA ATTACHMENT & INTERNSHIP PORTAL — RBAC IMPLEMENTATION GUIDE

**Version:** 2.0.0
**Updated:** July 2026
**Auth model:** Bearer-token sessions
**Roles:** 4-tier (super_admin, hr_coordinator, department_supervisor, student)

---

## Executive Summary

This document describes the Role-Based Access Control (RBAC) system for the **Vuka Attachment & Internship Portal**. Authorization is enforced entirely on the backend: every protected endpoint verifies the caller's role from the database on each request, and the frontend cannot override it.

> **v2 note:** This guide supersedes the original 3-role/cookie design. The live system uses **4 roles** and a **Bearer-token** transport (the frontend stores the session token in `sessionStorage` and sends it as `Authorization: Bearer <token>`). The database is `vuka_attachment_portal`.

---

## Role Hierarchy

```
┌─────────────────────────────────────────────┐
│           SUPER ADMIN (role_id 1)            │
│   • Full system control                      │
│   • Create/activate/deactivate admins        │
│   • View audit logs, all departments         │
└───────────────┬──────────────────────────────┘
                │
        ┌───────┴───────────────┐
        │                       │
┌───────▼─────────┐   ┌─────────▼──────────┐
│ HR COORDINATOR  │   │ DEPARTMENT         │
│ (role_id 2)     │   │ SUPERVISOR (id 3)  │
│ • Approve/close │   │ • Create vacancy   │
│   vacancies     │   │   requests         │
│ • See all depts │   │ • Review own-dept  │
│ • Placement     │   │   submissions only │
└─────────────────┘   └────────────────────┘
        │
┌───────▼──────────────────────────────────────┐
│            STUDENT / APPLICANT (role_id 4)    │
│   • Register, verify email, log in            │
│   • Browse approved vacancies (Opportunities) │
│   • Submit one application + documents        │
│   • View own status only                      │
└───────────────────────────────────────────────┘
```

| Role | role_id | level | Dashboard |
|------|---------|-------|-----------|
| super_admin | 1 | 1 | `pages/admin_dashboard.php` |
| hr_coordinator | 2 | 2 | `pages/hr_dashboard.php` |
| department_supervisor | 3 | 3 | `pages/supervisor_dashboard.php` |
| student | 4 | 4 | `pages/student_dashboard.php` |

---

## Authentication & Transport

**Login endpoints** issue a random 256-bit session token (`bin2hex(random_bytes(32))`), persist it in the `sessions` table, and return it as `token` in the JSON response.

- **Universal login:** `POST /api/login.php` — accepts a National ID (students, 6–8 digits) or matches an admin by National ID; returns `token`, `role`, `role_id`, `redirect`.
- **Admin login:** `POST /api/admin-login.php` — by `pf_number`.
- **Student login:** `POST /api/student-login.php` — by `national_id`.

**Frontend (`app.js`)** stores the token in `sessionStorage` and sends it on every protected call:
```js
fetch(`${API_BASE}/get-submissions.php`, {
    headers: { 'Authorization': `Bearer ${sessionStorage.getItem('adminToken')}` }
});
```

**Backend** reads it via `get_bearer_token()` (in `config.php`). `SessionManager::validateSession()` checks the Bearer token first, then falls back to cookie / PHP session for server-rendered pages.

Session lifetime: **8 hours** (`SESSION_TIMEOUT = 28800`).

---

## Backend Enforcement API (`session-manager.php`)

Include on any protected endpoint:
```php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session-manager.php';
```

Then guard the route with one helper. Each returns the verified `$session` array or exits with a JSON 401/403:

```php
$session = requireAuth();                 // any authenticated user
$session = requireAuth('student');        // exact role
$session = requireAuth(['super_admin','hr_coordinator']); // any of
$session = requireSuperAdmin();           // role_id 1
$session = requireHR();                    // role_id 2
$session = requireSupervisor();            // role_id 3
$session = requireStudent();               // role_id 4
$session = requireAnyAdmin();              // super_admin OR hr OR supervisor
```

`$session` keys: `user_type` ('admin'|'student'), `role_name`, `role_level`, `role_id`,
`admin_id`, `user_id`, `pf_number`, `full_name`, `email`, `department`, `department_id`, `department_name`.

**Department isolation** — never trust a department from the request:
```php
$scope = $GLOBALS['sessionManager']->getScopedDepartment($session);
// 'ALL' for super_admin / hr_coordinator; the supervisor's own department otherwise
if ($scope !== 'ALL') {
    $query .= " AND s.department_applied = ?";
    $params[] = $scope;
}
```

Key guarantees:
- **Role is read from the database** each request (join through `sessions.role_id → roles`), never from the client.
- **Account status** is re-checked on every request; a deactivated admin/student fails validation immediately.
- **Session token** is the primary guard (256-bit random); IP/User-Agent changes are logged, not hard-rejected (so proxies/mobile networks don't cause false logouts).

---

## Database Schema (RBAC-relevant tables)

Source of truth: `database.sql`. Migration for existing data: `database-migration.sql`.

- **roles** — `id, role_name, level, description` (seeded with the 4 roles above).
- **admin_users** — `id, national_id, pf_number, full_name, email, password_hash, role_id→roles, department_id→departments, department (free-text scope), status, last_login, created_by`.
- **users** — students; `national_id, email, password_hash, verified, verification_code, status, last_login`.
- **sessions** — `session_id (token), user_id, admin_id, user_type, role_id, ip_address, user_agent, expires_at, is_valid`.
- **departments** — `name, description, min_admins, is_active`.
- **audit_log** — `admin_id, action, details, ip_address, created_at`.
- **admin_creation_history** — trail of who created which admin.

---

## Admin Account Lifecycle

- **No public admin registration.** `api/admin-register.php` is disabled (returns 403). Admins are created only by a Super Admin.
- **Create** (`api/create-admin-account.php`, Super Admin only): validates unique `pf_number`/`email`, enforces **max 2 admins per department**, assigns `role_id` 2 (HR) or 3 (Supervisor), status `active`, logs to `admin_creation_history`.
- **Activate / Deactivate** (`api/activate-admin-account.php`, `api/deactivate-admin-account.php`, Super Admin only): toggles `status`; deactivation invalidates the admin's sessions. Super Admin cannot deactivate their own account.
- **Toggle status** (`api/toggle-admin-status.php`) and **update credentials** (`api/update-admin-credentials.php`).

---

## API Endpoint Map

**Auth/session:** `login.php`, `admin-login.php`, `student-login.php`, `logout.php`, `verify-session.php`, `register.php`, `verify-email.php`
**Admin mgmt (super_admin):** `create-admin-account.php`, `activate-admin-account.php`, `deactivate-admin-account.php`, `get-admin-accounts.php`, `get-admins.php`, `toggle-admin-status.php`, `update-admin-credentials.php`
**Reference/stats:** `get-departments.php`, `get-audit-log.php`, `get-dashboard-stats.php`, `get-user-info.php`, `get-users.php`
**Applications:** `submit-application.php`, `get-submissions.php`, `get-user-submissions.php`, `update-submission.php`, `delete-submission.php`, `download-document.php`
**Vacancies:** `vacancies.php` (GET list; POST `action=create` for supervisors, `action=update_status` for HR/super_admin)

---

## Implementation Checklist

### Database
- [ ] Fresh install: import `database.sql` (creates `vuka_attachment_portal`, seeds roles + Super Admin).
- [ ] Existing data: run `database-migration.sql` (idempotent, data-preserving).
- [ ] Confirm the 4 roles and the Super Admin account exist.

### Backend
- [x] `config.php` — DB = `vuka_attachment_portal`, `get_bearer_token()`, `json_response()`.
- [x] `session-manager.php` — Bearer-first `validateSession()`, 4-role helpers.
- [x] `audit-logger.php` — action logging.
- [ ] All `api/*.php` route through `requireAuth()`/role helpers and match the schema.

### Frontend
- [x] `index.php` — login/registration SPA.
- [x] `pages/{admin,hr,supervisor,student}_dashboard.php` — role dashboards; each sends the Bearer token.

### Testing
- [ ] Run `test-rbac.php` via WampServer (see TESTING_GUIDE.md).
- [ ] Verify a supervisor cannot see other departments' submissions.
- [ ] Verify a deactivated admin is logged out on next request.

---

## Default Super Admin

**For initial setup only — change immediately.**
```
PF Number:   SUPER/ADMIN/001
National ID: 12345678
Password:    admin123
```

---

## Deployment Notes

- **HTTPS:** serve over TLS in production; set the session cookie `Secure` flag (Bearer tokens should only travel over HTTPS).
- **Secrets:** keep `config.php` out of the web root or restrict it (`chmod 600`); never commit real DB credentials.
- **Docs:** these Markdown files should not be publicly served in production — move them outside the web root.
- **Session cleanup:** run `SessionManager::cleanupExpiredSessions()` on a daily cron.

---

## Troubleshooting

- **All protected calls 401** → token not sent as `Authorization: Bearer …`, or `sessions` row expired/`is_valid=0`.
- **Supervisor sees no data** → their `admin_users.department` doesn't match `submissions.department_applied`, or `department_id` is NULL.
- **Login "role" wrong** → check `admin_users.role_id` and the `roles` seed (student must be role_id 4).
- **DB connection failed** → `config.php` `DB_NAME` must be `vuka_attachment_portal` and the DB must exist.
