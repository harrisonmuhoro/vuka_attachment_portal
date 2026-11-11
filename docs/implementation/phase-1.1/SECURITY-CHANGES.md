# Phase 1.1 security changes

## Authorization and workflow

The individual and bulk submission routes now use the shared `lib/application-workflow.php` transition service. It derives the actor from the authenticated session, enforces the role transition matrix and supervisor department scope, validates required placement/rejection fields, locks target rows, and records status/history changes transactionally. Bulk requests are capped and fully validated before any row is changed; inaccessible or invalid records reject the entire batch.

Applicants can only withdraw their own eligible application. HR and Super Admin retain decision and lifecycle actions; supervisors can advance placement lifecycle states in their department, but cannot accept or reject applicants.

## Audit and vacancies

Audit-log reads scope supervisor queries, counts, and statistics in SQL. HR and Super Admin have system-wide access as directed by the user. Returned rows omit request metadata and before/after payloads.

Public vacancy reads return only approved, non-expired rows and public fields. GET performs no update. Administrative reads and writes enforce role and department boundaries, validate state changes, and write audit events transactionally. The scheduled closing operation is a CLI-only command.

## Credentials and profiles

Password-setting endpoints share `lib/password-policy.php` (12-character minimum, bounded input, PHP default algorithm). Existing credentials are not forcibly invalidated; successful logins can upgrade hashes. Administrator profile output uses the schema's `role_name` and excludes password/token data.

## Regression protection

The pre-existing Phase 1 HTTP security suite was extended instead of replaced. CI runs PHP/JavaScript syntax checks and HTTP/MySQL integration checks. See `TEST-RESULTS.md` for observed local output and limitations.
