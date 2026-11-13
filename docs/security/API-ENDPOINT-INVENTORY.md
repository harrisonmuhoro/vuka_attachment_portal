# API Endpoint Security Inventory & Authorization Matrix

**Baseline:** `99a3da7e0a309be37d264aba74b81b342d17f93e` · Updated Phase 2.2 · 46 PHP endpoints inspected and verified.

## Comprehensive Security & Authorization Matrix

| Endpoint | Methods | Role & Auth | Permission (RBAC) | Scope / Ownership | CSRF Protection | Audit Logging | Sensitive Data Filtering | Status |
|---|---|---|---|---|---|---|---|---|
| `activate-admin-account.php` | POST | Super Admin | `admin.accounts.activate` | Target admin ID; self-change blocked | Required for cookie auth | Yes (`AuditLogger`) | Passwords / hashes excluded | Verified |
| `admin-login.php` | POST | Public | None (pre-auth) | Credentials verification only | Rate-limited IP guard | Login failure audited | Password hashes never returned | Verified |
| `admin-register.php` | POST | Public | None (pre-auth) | New admin applicant | Form validation | Account creation audited | Password hashes never exposed | Verified |
| `bulk-update-submissions.php` | POST | Admin tier | `applications.update_status` | SQL department scope; all IDs in batch verified | Required for cookie auth | Yes (`review_history`) | Strict status & note payload | Remediated |
| `change-password.php` | POST | Authenticated | `profile.update_own` | Session-derived account (user/admin) | Required for cookie auth | Password change audited | No credential disclosure | Verified |
| `confirm-email-change.php` | POST | Authenticated | `profile.update_own` | Session-derived account + hashed token | Required for cookie auth | Email update audited | Token consumed immediately | Verified |
| `create-admin-account.php` | POST | Super Admin | `admin.accounts.manage` | Server-derived creator identity | Required for cookie auth | Yes (`AuditLogger`) | Plaintext/hash never returned | Verified |
| `deactivate-admin-account.php` | POST | Super Admin | `admin.accounts.deactivate` | Target admin ID; self-deactivation blocked | Required for cookie auth | Yes (`AuditLogger`) | Passwords / hashes excluded | Verified |
| `delete-submission.php` | POST | HR / Super Admin | `applications.delete` | Scoped lookup; supervisor strictly denied (403) | Required for cookie auth | Yes (`AuditLogger`, atomic) | No physical paths disclosed | Verified (Phase 2.2 atomic transaction) |
| `download-document.php` | GET | Student / Admin | `applications.view` | Owner (`user_id`) or supervisor department scope | N/A (read-only) | Access checked | Streamed via MIME check & nosniff | Verified |
| `evaluations.php` | GET/POST | Student / Admin | `evaluations.view` / `evaluations.create` | Student owns submission; supervisor owns department | Required on POST | Status update to completed | Student cannot view other students | Verified |
| `forgot-password.php` | POST | Public | None (pre-auth) | Target email lookup | Rate-limited | Token request audited | Always returns generic message | Verified |
| `generate-evaluation-pdf.php` | GET | Student / Admin | `evaluations.view` | Student owns submission; supervisor owns department | N/A (read-only) | Scoped generation | Scoped data only | Verified |
| `generate-letter.php` | GET | Admin tier | `applications.view` | Scoped submission lookup by department | N/A (read-only) | Scoped generation | PDF download streamed | Verified |
| `get-admin-accounts.php` | GET | Super Admin | `admin.accounts.manage` | System-wide admin accounts | N/A (read-only) | Access checked | Password hashes excluded | Verified |
| `get-admins.php` | GET | Super Admin | `admin.accounts.manage` | System-wide admin list | N/A (read-only) | Access checked | Passwords and secrets omitted | Verified |
| `get-analytics.php` | GET | Admin tier | `analytics.system` | Supervisor own department; HR/Super Admin all | N/A (read-only) | Access checked | Aggregated counts only | Remediated |
| `get-audit-log.php` | GET | Admin tier | `audit.view_department` / `audit.view_all` | Supervisor department-scoped; HR/Super Admin all | N/A (read-only) | Access checked | Private IP/metadata sanitized | Verified |
| `get-dashboard-stats.php` | GET | Authenticated | Role-specific | Role-specific aggregation (student/supervisor/HR) | N/A (read-only) | Access checked | Role-bounded counts only | Verified |
| `get-departments.php` | GET | Admin tier | `departments.view` | Supervisor own department; HR/Super Admin all | N/A (read-only) | Access checked | Department name & metadata only | Verified |
| `get-profile.php` | GET | Authenticated | `profile.view_own` | Session-derived account (`user_id` or `admin_id`) | N/A (read-only) | Access checked | Secrets & hashes excluded | Verified |
| `get-student-interviews.php` | GET | Student | `applications.view` | Session-derived applicant ownership (`user_id`) | N/A (read-only) | Access checked | Own interview slots only | Verified |
| `get-submissions.php` | GET | Student / Admin | `applications.view` | Student own submissions; supervisor own department | N/A (read-only) | Access checked | Admin fields hidden from students | Verified |
| `get-user-info.php` | GET | Authenticated | `profile.view_own` | Session-derived account | N/A (read-only) | Access checked | Safe user attributes only | Verified |
| `get-user-submissions.php` | GET | Student | `applications.view` | Session-derived applicant ownership (`user_id`) | N/A (read-only) | Access checked | Strict column allowlist (no admin fields) | Remediated (Finding B) |
| `get-users.php` | GET | Super Admin | `admin.accounts.manage` | System-wide student accounts | N/A (read-only) | Access checked | Password hashes excluded | Verified |
| `interview-response.php` | POST | Student | `applications.update_status` | Session-derived applicant ownership on parent submission | Required for cookie auth | Response recorded | Only accepts `confirmed` / `conflict` | Verified |
| `interview-slots.php` | GET/POST | Admin tier | `interviews.manage` | Supervisor department-scoped | Required on POST | Slot mutations logged | Department isolation enforced | Remediated |
| `login.php` | POST | Public | None (pre-auth) | Universal login dispatcher | Rate-limited IP guard | Login attempts tracked | Credentials never exposed | Verified |
| `logout.php` | POST | Authenticated | Any active session | Current session invalidation | Validated session | Session destruction logged | Session token revoked | Verified |
| `notifications.php` | GET/POST | Authenticated | Role-specific | Recipient-bounded by session ID | Required on POST | Notification state updated | Own notifications only | Verified |
| `register.php` | POST | Public | None (pre-auth) | Applicant registration validation | Rate-limited | Registration audited | Plaintext password discarded; hash stored | Verified |
| `request-email-change.php` | POST | Authenticated | `profile.update_own` | Session-derived account | Required for cookie auth | Verification token generated | Token sent via email only | Verified |
| `reset-password.php` | POST | Public token | None (token-bound) | Validated single-use token binding | Single-use token guard | Password reset audited | Token consumed immediately | Verified |
| `serve-profile-photo.php` | GET | Authenticated | `profile.view_own` | Session-derived account photo | N/A (read-only) | Access checked | Sanitized image stream only | Verified |
| `student-login.php` | POST | Public | None (pre-auth) | Applicant credentials and verification | Rate-limited IP guard | Login audited | Upgrades legacy password hashes | Verified |
| `submit-application.php` | POST | Student | `applications.view` | Session-derived applicant ownership (`user_id`) | Required for cookie auth | Application creation audited | Validates national ID and vacancy | Verified |
| `toggle-admin-status.php` | POST | Super Admin | `admin.accounts.manage` | Lifecycle transitions; self-toggle prevented | Required for cookie auth | Yes (`AuditLogger`, atomic) | In-flight sessions revoked | Verified |
| `update-admin-profile.php` | POST | Admin tier | `profile.update_own` | Session-derived admin identity (`admin_id`) | Required for cookie auth | Yes (`AuditLogger`) | Privileged columns strictly blocked | Remediated |
| `update-student-profile.php` | POST | Student | `profile.update_own` | Session-derived applicant identity (`user_id`) | Required for cookie auth | Yes (`AuditLogger`) | Only full_name and phone editable | Remediated |
| `update-submission.php` | POST | Admin tier | `applications.update_status` | SQL department scope; supervisor isolated | Required for cookie auth | Yes (`review_history`) | Strict workflow transition guard | Remediated |
| `upload-profile-photo.php` | POST | Authenticated | `profile.update_own` | Session-derived account (`user_id` or `admin_id`) | Required for cookie auth | Photo upload audited | MIME validated, stored safely | Verified |
| `vacancies.php` | GET/POST | Public reads / Admin writes | `vacancies.manage` (writes) | Public: approved/open only; Admin: department scope | Required on POST | Approval / edit audited | Internal fields omitted for public | Verified |
| `verify-email.php` | POST | Public token | None (token-bound) | Single-use verification code | Single-use code guard | Verification audited | Code hashed and consumed | Verified |
| `verify-session.php` | GET | Authenticated | Any active session | Current active session verification | N/A (read-only) | Access checked | Returns safe identity without tokens | Verified |
| `withdraw-application.php` | POST | Student | `applications.update_status` | Session-derived applicant ownership (`user_id`) | Required for cookie auth | Yes (`review_history`) | Workflow-validated withdrawal | Verified |

---

## Architectural Guarantees

1. **Centralized RBAC Enforcement (`lib/authorization.php`):**
   - Permission checks (`Authorization::requirePermission()`) are enforced before business logic runs.
   - Applicant ownership (`Authorization::requireApplicantOwnership()`) guarantees students cannot view or mutate another student's applications, profile, or documents.
   - Department scope (`Authorization::requireDepartmentScope()`) enforces horizontal multi-tenant isolation for department supervisors.
2. **CSRF Enforcement:**
   - Centralized CSRF verification is required for all state-changing cookie-authenticated requests (`require_csrf()`).
   - Bearer-token requests validate active database sessions with cryptographic token comparison.
3. **Sensitive Data Projection:**
   - Endpoints returning applicant data (such as `api/get-submissions.php` and `api/get-user-submissions.php`) use strict column allowlists that exclude internal administrative fields (`review_notes`, `intern_pf_number`, `assigned_role`, `assigned_station`, `last_updated_by`).
4. **Audit Trail Integrity:**
   - All state-changing administrative operations and sensitive applicant status transitions are immutably recorded via `AuditLogger` or transactional `review_history`.
