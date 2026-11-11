# Phase 1.1 re-audit and implementation plan

## Baseline

- Starting repository commit: `c76c08c843f19a948c681469d3dd344539ff5de7` (`origin/main`, Phase 1 merge commit).
- Initial local branch: `feature`, clean working tree.
- Phase 1 commit included in `origin/main`: `0578c48 refactor: implement Phase 1 security hardening and authentication overhaul`.
- Working branch: `feature-phase-1.1-security-remediation`. The requested `feature/phase-1.1-security-remediation` cannot coexist with the existing local `feature` branch name because Git refs share the same namespace; this branch uses an equivalent non-conflicting name and is based directly on the freshly fetched `origin/main`.
- No database initialization script was run. Existing Phase 1 tests and docs were present at baseline.

## Findings verified against source

| Workstream | Finding | Source evidence | Decision |
| --- | --- | --- | --- |
| Review workflow | Bulk endpoint accepts staff-review statuses for any admin role, skips inaccessible IDs and commits the rest, does not lock/revalidate transitions, and writes caller-independent but session-derived reviewer data inconsistently. | `api/bulk-update-submissions.php` updates `accepted`, `rejected`, `pending`, `deployed`, and `ongoing`; `api/update-submission.php` limits supervisor transitions to placement lifecycle actions. | Centralize state transition and scope checks, with bulk all-or-nothing semantics. Existing README/dashboard indicate HR handles decisions; supervisors deploy and advance placements. |
| Audit log | Supervisor department IDs are queried but unused; records and statistics are unrestricted; `total_records` is only the current page count. | `api/get-audit-log.php`, `audit-logger.php`. | User clarified policy: Super Admin and HR system-wide, Department Supervisor own department only. Enforce at SQL query level and apply scope to count/statistics. |
| Vacancy access | Anonymous callers can select `all` or internal states; response selects internal columns; GET closes records by UPDATE; status endpoint accepts arbitrary statuses; supervisor ownership not applied to status action. | `api/vacancies.php`. | Public reads are approved, open, non-expired, whitelisted fields; GET stays read-only. Admin reads are role-scoped. Validate role, current state, and transition for writes. |
| Password policy | Applicant register, password change, and reset allow 8 characters; administrator creation allows 6; provisioner already requires 12. | `api/register.php`, `api/create-admin-account.php`, `api/change-password.php`, `api/reset-password.php`, `scripts/provision-admin.php`. | Add one policy service for setting passwords, minimum 12 characters, explicit maximum, no composition rule; leave existing credentials untouched. |
| Profile | Admin query selects `roles.name`, but schema defines `roles.role_name`; applicant query is already user-scoped and whitelisted. | `api/get-profile.php`, `config/database.sql`. | Fix administrator role column and use consistent JSON/status handling; test both account types and sensitive-field exclusion. |
| CI | No `.github/workflows` pipeline exists. | Repository file inventory. | Add PHP 8.3 / Node syntax / SQLite HTTP security tests and disposable MySQL migration test workflow. |

## Authorization and workflow source

The repository README assigns cross-department application decisions and vacancy approval to HR, and deployment/placement work to supervisors. The current individual endpoint also enforces supervisor-only transitions `accepted → deployed → ongoing → completed`; the supervisor UI exposes deployment, start, and completion actions. HR UI exposes accept/reject and later lifecycle actions. Therefore the shared service will preserve the existing global lifecycle (`applied → pending/accepted/rejected`, `pending → accepted/rejected`, `accepted → deployed`, `deployed → ongoing`, `ongoing → completed`) and restrict supervisors to the placement transitions. HR and Super Admin remain subject to the same transition graph. Applicants retain the separate existing withdrawal endpoint.

## Sub-phase plan

1. **Authorization/workflow:** add a shared workflow service; use it in individual and bulk review routes; lock and validate the complete batch before writing; reject the whole operation on any invalid/inaccessible item; write review history consistently.
2. **Audit/vacancy:** enforce query-level actor/target department scope for supervisors and system scope for HR/Super Admin; validate filters/pagination and scope aggregates; make public vacancy reads read-only and public-only; validate role, ownership and state transitions for vacancy writes.
3. **Password/profile:** centralize password validation/hash policy for every setting endpoint; preserve existing passwords; correct the administrator profile query and return only whitelisted fields.
4. **Tests/CI:** extend the existing isolated SQLite HTTP suite for all new checks, retain and rerun Phase 1 tests, verify migration suite using disposable MySQL, add a required GitHub Actions workflow.
5. **Review/docs:** run syntax and diff checks, review all affected authorization SQL and error paths, document decisions, results, known limitations, and deployment steps. Do not merge or start Phase 2.

## Known policy choice

Audit-log access was ambiguous in repository prose. The user explicitly selected system-wide access for Super Admin and HR, and department-only access for supervisors. This phase implements that choice.
