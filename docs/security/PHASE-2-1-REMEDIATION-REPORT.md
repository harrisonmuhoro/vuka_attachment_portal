# Phase 2.1 Remediation Report

**Branch:** `feature/phase-2-1-rbac-integration-ci-recovery`
**Base SHA:** `99a3da7e0a309be37d264aba74b81b342d17f93e`
**Report updated:** 2026-11-12
**Status: All Phase 2.1 and Phase 2.1.2 tasks complete. Local test results: 116 passed, 0 failed.**

---

## Baseline

| Item | Value |
|---|---|
| Main SHA at phase start | `99a3da7e0a309be37d264aba74b81b342d17f93e` |
| Working branch | `feature/phase-2-1-rbac-integration-ci-recovery` |
| API surface inspected | 46 PHP files under `api/` |
| Phase 2 merged via | PR #7 |

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

## Phase 2.1.2 — Transactional File Cleanup and Worker Hardening

Following code audit review of `api/delete-submission.php` and `scripts/process-submission-cleanup.php`, the file cleanup subsystem was hardened to ensure zero failure windows and strict filesystem boundaries.

### 1. Atomic Queue Insertion Within Database Transaction
- Previously, submission deletion committed before queue insertion began. A crash or fatal error between commit and queue insertion would leave orphaned files on disk without any queue record to clean them up.
- **Remediation:** In `api/delete-submission.php`, `submission_cleanup_queue` inserts are now executed **inside the same database transaction** as `DELETE FROM submissions` and audit logging.
- If queue record creation or audit logging fails, `$pdo->rollBack()` is executed, rolling back the submission deletion. Physical files are never deleted inside the transaction.

### 2. Canonical Path Validation and Boundary Enforcement
- Added dedicated validator `lib/cleanup-path-validator.php` (`validateCleanupPath($relative, $uploadDir)`).
- Rejects paths containing null bytes (`\0`) or directory traversal sequences (`..`).
- Resolves absolute path via `realpath()` and verifies that the canonical path strictly starts with the configured `UPLOAD_DIR`.
- Never logs or exposes internal full paths in error messages returned to clients or unhandled exceptions.

### 3. Concurrent Worker Safety
- In `scripts/process-submission-cleanup.php`, pending items are claimed using `SELECT ... FOR UPDATE SKIP LOCKED` inside a short transaction.
- When rows are claimed, their `available_at` timestamp is immediately leased forward by `CLAIM_MINUTES` (10 minutes).
- Other concurrent workers will skip these claimed rows.
- If a worker crashes mid-batch, the claim lease expires and the records automatically become eligible for retry by another worker.
- Physical unlinking (`@unlink`) is executed outside any database transaction.

---

## Distinction: Tested Guarantees vs Operational Assumptions

To ensure rigorous audit compliance, we distinguish between guarantees proven by test assertions versus operational runtime assumptions:

| Property | Classification | Proof / Mechanism |
|---|---|---|
| **Atomicity of deletion & queueing** | **Tested Guarantee** | Test `T1` proves that when the queue table is unavailable, the transaction rolls back and the submission remains intact in the database. |
| **Commit verification** | **Tested Guarantee** | Test `T2` verifies that on success, both the submission row is gone and the queue record exists. |
| **Response sanitization** | **Tested Guarantee** | Test `T3` verifies that the API response omits `cleanup_failures` and filesystem paths. |
| **Path traversal prevention** | **Tested Guarantee** | Test `T6` verifies that `validateCleanupPath()` rejects `../` and null bytes, and resolves inside `UPLOAD_DIR`. |
| **Crash recovery visibility** | **Tested Guarantee** | Test `T4` & `T7` verify that pending/failed queue rows maintain `completed_at = NULL` and retryable timestamps. |
| **Idempotent API guard** | **Tested Guarantee** | Test `T5` verifies re-deleting an already deleted submission returns `success: false`. |
| **Concurrent lock non-blocking** | **Operational Assumption** | `FOR UPDATE SKIP LOCKED` behavior relies on MySQL 8.0 InnoDB engine features; verified by syntax and engine compatibility, but simulated single-threaded in SQLite test harness. |
| **Filesystem unlink permissions** | **Operational Assumption** | Assumes the PHP process executing `scripts/process-submission-cleanup.php` has write/delete permissions on files within `UPLOAD_DIR`. |

---

## Task 4 & Phase 2.1.2 Test Suite Matrix

Seven dedicated assertions in `tests/security/phase1_security_tests.php` cover transactional cleanup and worker hardening:

| # | Test Label | Verifies |
|---|---|---|
| T1 | Transaction rolls back submission deletion when cleanup queue insert fails | Queue failure triggers complete rollback of submission delete |
| T2 | Cleanup queue row and submission deletion are committed in the same transaction | Both submission deletion and queue record commit atomically |
| T3 | Delete-submission response does not expose cleanup_failures or filesystem paths | Sensitive path information is not leaked in API output |
| T4 | Unprocessed cleanup queue row has completed_at NULL (crash recovery visibility) | New queue items remain visible to cleanup workers |
| T5 | Deleted submission cannot be deleted a second time (idempotent guard) | 404/not found returned on duplicate delete attempt |
| T6 | validateCleanupPath rejects traversal / null bytes & resolves inside upload dir | Strict boundary enforcement via `realpath()` |
| T7 | Failed cleanup queue row retains last_error; completed_at NULL; available_at past | Exponential/stepped retry and error tracking integrity |

---

## Task 5 — Endpoint-by-Endpoint Authorization Verification

All 46 endpoints listed and individually verified in
`docs/security/API-ENDPOINT-INVENTORY.md`. Every endpoint is confirmed to:

- Require the correct role (or be explicitly public).
- Use server-side session identity (never caller-supplied user/admin ID).
- Apply department scope where applicable.
- Return 401/403/404 for unauthorized callers.

The two profile endpoints (`update-admin-profile.php`, `update-student-profile.php`) and deletion endpoint (`delete-submission.php`) are marked verified post-fix.

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
TOTAL: 116 passed, 0 failed
```

Run outside sandbox (bypass required on Windows for PHP built-in server loopback).
- Baseline: 107 passed, 0 failed.
- Post Task 1 fix: 112 passed, 0 failed.
- Post Phase 2.1.2 cleanup hardening: **116 passed, 0 failed**.

The `rmdir: Directory not empty` PHP warning on Windows is cosmetic (Windows file locking during
temp dir cleanup); it does not affect the exit code or test count.

### MySQL migration suite

```
php tests/security/phase1_migration_test.php
TOTAL: 7 passed, 0 failed
```

Run against disposable MySQL 8.4.7 instance on port 3307.
Migration `002_submission_cleanup_queue` applied and idempotency confirmed.

---

## Commits on This Branch

| SHA | Description |
|---|---|
| `d8b169d` | fix: recover CI and integrate Phase 2.1 RBAC controls |
| `f7a2c3e` | feat: add retryable submission cleanup and runnable regression tests |
| `cd250ad` | fix: resolve CI HTTP 500, harden cleanup API, add UPLOAD_DIR and cleanup queue tests |
| `d424580` | docs: update Phase 2.1 remediation report with actual 112/112 local results |
| `3ea1dec` | fix(cleanup): atomic queue insertion within transaction, canonical path validation, and concurrent worker safety |
| `HEAD` | docs: update Phase 2.1 remediation report with Phase 2.1.2 hardening and tested guarantees |
