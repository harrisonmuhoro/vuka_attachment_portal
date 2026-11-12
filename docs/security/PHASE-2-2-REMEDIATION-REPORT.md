# Phase 2.2 — Atomic Submission Cleanup and Worker Reliability Report

**Repository:** `https://github.com/harrisonmuhoro/vuka_attachment_portal`  
**Base commit (main):** `ed20d59f1641ebd6163e561b014e71799d82e204`  
**Working branch:** `feature/phase-2-2-atomic-cleanup-reliability`  
**Date:** 2026-11-12  
**Status:** All tasks implemented and verified. All 122 security tests and 7 migration tests passing (0 failures).

---

## 1. Executive Summary & Problem Diagnosis

In Phase 2.1, a retryable cleanup mechanism was introduced via `submission_cleanup_queue`. However, a comprehensive post-merge engineering audit identified critical reliability and security defects:

1. **Non-Atomic Failure Window:** `api/delete-submission.php` deleted the submission, committed the transaction, and only then inserted rows into `submission_cleanup_queue`. If the server crashed or threw a fatal error between commit and queue insertion, physical documents on disk remained orphaned without any record to trigger cleanup.
2. **Inadequate Path Canonicalization:** The worker previously concatenated `UPLOAD_DIR . $row['file_path']` without rejecting absolute paths, stream wrappers, directory traversal sequences (`../`), or validating that the resolved path remained strictly within the permitted upload boundary.
3. **Missing Concurrency Controls:** The initial worker script lacked row-level lock claiming (`FOR UPDATE SKIP LOCKED`) and lease expiration, leaving open the risk of duplicate work by concurrent cron jobs or worker instances.
4. **Superficial Tests:** Previous tests asserted that a queue row existed after deletion, but did not simulate queue failure, audit failure, worker file deletion, path traversal attacks, symlink escapes, or crash recovery.
5. **HTTP Status Inconsistency:** Deleting a non-existent submission returned HTTP 200 with `success: false` rather than standard HTTP 404.

Phase 2.2 resolves every defect identified above without introducing frameworks or altering established business workflows.

---

## 2. Architectural Remediation Details

### 2.1 Atomic Transaction Sequence (`api/delete-submission.php`)

All database mutations for submission deletion now execute within a single database transaction. The execution sequence is strictly:

```
[1]  Enforce POST method (405 on violation).
[2]  Authenticate admin session & require 'applications.delete' permission.
[3]  Validate input ID format (> 0, numeric, 400 on error).
[4]  $pdo->beginTransaction().
[5]  Lock & verify target submission:
       SELECT id, department_applied FROM submissions WHERE id = ? [FOR UPDATE]
       -> 404 if not found (rollback).
[6]  Enforce horizontal department isolation:
       -> 403 if supervisor department mismatches (rollback).
[7]  Retrieve associated document file paths:
       SELECT id, file_path FROM documents WHERE submission_id = ? [FOR UPDATE]
[8]  Normalize & insert cleanup queue rows:
       INSERT INTO submission_cleanup_queue (submission_id, file_path) VALUES (?, ?)
       -> Skips empty/BLOB-only documents.
       -> Normalizes paths to canonical relative format.
[9]  Delete submission row:
       DELETE FROM submissions WHERE id = ? (assert rowCount === 1).
[10] Record audit event:
       AuditLogger->log(...) (throws RuntimeException if recording fails).
[11] $pdo->commit().
     -> Returns HTTP 200 { success: true, message: "Submission deleted successfully" }.
[EXCEPTION] Catch block:
     -> If in transaction: $pdo->rollBack().
     -> Submission, documents, and queue remain unchanged.
     -> Logs diagnostics to error_log without disclosing file paths.
     -> Returns generic HTTP 500 { success: false, message: "Failed to delete submission" }.
```

**Guaranteed Condition:** There is zero execution path where submission deletion commits but cleanup queue records do not commit. Physical disk unlinking is never performed inside this transaction.

---

### 2.2 Canonical Path Validation Library (`lib/cleanup-path-validator.php`)

To prevent traversal, injection, and arbitrary file deletion attacks:

1. **Storage Canonicalization (`CleanupPathValidator::normalizeForQueue`):**
   - Rejects null bytes (`\0`), traversal sequences (`..`), URI schemes (`phar://`, `file://`), absolute drive letters (`C:\`), and leading root/UNC slashes.
   - Normalizes directory separators to forward slashes (`/`).
   - Strips leading `./` and redundant `uploads/` prefix if present, ensuring all queue paths are uniformly relative to `UPLOAD_DIR`.
   - Returns empty string for blank/BLOB paths (preventing redundant queue rows).

2. **Resolution & Boundary Enforcement (`CleanupPathValidator::resolveAndValidate`):**
   - Canonicalizes `UPLOAD_DIR` via `realpath()`.
   - Rejects absolute paths, stream wrappers, and traversal patterns in stored queue rows.
   - Resolves target path against `UPLOAD_DIR`.
   - **Existing Files & Symlinks:** Uses `realpath()` to resolve the final target and verifies `strncmp($canonicalTarget, $realUploadDir, strlen($realUploadDir)) === 0`. Symlinks pointing outside `UPLOAD_DIR` are rejected.
   - **Missing Files:** Validates that the target's parent directory is contained within `UPLOAD_DIR`, and returns `exists: false, valid: true` so the worker can treat it as already cleaned without calling `unlink`.

---

### 2.3 Reliable Cleanup Worker (`lib/submission-cleanup-worker.php` & `scripts/process-submission-cleanup.php`)

1. **CLI Only:** Protected via `PHP_SAPI !== 'cli'`.
2. **Concurrency Control:**
   - On MySQL: claims pending rows using `SELECT ... FOR UPDATE SKIP LOCKED`.
   - Immediately sets a lease window: `UPDATE ... SET available_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)`.
   - Other workers skip these claimed rows, preventing duplicate processing.
3. **Crash Recovery:**
   - If a worker terminates abruptly mid-batch, the lease expires after 10 minutes and the records become available for subsequent worker runs.
4. **Physical Deletion Decoupling:**
   - Physical `@unlink()` executes individually per row outside any database transaction.
   - Missing files are treated as successfully cleaned and marked `completed_at = NOW()`.
5. **Bounded Exponential Backoff & Terminal Failure:**
   - Retries follow $2^{\text{attempts}}$ minutes, capped at 60 minutes for physical deletion failures and 1440 minutes for invalid paths.
   - After `maxAttempts` (default 5), the record is marked with terminal error text (`Terminal failure (max retries reached): ...`) and excluded from future automated batches (`WHERE attempts < 5`), preventing infinite retry loops while preserving records for operational audit.

---

## 3. Schema Evaluation (Task 5)

Evaluation of `migrations/002_submission_cleanup_queue.sql`:
```sql
CREATE TABLE IF NOT EXISTS submission_cleanup_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    file_path TEXT NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_error TEXT NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cleanup_ready (completed_at, available_at),
    INDEX idx_cleanup_submission (submission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Findings:**
- Pending state: Supported via `completed_at IS NULL AND attempts < 5 AND available_at <= NOW()`.
- Claim / lease state: Supported via `available_at = NOW() + INTERVAL 10 MINUTE`.
- Retry scheduling: Supported via `available_at` and `attempts`.
- Completion state: Supported via `completed_at = NOW()`.
- Terminal failure: Supported via `attempts >= 5` and descriptive `last_error`.
- Operational inspection: Simple queries on `attempts >= 5` surface all terminally failed items.
- Existing indexes (`idx_cleanup_ready`, `idx_cleanup_submission`) fully index the worker claim query.
- **Conclusion:** No additional migration is required; the schema is completely adequate and avoids breaking changes.

---

## 4. Test Suite & Verification Matrix (Task 4)

All tests were executed against the isolated test harness in `tests/security/phase1_security_tests.php` and `tests/security/phase1_migration_test.php`.

### Transaction Integrity Tests

| ID | Test Description | Result |
|---|---|---|
| **T1** | Simulated queue insertion failure rolls back submission deletion and documents | **PASS** (HTTP 500; submission 901 preserved) |
| **T2** | Simulated audit failure rolls back deletion and leaves no partial queue records | **PASS** (HTTP 500; submission 902 preserved; queue count = 0) |
| **T3** | Successful deletion atomically commits submission delete, audit record, and canonical queue rows | **PASS** (HTTP 200; submission gone; 2 queue rows stored with canonical paths; audit logged) |
| **T4** | Delete-submission API response does not expose internal paths or cleanup arrays | **PASS** (Response verified clean) |
| **T5** | Deleted submission returns HTTP 404 on subsequent delete attempt (idempotent guard) | **PASS** (HTTP 404; { success: false, message: "Submission not found" }) |

### Worker Reliability Tests

| ID | Test Description | Result |
|---|---|---|
| **W1** | Cleanup worker physically deletes existing file and marks record completed | **PASS** (File deleted from disk; `completed_at` populated) |
| **W2** | Cleanup worker treats already missing file as successfully cleaned | **PASS** (Marked completed without error) |
| **W3** | Cleanup worker rejects path containing null byte and schedules retry | **PASS** (Attempts incremented; error logged; not unlinked) |
| **W4** | Cleanup worker rejects directory traversal and prevents file deletion outside upload dir | **PASS** (Outside victim file preserved; traversal error recorded) |
| **W5** | Cleanup worker rejects symlink pointing outside permitted upload directory | **PASS** (Outside target preserved; escape error recorded) |
| **W6** | Failed physical deletion increments attempts and schedules retry with future `available_at` | **PASS** (Attempts = 1; `available_at` set to future) |
| **W7** | Successful retry marks record completed and clears `last_error` | **PASS** (Obstacle removed; record marked completed) |
| **W8** | Duplicate worker run on completed records claims zero rows and corrupts nothing | **PASS** (Claimed = 0; no data corruption) |
| **W9** | Interrupted worker in-flight claim expires and subsequent worker successfully recovers and processes record | **PASS** (Expired claim picked up and completed) |
| **W10** | Max retry limit triggers terminal failure and permanently halts reprocessing of bad record | **PASS** (Attempts = 5; terminal failure message recorded; excluded from subsequent runs) |

### Test Summary
- **Security Regression Suite (`tests/security/phase1_security_tests.php`):** **122 passed, 0 failed.**
- **MySQL Migration Suite (`tests/security/phase1_migration_test.php`):** **7 passed, 0 failed.**
- **PHP Syntax Checks:** 100% passed across all PHP files.
- **JavaScript Syntax Checks:** 100% passed via `node --check`.
- **Git Diff Check:** Clean, no whitespace or conflict errors.

---

## 5. Distinction: Tested Guarantees vs Operational Assumptions

| Property | Classification | Proof / Mechanism |
|---|---|---|
| **Atomic Deletion & Queueing** | **Tested Guarantee** | Proven by tests T1 and T2: when queue insertion or audit fails, the transaction is rolled back completely. |
| **No Path Disclosure in API** | **Tested Guarantee** | Proven by test T4: JSON response strictly returns generic confirmation. |
| **404 On Missing Submission** | **Tested Guarantee** | Proven by test T5: querying a non-existent or previously deleted submission yields HTTP 404. |
| **Canonical Path Resolution** | **Tested Guarantee** | Proven by tests W3, W4, and W5: traversal (`..`), null bytes (`\0`), and out-of-boundary paths are rejected. |
| **Crash Recovery & Leases** | **Tested Guarantee** | Proven by test W9: worker leases expire after 10 minutes, allowing subsequent workers to claim in-flight rows. |
| **Terminal Failure Protection** | **Tested Guarantee** | Proven by test W10: records reaching `maxAttempts` (5) are flagged terminal and permanently excluded. |
| **Concurrent Row Skipping** | **Operational Assumption** | `FOR UPDATE SKIP LOCKED` operates on MySQL 8.0 InnoDB; SQLite test harness runs queries without `SKIP LOCKED` due to driver syntax limitations. |
| **Filesystem Write Permissions** | **Operational Assumption** | Assumes the cron/CLI user running `scripts/process-submission-cleanup.php` has OS permissions to delete files within `UPLOAD_DIR`. |

---

## 6. Files Changed in Phase 2.2

| File | Change Description |
|---|---|
| `api/delete-submission.php` | Replaced commit-then-queue with a single atomic transaction; added 404 for missing submissions; path normalization. |
| `lib/cleanup-path-validator.php` | Created path validation and canonical normalization library (`normalizeForQueue`, `resolveAndValidate`). |
| `lib/submission-cleanup-worker.php` | Created standalone worker service supporting concurrency locking, lease expiration, retry backoff, and terminal failure handling. |
| `scripts/process-submission-cleanup.php` | Refactored CLI worker to use `SubmissionCleanupWorker` with safe summary output and `--limit` support. |
| `tests/security/phase1_security_tests.php` | Added 15 comprehensive transaction integrity and worker reliability tests (total: 122 tests). |
| `docs/security/API-ENDPOINT-INVENTORY.md` | Updated status and description for `delete-submission.php`. |
| `docs/security/PHASE-2-1-REMEDIATION-REPORT.md` | Added clarification regarding Phase 2.1 historical commit-then-queue behavior. |
| `docs/security/PHASE-2-2-REMEDIATION-REPORT.md` | Created comprehensive Phase 2.2 audit and implementation report. |
