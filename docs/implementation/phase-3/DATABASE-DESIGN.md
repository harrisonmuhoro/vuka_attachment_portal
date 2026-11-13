# Phase 3.1 — Document Database Design (Review Proposal)

**Design baseline:** `origin/main` at `121bbcd1faef196c09d5a95b1a2d934377786603`  
**Status:** proposed for review; no migration has been written or applied.

## Decisions for review

1. Retain MySQL BLOB storage. It matches the existing `documents.file_content` implementation and keeps document content and metadata in the transactional database.
2. Make `documents` the stable logical document and `document_versions` the immutable file/review unit.
3. Each logical document points to exactly one current version. Verification status belongs to a version, never to a mutable logical document. Replacing a document creates a new `pending` version and changes the pointer transactionally; earlier bytes and decisions remain unchanged.
4. Model only the five existing upload keys as initial document types. Do not infer mandatory uploads or expiry requirements.
5. Use append-only verification history and the existing `audit_logs` for actor-level audit events. Keep the schema migration forward-only.

## Proposed model

### `document_types`

Controlled catalog keyed by stable `type_code` (for example `application_letter`). Include display name, active flag, allowed MIME/extensions, maximum byte size, `expiry_required` (default false), and timestamps. Seed the five observed upload keys. This is validation/configuration data; it does not mean every type is required for submission. Do not add a requirement matrix without a business rule in the existing application.

### `documents` (logical document)

Preserve the existing table and rows. Add `current_version_id` (nullable while migration/backfill is in progress) and timestamps as needed. Keep `submission_id` as the authoritative parent relationship. Applicant ownership is derived by joining `submissions.user_id`; do not duplicate an independently mutable applicant ID. Keep `document_type` in place for compatibility and make it reference `document_types.type_code` after validating/backfilling existing values.

The existing legacy file metadata/content columns remain during cutover for compatibility and rollback. New application code treats `document_versions` as authoritative. Any legacy shadow fields must be synchronized within the same transaction until a separately reviewed compatibility retirement. This temporarily duplicates current BLOB bytes; quantify this in the migration report and do not remove old bytes as part of a failed or rolled-back deployment.

### `document_versions` (immutable revision and status)

Each row contains:

- Primary key `id`; `document_id` FK to `documents` with `ON DELETE CASCADE`.
- `version_number` positive integer, unique per document; index `(document_id, version_number)`.
- Original filename, MIME type, extension, byte size and `file_content LONGBLOB`.
- `uploaded_by_user_id` nullable FK to `users`; `uploaded_by_admin_id` nullable FK to `admin_users`; a check constraint requires exactly one uploader identity. Admin uploads remain disabled unless an existing workflow explicitly authorizes them.
- `uploaded_at`, optional version/replacement notes, optional `expires_at`.
- Mutable workflow field `verification_status` constrained to `pending`, `verified`, `rejected`, initially `pending`; file bytes and identity metadata are immutable after insert. Status changes are allowed only through the transactional verification service and are captured in history.
- `verified_by_admin_id` nullable FK to `admin_users`, `verified_at`, and permitted `reviewer_feedback` / `rejection_reason`.
- Created timestamp and immutable content identity (prefer SHA-256 `content_sha256`) for integrity and duplicate detection.

Enforce unique `(document_id, version_number)` and indexes for document/current lookup, status, uploader, and expiry reporting. Keep current-version membership authoritative in `documents.current_version_id`; do not duplicate a mutable `is_current` flag on every version. A replacement transaction locks the logical document, allocates `MAX(version_number)+1`, inserts the immutable version, then updates `current_version_id`. The new version starts pending and the old version keeps its bytes and verification result.

### `document_verification_history` (append-only decisions)

Each event references both `document_id` and `document_version_id`, records prior and new status, reviewer admin FK, reviewed timestamp, optional notes/rejection reason, and a request/correlation identifier where available. Index by `(document_id, reviewed_at)`, `(document_version_id, reviewed_at)`, and reviewer. The service validates status transitions and writes the version status update, history row, and existing `audit_logs` event in one transaction. Never update/delete prior history through application endpoints.

The event's `document_id` is useful for querying but should be integrity-checked against the referenced version's parent in the service or represented by a composite FK if supported by the chosen MySQL version and exact migration syntax.

## Verification and current-version invariant

The displayed status is read from the version referenced by `documents.current_version_id`. There is no `documents.verification_status` field that could remain `verified` after replacement. A verification command takes the expected version ID, locks the logical document/version, verifies that it remains current, checks the reviewer permission and department scope, prevents applicant self-verification, validates the transition, and commits version status plus history plus audit atomically. A stale review request for an older version fails without changing the current status.

This makes approval version-specific by construction: replacement points to a fresh pending version; the earlier verified version remains historical and cannot confer approval on the replacement.

## Expiry semantics

`expires_at` is optional per version. Only catalog types with an established expiry rule require it; no such rules were found in the baseline. An expiry change should create an auditable metadata event or be recorded in history rather than rewriting content history silently. Expiry never deletes a version. Reports classify current versions as expired, expiring soon (central configurable window), valid, or no expiry.

## Relationships and deletion/retention

- Existing `documents.submission_id -> submissions.id ON DELETE CASCADE` remains. A submission deletion removes logical documents, versions, and verification history in the same database transaction. No BLOB cleanup queue entry is created because bytes reside in MySQL.
- `document_versions.uploaded_by_*`, `verified_by_admin_id`, and history reviewer use `ON DELETE SET NULL` so personnel lifecycle changes do not erase evidence. Retain the numeric actor reference where allowed by account-retention policy only if the FK policy is changed and documented; do not cascade-delete audit evidence with an admin account.
- Document type references should use `RESTRICT` for types in use; deactivate catalog entries instead of deleting them.
- The existing submission deletion endpoint and Phase 2.2 queue transaction are preserved. Its physical path queue continues to handle only legacy physical-file references, if any.
- Avoid a cascading foreign-key cycle between `documents.current_version_id` and `document_versions.document_id`. Keep the pointer nullable during backfill and validate that it names a version whose `document_id` matches in the transactional service; if an FK is retained, its `ON DELETE` behavior must be proven safe with the submission cascade in the target MySQL version. Do not choose `RESTRICT` in a way that blocks authorized submission deletion.
- Retention of applicant records and legally required deletion/retention periods are not specified by the baseline. Keep current cascade behavior for submission deletion unless a documented policy changes it; Phase 3 must not invent a new retention period.

## Migration sequence and rollback

Use a new numbered migration (expected next sequence after `002`, subject to checking the fetched baseline migration list at implementation time):

1. Create `document_types`, `document_versions`, and `document_verification_history` additively; add nullable `documents.current_version_id` and required indexes. Do not recreate `documents` or `submissions`.
2. Seed only the five observed type codes. Validate every existing `documents.document_type` against the seed before adding a foreign key.
3. Backfill one version per existing document, preserving ID relationships, filename, MIME, size, and BLOB. Set legacy versions to `pending` because no reliable historic document verification state exists. Set current pointers only after content/metadata checks pass. Migration must be restartable/idempotent or fail clearly before partial cutover.
4. Keep legacy fields populated during the application rollout and compatibility window. Compare row counts and content hashes. Do not declare cutover complete if any non-null legacy BLOB lacks a corresponding version.
5. Add/validate referential constraints and make `current_version_id` required only after successful backfill, if DB engine/version permits a safe online alteration.

Migrations are forward-only. Rollback means redeploying the prior application while retaining additive tables and legacy columns; prior endpoints can continue reading existing `documents.file_content`. Do not drop version/history tables or delete newer uploaded content as an automatic rollback. A later retirement migration for legacy columns requires a separate backup/restore plan and review after the compatibility window.

## Indexes and common queries

- Logical documents by submission and type: existing `idx_documents_submission`, `idx_documents_type`; add unique `(submission_id, document_type)` only if implementation confirms exactly one logical document per type is a valid business rule. Existing data and multiple-upload workflows must be checked first.
- Versions by document and descending version number: `(document_id, version_number)` unique.
- Current verification work queue: `(verification_status, uploaded_at)`.
- Expiry reports on current versions: `expires_at`, with application join through `current_version_id`; avoid assuming an index can cover joined currentness.
- History by document/version and time; reviewer/time for audit reporting.
- Type activation lookup: `active` or primary code lookup.

## Compatibility and implementation gate

The existing endpoints and UI expect a `documents` row ID, filename/type metadata, and a BLOB-backed download route. Preserve that contract where practical by retaining the logical document ID in public APIs and resolving bytes from the current version internally. Do not expose storage IDs or paths in JSON. Applicant ownership and administrator scope continue to come from the authenticated session and parent submission.

The audited permission model has no document verification permission today. Phase 2 permits application accept/reject to HR Coordinator and Super Admin, but not Department Supervisors. The initial verification implementation should use the existing registry and preserve that authority boundary; supervisors may read/download only within their department unless a separately approved policy expands their authority.

Please review the storage decision, compatibility shadow period, logical-document/version split, status invariant, deletion behavior, and migration/rollback sequence before Phase 3.2 implementation begins. No database was modified to prepare this proposal.
