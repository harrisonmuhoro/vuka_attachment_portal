# Phase 0 Executive Summary

**Project:** Vuka Attachment Portal  
**Audit basis:** local `main` at `83aa34f`, tracked source, tracked schema, local environment file names only, and read-only inspection of the configured development database.  
**Scope boundary:** audit only. No application or database changes were made for this phase.

## Overall assessment

The repository contains a functioning PHP/MySQL application with student, supervisor, HR, and super-admin interfaces; 46 API scripts; a 17-table schema; session and role helpers; file handling; notifications; email; and PDF output. The architecture is a small, include-based PHP application with per-page vanilla JavaScript. It is understandable and avoids framework overhead, but the codebase has no automated test suite, CI pipeline, migration system, or reproducible production deployment process.

The application is **not ready for institutional production use** based on the inspected source. Two confirmed, unauthenticated API paths expose or modify sensitive application data. Publicly documented seeded administrator credentials, reset-link logging under the web root, raw session-token logging, inconsistent session creation, and code/schema drift add substantial risk. This is a source review, not a penetration test or legal compliance determination.

## Strengths observed

- PDO is configured with exceptions, associative fetches, and native prepared statements in the shared bootstrap.
- Password hashes use PHP password hashing APIs; session tokens are generated from `random_bytes(32)` and stored server-side with expiration and revocation flags.
- The session manager re-reads account state and role data from the database and provides role guard helpers.
- Uploads use `finfo` MIME detection, extension allowlists, and a 2 MiB limit; application and profile upload flows use database transactions or user-scoped updates.
- The schema has foreign keys for core user/application/document relationships, indexes for common lookups, and a separate review history.
- The current code includes `config/.htaccess`, a root Authorization-header rewrite, and a `.gitignore` rule keeping `.env` out of Git.
- Static syntax checks passed for all tracked PHP and JavaScript files in this checkout.

## Confirmed critical findings (P0)

1. `api/get-submissions.php` handles `?id=` before authenticating and selects submission details, review history, and full document rows. Its list branch also permits a request without a valid token to query all submissions. This is an unauthenticated exposure of student personal data and uploaded documents.
2. `api/update-submission.php` accepts caller-supplied `pf_number` and `department`; it only tries session validation when PF is absent. A caller can therefore submit a status change or review note without a valid session and can target arbitrary submission IDs.
3. The README and SQL seed disclose a usable default super-admin credential set. A deployment that retains the seeded account is immediately exposed to account takeover.

These should be treated as release blockers and addressed before Phase 1 can be considered complete.

## High-priority risks (P1)

- `lib/mailer.php` writes complete HTML message bodies to `logs/mail.log` in the document root when SMTP is unavailable. Password-reset emails include the reset URL and token; the root `.htaccess` does not deny that directory.
- `session-manager.php` writes raw session IDs and Authorization values to root-level `debug.log`. The file is ignored by Git but is not protected from HTTP access by the root Apache rules.
- Login endpoints create sessions in different ways. The schema requires `sessions.created_at` and `last_activity`, but two direct-insert paths omit them; success depends on SQL mode/default behavior. `change-password.php` uses a `password` column while the tracked schema defines `password_hash`.
- Admin activation/deactivation endpoints update columns absent from the tracked and locally inspected tables (`activated_at`, `activated_by`, `updated_by`, and deactivation-history fields), so these operations are expected to fail against this schema.
- There is no central state-transition guard or consistent department-scope check in submission updates; application and note mutation boundaries are not enforced centrally.
- CSRF helpers exist but are not invoked by the state-changing endpoints. The application accepts server-side session cookies as a fallback, so cookie-authenticated deployments need CSRF review.

## Major readiness gaps

- No tracked tests, test configuration, Composer/npm manifest, CI workflow, migration tooling, database backup/restore procedure, or deployment automation was found.
- The single checked-in schema script contains `DROP TABLE IF EXISTS` for its tables; it is an initialization/reset script, not a safe upgrade mechanism.
- Several API/frontend response styles coexist; endpoint exceptions and schema expectations are inconsistent.
- Submission listing executes a separate document query per submission and is unpaginated unless a page parameter is supplied. Documents and photos are stored as MySQL BLOBs.
- The README claims security headers and setup assets/features that are not uniformly present in the inspected source.
- A local `.env`, ignored root `database.sql`, and ignored `debug.log` exist. Their secret or personal contents are intentionally not reproduced in this report.

## Priority order

| Area | Status | Priority |
|---|---|---|
| Authentication and authorization | Critical unauthenticated read and write paths; seeded administrator credential risk | P0 |
| Database and schema alignment | 17-table schema inspected; runtime handlers reference absent columns; destructive reset SQL is not a migration | P1 |
| API and business workflows | 46 endpoints inventoried; access scope and status transition rules are inconsistent | P1 |
| Security and privacy controls | Token-bearing logs, verification-code response, CSRF gaps, and client-side HTML injection sinks identified | P1/P2 |
| Frontend and accessibility | Functional vanilla-JS pages; limited automated/browser accessibility evidence | P2 |
| Testing and quality gates | Syntax checks pass; no automated test suite or CI found | P1 |
| Performance and scalability | Unpaged listing paths, per-row document queries, and database BLOBs; capacity is not established | P2 |
| Operations and deployment | No reproducible deployment, migration, backup/restore, or CI process found | P1 |

| Priority | Focus |
|---|---|
| P0 | Close unauthenticated submission read/write paths; replace seeded admin credentials before any shared deployment. |
| P1 | Stop credential/token-bearing logs under the web root; unify and verify sessions; repair password and admin lifecycle schema mismatches; enforce endpoint authorization and transition rules. |
| P2 | Add migrations, automated security/regression tests, API consistency, pagination/query improvements, and operational backups/monitoring. |
| P3 | UI/accessibility and maintainability refinements after security and workflows are verified. |

## Phase 1 readiness

**Not ready to proceed.** Phase 1 is appropriately next, but should start only after explicit approval and should begin by closing the P0 findings with tests that prove anonymous requests cannot read or mutate records. See the [master roadmap](MASTER-IMPLEMENTATION-ROADMAP.md). No Phase 1 changes were made here.

## Evidence and limitations

The audit inspected 100 tracked files, including 46 API scripts, 78 PHP files overall, 9 JavaScript files, 6 CSS files, and the tracked SQL schema. The development database returned 17 tables matching the schema's table inventory; only table names and selected column names were queried. All tracked PHP files passed `php -l` under PHP 8.3.28, and all tracked JavaScript files passed `node --check`. No test suite exists to run. No load test, browser accessibility audit, penetration test, production configuration, backup, or legal review was performed.
