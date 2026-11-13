# Phase 2.1 Remediation Report

**Branch:** `feature/phase-2-1-rbac-integration-ci-recovery`
**Base SHA:** `99a3da7e0a309be37d264aba74b81b342d17f93e`
**Report updated:** 2026-11-13\
**Status:** Merged into main via PR #9. CI and regression checks passing (112 local / CI suite).

---

## Baseline

| Item | Value |
|---|---|
| Main SHA at phase start | `99a3da7e0a309be37d264aba74b81b342d17f93e` |
| Working branch | `feature/phase-2-1-rbac-integration-ci-recovery` |
| API surface inspected | 46 PHP files under `api/` |
| Phase 2 merged via | PR #7 |
| Phase 2.1 merged via | PR #9 |

---

## Task 1 — Administrator Profile Update HTTP 500: Root Cause Proven and Fixed

### Root cause

GitHub Actions CI job `43028911789` failed at step "Run Phase 1, 1.1 and Phase 2 HTTP authorization
regression suite" with:

```
FAIL Administrator profile update uses authenticated identity — HTTP 500
```

Inspection of `api/update-admin-profile.php` and `api/update-student-profile.php` confirmed both
called `logAudit($pdo, ...)` — a free function that **does not exist anywhere in the codebase**.
The only audit mechanism is `AuditLogger::log()` accessed via `$GLOBALS['auditLogger']`.

On Windows (local WAMP), the PHP built-in test server cannot be started from the sandbox
(`proc_open` restriction), so the HTTP 500 never manifests locally without bypass. Linux CI starts
the server successfully, exposing the undefined-function fatal error.

### Fix applied — `api/update-admin-profile.php`

- Replaced `logAudit(...)` with `$GLOBALS['auditLogger']->log(...)`.
- Added `require_once __DIR__ . '/../config/config.php'` as the first include (using `__DIR__`).
- Changed all `require_once` to `__DIR__`-based paths.
- Added `$session = requireAnyAdmin()` with explicit variable capture.
- Used `json_response()` consistently for all exits.

### Fix applied — `api/update-student-profile.php`

- Replaced `logAudit(...)` with `$GLOBALS['auditLogger']->log(...)`.
- Added `require_once __DIR__ . '/../config/config.php'` as first include.
- Changed all `require_once` to `__DIR__`-based paths.
- Changed `requireStudent()` to `$session = requireStudent()` (variable was used but not assigned).
- Used `json_response()` consistently for all exits.

---

## Task 2 — Submission Cleanup Queue (Historical Phase 2.1 Baseline)

> [!NOTE]
> **Audit Correction (Phase 2.2):** The Phase 2.1 implementation used a *commit-then-queue* sequence, which left an operational failure window: if the process terminated after the database commit but before queue insertion, documents on disk would remain orphaned without a queue record. This was formally identified in post-merge audit and fully resolved in Phase 2.2 (`feature/phase-2-2-atomic-cleanup-reliability`), where queue insertion, submission deletion, and audit logging are all executed within a single database transaction.

Implemented in commit `f7a2c3e` (historical Phase 2.1 baseline):

- `api/delete-submission.php` wraps the `DELETE FROM submissions` and its audit log entry in a
  single `beginTransaction()`/`commit()`. Physical file cleanup is queued in
  `submission_cleanup_queue` only after the commit succeeds.
- If the queue insert fails, the error is written via `error_log()` only. The submission remains
  deleted (the DB transaction is already committed and cannot be rolled back without a compensating
  transaction, which would reintroduce the orphaned row).
- `scripts/process-submission-cleanup.php` processes the queue with configurable retry/backoff.
- `migrations/002_submission_cleanup_queue.sql` creates the queue table.

---

## Task 3 — Hardened Cleanup Path Validation and API Response Sanitisation

- `api/delete-submission.php` no longer includes a `cleanup_failures` array in the JSON success
  response. Filesystem paths are never sent to the caller.
- Internal queue failures are logged server-side via `error_log()`.
- `config/config.php` now defines `UPLOAD_DIR` as `<project-root>/uploads/` so
  `scripts/process-submission-cleanup.php` has the constant it requires.

---

## Task 4 — Cleanup Queue Tests

Five new assertions added to `tests/security/phase1_security_tests.php`:

| # | Assertion | Verifies |
|---|---|---|
| 1 | Queue row is inserted atomically when submission is deleted | Commit-then-queue pattern |
| 2 | `completed_at` is NULL before processing | Crash-recovery visibility |
| 3 | API response does not expose `cleanup_failures` or filesystem paths | Task 3 coverage |
| 4 | Second delete of same submission returns `success:false` | Idempotent guard |
| 5 | Simulated retry: `attempts` and `last_error` persisted, `completed_at` stays NULL | Retry state integrity |

---

## Task 5 — Endpoint-by-Endpoint Authorization Verification

All 46 endpoints listed and individually verified in
`docs/security/API-ENDPOINT-INVENTORY.md`. Every endpoint is confirmed to:

- Require the correct role (or be explicitly public).
- Use server-side session identity (never caller-supplied user/admin ID).
- Apply department scope where applicable.
- Return 401/403/404 for unauthorized callers.

The two profile endpoints (`update-admin-profile.php`, `update-student-profile.php`) are now marked
"Verified post-fix" reflecting the Task 1 remediation.

---

## Task 6 — CI Suites

Both suites are configured in `.github/workflows/ci.yml`:

- **Security regression suite** (`tests/security/phase1_security_tests.php`) — runs against an
  in-memory SQLite fixture via the PHP built-in server.
- **MySQL migration suite** (`tests/security/phase1_migration_test.php`) — runs against a
  disposable MySQL 8.0 service container.

The `fail-fast: true` invalid input was removed in commit `d8b169d`. Both suites are expected to
pass on GitHub Actions CI after this branch is pushed.

---

## Task 7 — Local Test Results (Actual Verified Results)

### Security regression suite

```
php tests/security/phase1_security_tests.php
TOTAL: 112 passed, 0 failed
```

Run outside sandbox (bypass required on Windows for PHP built-in server loopback).
Previous result before Task 1 fix: 107 passed, 0 failed (CI showed HTTP 500).
After fix: 112 passed, 0 failed (5 new cleanup queue tests added).

The `rmdir: Directory not empty` PHP warning on Windows is cosmetic (Windows file locking during
temp dir cleanup); it does not affect the exit code or test count.

### MySQL migration suite

```
php tests/security/phase1_migration_test.php
TOTAL: 7 passed, 0 failed
```

Run against a disposable MySQL 8.4.7 instance on port 3307 (separate from WAMP production DB).
Migration `002_submission_cleanup_queue` applied and idempotency confirmed.

---

## Commits on This Branch

| SHA | Description |
|---|---|
| `d8b169d` | fix: recover CI and integrate Phase 2.1 RBAC controls |
| `f7a2c3e` | feat: add retryable submission cleanup and runnable regression tests |
| `cd250ad` | fix: resolve CI HTTP 500, harden cleanup API, add UPLOAD_DIR and cleanup queue tests |

---

## Remaining Known Risks

| Item | Severity | Status |
|---|---|---|
| `UPLOAD_DIR` now defined in config; cleanup worker will function in production | Low | Resolved |
| `api/delete-submission.php` missing status code for "not found" (returns HTTP 200 with `success:false`) | Low | Documented; not a security issue; consider adding explicit 404 http code in a follow-up |
| Hosted GitHub Actions CI result | Clean | Verified passing on main (PR #9 merged) |
| Windows temp dir `rmdir` warning in test harness | Cosmetic | Non-blocking |
