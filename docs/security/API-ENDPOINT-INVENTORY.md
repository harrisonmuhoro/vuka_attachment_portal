# API endpoint security inventory

Baseline: `99a3da7e0a309be37d264aba74b81b342d17f93e` · 46 PHP endpoints inspected on Phase 2.1 branch.

| Endpoint | Methods | Authentication / roles | Scope / ownership | Mutation | Status |
|---|---|---|---|---|---|
| activate-admin-account.php | POST | Super Admin | Target admin, no self-change | Yes, audit | Verified |
| admin-login.php | POST | Public | Credentials only | Session | Verified |
| admin-register.php | POST | Public | Registration validation | Yes | Verified |
| bulk-update-submissions.php | POST | Admin tier; update permission | SQL department scope; all IDs accessible | Yes, history | Remediated |
| change-password.php | POST | Authenticated | Session-derived account | Yes | Verified |
| confirm-email-change.php | POST | Authenticated | Session-derived account/token | Yes | Verified |
| create-admin-account.php | POST | Super Admin | Server-derived creator | Yes, audit | Verified |
| deactivate-admin-account.php | POST | Super Admin | Target admin, no self-change | Yes, audit | Verified |
| delete-submission.php | POST | HR / Super Admin | Scoped lookup; supervisor denied | Yes, audit | Verified (Phase 2.2 atomic transaction & 404 guard) |
| download-document.php | GET | Student/admin | Owner or department scope | No | Verified |
| evaluations.php | GET/POST | Student/admin | Owner or department scope | Yes for admin | Verified |
| forgot-password.php | POST | Public | Email/token flow | Token | Verified |
| generate-evaluation-pdf.php | GET | Student/admin | Scoped submission lookup | No | Verified |
| generate-letter.php | GET | Admin | Scoped submission lookup | No | Verified |
| get-admin-accounts.php | GET | Super Admin | System-wide | No | Verified |
| get-admins.php | GET | Super Admin | System-wide | No | Verified |
| get-analytics.php | GET | Admin tier | Supervisor department; HR/Super Admin all | No | Remediated |
| get-audit-log.php | GET | Admin tier | Role department scope | No | Verified |
| get-dashboard-stats.php | GET | Authenticated | Role-specific aggregation | No | Verified |
| get-departments.php | GET | Admin | Supervisor own department; others all | No | Verified |
| get-profile.php | GET | Authenticated | Session-derived account | No | Verified |
| get-student-interviews.php | GET | Student | Submission owner | No | Verified |
| get-submissions.php | GET | Student/admin | Owner; supervisor department | No | Verified |
| get-user-info.php | GET | Authenticated | Session-derived account | No | Verified |
| get-user-submissions.php | GET | Student | Session-derived user | No | Verified |
| get-users.php | GET | Super Admin | System-wide student accounts | No | Verified |
| interview-response.php | POST | Student | Parent submission owner | Yes | Verified |
| interview-slots.php | GET/POST | Admin; interview permission | Supervisor department | Yes | Remediated |
| login.php | POST | Public | Credentials only | Session | Verified |
| logout.php | POST | Authenticated | Current session | Yes | Verified |
| notifications.php | GET/POST | Authenticated | Recipient/session-derived | Yes | Verified |
| register.php | POST | Public | Applicant registration | Yes | Verified |
| request-email-change.php | POST | Authenticated | Session-derived account | Yes | Verified |
| reset-password.php | POST | Public token | Token/account binding | Yes | Verified |
| serve-profile-photo.php | GET | Authenticated | Session-derived account | No | Verified |
| student-login.php | POST | Public | Credentials and verification | Session | Verified |
| submit-application.php | POST | Student | Session applicant; vacancy validated | Yes | Verified |
| toggle-admin-status.php | POST | Super Admin | Lifecycle rules | Yes, audit | Verified |
| update-admin-profile.php | POST | Admin | Own profile only | Yes | Remediated |
| update-student-profile.php | POST | Student | Own profile only | Yes, audit | Verified |
| update-submission.php | POST | Admin; update permission | SQL department scope | Yes, history | Remediated |
| upload-profile-photo.php | POST | Authenticated | Session-derived account | Yes | Verified |
| vacancies.php | GET/POST | Public reads; admin writes | Public approved/open; admin scope | Yes for admin | Verified |
| verify-email.php | POST | Public token | Token-bound applicant | Yes | Verified |
| verify-session.php | GET | Authenticated | Current session | No | Verified |
| withdraw-application.php | POST | Student | Own application only | Yes, history | Verified |

CSRF is enforced centrally for cookie-authenticated state-changing requests. Bearer requests still require a valid server-side session and endpoint authorization. No browser-supplied role, department or applicant ID is treated as authoritative.
