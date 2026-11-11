# Phase 2 API endpoint inventory

| Endpoint | Methods | Authentication / roles | Scope and ownership | Mutation / audit |
|---|---|---|---|---|
| login.php, student-login.php, admin-login.php | POST | Public authentication | Credentials only; no caller identity trusted | Session creation; auth rate/audit behavior |
| register.php, verify-email.php, forgot-password.php, reset-password.php | POST | Public token flows | Applicant email/token ownership | Credential/token mutation |
| get-submissions.php, get-user-submissions.php | GET | Student or admin tier | Student own user ID; supervisors own department; HR/Super Admin all | Read sensitive applicant data |
| submit-application.php, withdraw-application.php | POST | Student | Authenticated applicant and own account | Submission workflow/history |
| update-submission.php, bulk-update-submissions.php, evaluations.php | GET/POST | Admin tier; evaluation GET also owner student | Workflow service; supervisor department scope; student own evaluation | Transactional workflow/history |
| download-document.php, serve-profile-photo.php | GET | Owner student or scoped admin | Parent submission/user checked in SQL | Sensitive binary read |
| interview-slots.php, generate-letter.php | GET/POST | Admin tier | Supervisor department scope; HR/Super Admin all | Interview/letter mutations/read |
| get-student-interviews.php, interview-response.php | GET/POST | Student | Submission ownership in SQL | Student response mutation |
| generate-evaluation-pdf.php | GET | Owner student or scoped admin | Submission department/owner checked in lookup | Sensitive binary read |
| get-analytics.php, get-dashboard-stats.php | GET | Authenticated role | System, department, or own records according to role | Aggregate read; same scope for counts |
| get-audit-log.php | GET | Admin tier | HR/Super Admin all; supervisor own department | Read audit data; sanitized output |
| create-admin-account.php, activate-admin-account.php, deactivate-admin-account.php, toggle-admin-status.php | POST | Super Admin | Target admin lifecycle rules; no self-deactivation | Transaction + audit + session invalidation |
| get-admins.php, get-admin-accounts.php, get-users.php | GET | Super Admin | System-wide administrator/student management | Sensitive read |
| update-admin-profile.php, update-student-profile.php, upload-profile-photo.php | POST | Authenticated owner | Session-derived identity only | Profile mutation + audit where applicable |
| get-profile.php, get-user-info.php, verify-session.php | GET | Authenticated | Session-derived identity | Sensitive read |
| get-departments.php | GET | Admin tier | Supervisor receives own department; system roles receive all | Read |
| get-vacancies / vacancies.php | GET/POST | Public reads; admin writes | Public approved/open; admin role/dept scope | Vacancy mutation + audit |
| delete-submission.php | POST | HR/Super Admin | System operational scope; supervisors denied | Destructive mutation + audit |
| notifications.php, logout.php, confirm-email-change.php, request-email-change.php, change-password.php | GET/POST | Authenticated | Recipient/session-derived | Account mutation as applicable |

CSRF is enforced by the shared session guard for cookie-authenticated mutations. Bearer-token requests remain subject to session validation and endpoint authorization.
