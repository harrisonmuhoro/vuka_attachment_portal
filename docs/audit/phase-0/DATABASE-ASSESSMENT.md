# Database Assessment

## Source and live instance

`config/database.sql` is tracked and creates/selects `vuka_attachment_portal`; it contains 17 tables, seed records, and `sp_get_submission_stats`. A read-only query against the configured development DB returned the same 17 table names. Selected live column checks matched the script and confirmed that several fields used by API endpoints are absent. The separate ignored root `database.sql` was not used as evidence. The schema script is destructive: it disables FK checks and drops/recreates tables, so it must not be used as an upgrade against data that must be retained.

All tables use InnoDB and utf8mb4. PKs are auto-increment integer IDs. Details below are from the tracked DDL.

## Tables and relationships

| Table | Purpose and principal columns | Constraints / indexes |
|---|---|---|
| `roles` | Role name, display name, level, description, created time | PK `id`; unique `role_name` |
| `departments` | Name, description, minimum/maximum admin counts, timestamps | PK; unique `name`; no constraint enforcing admin caps |
| `users` | Student identity, email, bcrypt hash, verification state, account status, profile metadata/BLOB, pending email, login/timestamps | PK; unique national ID and email; secondary indexes repeat these unique lookup indexes plus status |
| `admin_users` | Staff identity, PF, email/hash, role and department IDs plus free-text department, status, photo BLOB, creator, pending email, last login | PK; unique PF/email; FK department (`SET NULL`), role, self creator (`SET NULL`); indexes PF/email/department/status |
| `admin_creation_history` | Created admin, creator, role/department, activation status and creation time | PK; FKs to both admins (cascade), role, department (`SET NULL`); no activation/deactivation actor/time fields expected by endpoints |
| `sessions` | Raw `session_id`, user/admin IDs, type, role ID, IP/UA, create/activity/expiry, valid flag | PK; unique session token; FKs to user/admin (cascade), role; indexes token, expiry/valid, account/type. No CHECK guarantees exactly one owner or role/account consistency |
| `vacancies` | Department name, title, description, skills, count/type, deadline, status, creator, timestamps | PK; creator FK (`SET NULL`); indexes department and status; department name has no FK |
| `submissions` | User and duplicated identity snapshot; vacancy, type/duration/insurance/course/institution/department; status/placement status; rejection/review/placement fields; timestamps | PK; user FK cascade, vacancy FK set null; indexes user, department, status, vacancy; no uniqueness preventing duplicate applications per user/vacancy |
| `documents` | Submission/type/original name/path/MIME/size/content BLOB/upload time | PK; submission FK cascade; indexes submission/type; no content digest or per-type uniqueness |
| `review_history` | Submission, reviewer PF/department, status, notes, email flags and reviewed time | PK; submission FK cascade; indexes submission/PF; cascade deletion means audit history is not immutable |
| `evaluations` | One evaluation per submission, evaluator ID, five scores, comment, recommendation, submission time | PK; unique submission; submission/evaluator FKs cascade; evaluator index; score range is not constrained in SQL |
| `interview_slots` | Submission, schedule/location/notes/status, creator, timestamps | PK; submission/creator FKs cascade; indexes submission/status |
| `interview_responses` | Slot, confirmed/conflict response, notes/time | PK; slot FK cascade; slot index; no unique response per slot/student |
| `notifications` | Recipient ID/type, title/body/link, read flag/time | PK; compound recipient/type/read index; polymorphic recipient has no FK/deduplication constraint |
| `login_attempts` | IP, endpoint, identifier, success and attempt time | PK; compound rate-limit index |
| `password_reset_tokens` | Email, token, account type, expiry, used and create time | PK; unique token; token/used/expiry and email indexes; token stored in plaintext |
| `audit_logs` | Admin snapshot, action/entity/target, JSON old/new values, IP/UA/details/time | PK; indexes admin/action/time; no FK to admin, deliberately permitting history after deletion but no SQL immutability controls |

## Integrity and modeling observations

- Strong FK coverage exists for core ownership chains (`users -> submissions -> documents/review_history`, `submissions -> vacancy`, and evaluations/interviews). Delete cascades can erase documents/review history/evaluations as a side effect; the API also exposes permanent deletion.
- `users` uses unique constraints and duplicates the same lookup keys as ordinary indexes. `admin_users.national_id` is not unique while login accepts it.
- Department identity is stored as both `admin_users.department` and `department_id`, and as unreferenced text in vacancies/submissions/review history. Scope checks compare strings, so renamed/mismatched departments can cause incorrect visibility.
- Submission identity data is duplicated from `users`. This may be a historical snapshot, but no explicit snapshot policy is documented and it can become stale.
- `notifications.recipient_id` is polymorphic and cannot be protected by a normal FK; orphan rows are possible.
- Session role and owner consistency relies on application code. Database constraints permit empty or conflicting owner IDs.
- Indexes cover common foreign key/filter fields, but there is no index on several sort/filter patterns (`submitted_at`, `created_at` composites, status+department) and no workload evidence. No migration history is tracked.
- `sp_get_submission_stats` is present but current API paths should be checked for actual use; the schema import uses a `DELIMITER` block and MySQL-oriented syntax.

## API/schema drift confirmed

The local schema has `password_hash`, while `api/change-password.php` reads/writes `password`. `activate-admin-account.php` expects `admin_users.activated_at`, `activated_by`, and `admin_creation_history.activated_at`/`activation_by_admin_id`; those columns are absent. `deactivate-admin-account.php` expects `admin_users.updated_by` and history `deactivation_reason`/`deactivated_at`; those are absent. These endpoints will fail against the audited schema unless a different, undocumented local schema exists.

## Transactions and concurrency

Transactions are present in application submission, interview scheduling/status, and evaluation submission. Admin creation/history insertion, submission status plus review-history insertion, password change/reset, notification delivery, and several admin activation/deactivation sequences are not transactionally grouped. The app-level “max two admins” and “one submission per user” checks are check-then-insert patterns without matching unique constraints, allowing races.

## Data retention and BLOBs

Documents (up to 2 MiB each in the application path) and profile photos (up to 2 MiB) are stored as `LONGBLOB`. This avoids a public filesystem path but makes database size, backup time, replication, restore, and response memory grow with uploads. There is no retention/archive or backup/restore policy in the repo. At current unmeasured scale, keep the design until institutional volume and restore requirements are known; measure DB growth and restore time before deciding whether private object storage is justified.

## Safe next steps

1. Preserve the current DDL and live database as evidence; do not rerun it in place.
2. Reconcile all SQL statements with the live schema, especially the mismatches listed above.
3. Establish versioned, forward-only migrations and a tested backup/restore path.
4. Add uniqueness/check constraints only after reviewing existing data and business rules.
5. Define retention, purge, and audit-history policy with the institution’s data owner.
