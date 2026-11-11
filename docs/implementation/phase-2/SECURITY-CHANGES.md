# Phase 2 security changes

Authorization is now expressed through a shared permission registry while the existing database-backed session guard remains the source of identity. Role, department and applicant ownership values from request bodies are ignored for authorization.

Supervisor department scope is applied to department enumeration and generated-document/evaluation submission lookups in SQL. Supervisors cannot use the administrative delete-submission path. Evaluation access retains a defense-in-depth scope check after the scoped lookup.

Administrator status changes have one authoritative service. It denies HR/supervisor callers, prevents self-modification, rejects invalid transitions, invalidates all target sessions on deactivation, writes an audit event in the same transaction, and rolls back if auditing fails.

`verify-session.php` now returns HTTP 401 when no valid session exists, making authentication failures consistent with protected API behavior.
