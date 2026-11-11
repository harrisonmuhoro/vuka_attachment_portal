# Phase 2 baseline authorization audit

**Baseline:** `a7b1d98a6c8061ae863aaf0c173abf0201d39b40` (`origin/main` at audit start)  
**Working branch:** `feature/phase-2-rbac-data-isolation`

## Controls already present

- Database-backed bearer sessions reload role and account status on every request.
- CSRF is required for cookie-authenticated mutations.
- Phase 1.1 centralizes application transitions and bulk operations.
- Submission, document, interview, evaluation, vacancy, and audit reads already contain substantial ownership or department checks.
- Password, reset-token, profile, and public-vacancy protections are covered by the existing regression suite.

## Gaps found and addressed in Phase 2

- Authorization decisions were still scattered as role-name comparisons; a small shared permission registry is now available in `lib/authorization.php`.
- `get-departments.php` returned every department to supervisors; its query is now department-scoped for supervisors.
- `update-admin-profile.php` called `requireAnyAdmin()` without retaining the validated session, causing the update to use an undefined identity.
- Generated evaluation PDFs and letters fetched unrestricted submissions before checking department scope; both now constrain the lookup itself.
- Submission deletion was available to supervisors even though their approved role is placement management, not destructive application deletion.
- Administrator status mutation had a duplicate toggle path with no audit transaction and arbitrary status values; the toggle path now delegates to the same lifecycle rules as the dedicated endpoints.
- The audit logger had a malformed duplicate `FROM` clause in its activation helper and lifecycle callers did not fail closed when audit insertion failed.

## Review scope

All files under `api/`, `session-manager.php`, `audit-logger.php`, `lib/`, `config/database.sql`, migrations, dashboards, JavaScript, Phase 1/1.1 tests, and CI workflow were inspected. Endpoint details are recorded in `API-ENDPOINT-INVENTORY.md`; approved role decisions are in `ROLE-PERMISSION-MATRIX.md`.
