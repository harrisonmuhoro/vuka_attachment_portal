# Phase 3.0 — Baseline Document Audit

**Audited baseline:** `origin/main` at `121bbcd1faef196c09d5a95b1a2d934377786603` (2026-11-13)  
**Local checkout at audit start:** `main` at `ed20d59f1641ebd6163e561b014e71799d82e204`, three commits behind. The fetched baseline includes Phase 2.2 cleanup reliability changes.  
**Scope:** read-only inspection of the document schema, upload/download/retrieval flows, applicant and administrator UI, session/RBAC/department controls, cleanup queue/worker, migrations, tests and CI. No application code or database was changed.

## Executive summary

The live design in the repository is database BLOB storage. `documents.file_content` is a `LONGBLOB`; the current application upload endpoint writes bytes and metadata in the same database transaction as the submission. Normal submission API responses expose document metadata only, and the download endpoint streams binary content after checking applicant ownership or administrator department scope. `file_path` and `UPLOAD_DIR` remain as a legacy filesystem-cleanup seam; normal BLOB uploads save an empty path.

**Recommendation for Phase 3:** retain private BLOB storage and add immutable version records in the same database. It matches the deployed behavior, gives atomic metadata/content updates, and avoids introducing a file/database dual-write or a BLOB-to-filesystem cutover. The current 2 MiB per-file limit makes BLOB storage a reasonable fit for the observed workload, subject to database growth and backup monitoring. The design and proposed migration shape are in [DATABASE-DESIGN.md](DATABASE-DESIGN.md).

## Existing schema and relationships

- `submissions.user_id` references `users.id` with `ON DELETE CASCADE`.
- `documents.submission_id` references `submissions.id` with `ON DELETE CASCADE`; current indexes cover submission and document type.
- `documents` currently has a numeric ID, type string, original filename, optional `file_path`, MIME type, integer byte size, upload timestamp and nullable `file_content LONGBLOB`.
- The schema comment lists five upload slots: `application_letter`, `campus_letter`, `insurance_cert`, `academic_certs`, and `national_id_copy`. These are observed UI/API keys, not a verified statement that every type is mandatory.
- There is no document uploader identity, current-version marker, verification state, verification history, expiry date, or controlled document-type table today.
- The consolidated `config/database.sql` contains destructive `DROP TABLE` statements and is an initialization artifact. It must not be used to deploy Phase 3. Changes must use forward-only versioned migrations.

## Storage architecture assessment

| Concern | Current BLOB design | Private filesystem alternative |
|---|---|---|
| Existing compatibility | Directly matches `documents.file_content`; no conversion needed | Requires moving existing bytes and changing readers/writers |
| Consistency | Content and metadata can commit/roll back together in PDO transaction | Requires staged file writes, reconciliation, and compensation around DB transactions |
| Scale and database growth | BLOBs enlarge database, backups, and restore time; each current upload is capped at 2 MiB | Keeps database smaller but requires separately managed private storage and coordinated backups |
| Retrieval | Existing endpoint loads bytes and streams them; avoid JSON and enforce limits | Can stream from disk, but every reference and path must be validated and protected from traversal/symlinks |
| Cleanup integration | BLOB bytes are removed by the database's existing submission cascade; no disk cleanup job is needed for them | Can use Phase 2.2 queue/worker after commit, but race/retention behavior must be designed for versions |
| Operational fit | No external service or new runtime dependency | `UPLOAD_DIR` is under the repository directory by default; it is not demonstrated to be outside the public webroot |

### Decision

Retain BLOB storage for this phase. This is a continuity decision based on the implementation that is actually present, not a claim that BLOBs scale indefinitely. The application currently limits each upload to 2 MiB, already uses PDO transactions for submission creation, and does not currently store uploaded application documents as ordinary files. Private filesystem storage would add a migration and rollback problem without an established deployment requirement or demonstrated scale need. Revisit only if measured database/backup growth justifies that operational cost.

The Phase 2.2 worker is valuable for legacy physical paths, but its existence is not evidence that the current applicant upload path uses filesystem storage. Do not make the new version store depend on `UPLOAD_DIR` or change the cleanup worker for BLOB-only versions.

## Upload and applicant interface

- `api/submit-application.php` derives the applicant from `requireStudent()` and re-reads the account by session user ID. It requires an active verified applicant, rejects an already-existing submission, inserts the submission and uploaded BLOB rows in one transaction.
- It recognizes five fixed form fields, limits files to 2 MiB, checks extension and Fileinfo MIME against PDF/JPEG/PNG allowlists, normalizes the original filename, and reads content into the BLOB. There is no shared document upload service; validation currently lives in this endpoint.
- `assets/js/common.js` assembles the same named fields from the application form. Existing browser checks are convenience only; server validation is authoritative.
- No separate applicant document replacement/versioning interface was found. Existing application submission is the upload workflow.

## Retrieval, download and administrator interface

- `api/get-submissions.php` returns document metadata (`id`, type, filename, MIME, size and upload time) without the BLOB. Applicant submission queries are scoped with `s.user_id`; supervisor/admin queries use the server-derived department scope.
- `api/download-document.php` supports a document ID or the legacy submission/type selector. The SQL joins through `submissions` and applies owner or department scope before fetching the BLOB. It allowlists the three MIME types and streams as an attachment with `nosniff` and private/no-store cache headers.
- `assets/js/common.js` invokes the download endpoint using a document ID. Dashboards consume the scoped submission API; no separate verification or replacement UI exists.
- Current browser code does not send an applicant ID for authority. Session authentication revalidates account status and role from the database; administrator permissions and department scope are server-derived.

## Verification, versioning, type policy and expiry

No document-specific verification/rejection fields, review API, verification history table, version history, expiry field, or expiry report was found in the audited schema and code. Existing `review_history` concerns application status, not document verification. The five upload keys appear in both UI and server code, while allowed file types are globally configured as PDF/JPEG/PNG. The implementation should model the observed keys as controlled types without inferring new mandatory requirements or expiry rules.

The existing permission registry has no document verification permission. Phase 2's role matrix allows application accept/reject to HR/Super Admin and not Department Supervisors. Until an approved permission policy says otherwise, document verification should follow that same authority boundary; supervisors retain in-department document read/download access only.

## Authorization and security baseline

- Student access is tied to the authenticated student session and the document's parent submission.
- Administrator access is tied to the validated admin session, centralized existing role/permission model, and department scope; supervisors are scoped to their department while HR/Super Admin receive system-wide scope where current permissions allow.
- CSRF protection for cookie-authenticated mutations is part of the shared session controls. New mutation endpoints should use those controls and the existing permission registry rather than inventing document roles.
- Document bytes are not included in ordinary JSON metadata responses. The download path is the controlled binary route.

These are baseline findings, not a claim that all future document mutations already have suitable controls. Replacement, verification and metadata endpoints do not exist and must apply these rules when introduced.

## Phase 2.2 deletion and cleanup integration

At the fetched baseline, `api/delete-submission.php` locks the submission and associated document rows where supported, queues nonempty physical `file_path` values, deletes the submission, and records the audit event in one database transaction. Queue insertion failures therefore roll back the delete. BLOB rows have empty `file_path` and are removed through the existing submission/document cascade. The Phase 2.2 worker claims batches, validates relative paths against `UPLOAD_DIR`, checks canonical path/symlink containment, deletes files outside the claim transaction, and retries failures. Preserve this transaction and worker architecture; version BLOBs should follow the database retention policy, not be added to the filesystem queue.

## Baseline tests and CI

- `.github/workflows/ci.yml` runs PHP and JavaScript syntax checks, `tests/security/phase1_security_tests.php`, and a disposable MySQL migration regression suite.
- The security suite covers anonymous download denial, owner-only document download, administrator scoping, metadata responses excluding BLOB content, application upload validation and BLOB association, and submission cleanup behavior.
- The latest main commit's Phase 2.2 changes include a dedicated cleanup path validator and worker plus worker exit-code coverage.
- This audit did not run test suites. The repository brief explicitly sequences baseline and design review before implementation; implementation verification belongs to the subsequent approved sequence.

## Open design choices for review

1. Keep document bytes in MySQL BLOB columns and place each immutable revision in a version table.
2. Treat current `documents` rows as stable logical documents and point each to an immutable current version. A replacement creates a new `pending` version; it cannot inherit a prior version's verification.
3. Keep the observed five document keys configurable in a type table, without making any type mandatory or requiring expiry absent an existing business rule.
4. Keep migration forward-only. Preserve legacy columns/rows through cutover and document an application rollback path; do not drop or rewrite historical content during rollback.

These decisions are proposed in the accompanying database design for review. No implementation branch has been created and no Phase 3 runtime code or migration has been applied.
