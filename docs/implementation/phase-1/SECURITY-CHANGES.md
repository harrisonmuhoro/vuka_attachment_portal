# Phase 1 security changes

## Authentication and authorization

- Centralized session creation and validation; session identifiers rotate at login, cookies use `HttpOnly` and `SameSite=Lax`, and `Secure` is enabled on HTTPS.
- Active status is checked for all accounts and verification status for applicants. Authorization roles come from database joins.
- Cookie-authenticated state-changing requests require the session CSRF token in `X-CSRF-Token` (or the form token). Bearer-authenticated API requests remain supported.
- Submission APIs scope applicant data to the signed-in applicant, supervisor data to the supervisor's department, and HR/Super Admin data to their authorized system-wide scope. Caller-supplied user IDs, PF numbers, and department values do not establish authority.

## Sensitive data

- Private document/profile endpoints require authentication and ownership or department authorization. Document metadata does not return stored BLOB contents; downloads use private no-store and nosniff headers.
- Verification and reset tokens are stored as hashes and expire; reset tokens are single-use. Reset links place the token in the URL fragment to keep it out of HTTP request paths.
- Login failures are generic, identifier-based throttling does not persist the raw identifier, and mail/error logs omit message content, credentials, and tokens.
- Registration no longer returns a verification code in the API response.

## Submission and review integrity

- Submission identity is derived from the authenticated user; the posted identifier is only checked for consistency. File type, extension, and size are validated before transactional storage.
- Review identity and department are derived from the authenticated admin account. Review changes use prepared queries and transactions, validate transition and rejection reason, and record history atomically.
- Applicant detail responses omit internal notes and history.

## Initial administrator and web exposure

- The SQL setup no longer inserts an active default Super Admin or a known password. Use `php scripts/provision-admin.php` from CLI to provision the initial administrator.
- Root web rules deny access to `.env`, SQL, logs, backup, and archive files and disable directory indexes.

## Known deployment controls

- Apply the migration only after a verified backup and staging rehearsal.
- Configure HTTPS in production so session cookies receive the `Secure` attribute.
- Confirm review transition and applicant feedback policies with the product owner.
- The current mail transport can fall back to a local log when delivery is unavailable; deployment must confirm that production email is actually delivered and that the log directory is outside the document root.
