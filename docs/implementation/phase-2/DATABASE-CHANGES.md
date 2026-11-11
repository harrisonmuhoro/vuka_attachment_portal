# Phase 2 database changes

Phase 2 requires no production schema migration. The changes use the existing role, department, session, audit, administrator and application tables. Existing foreign-key and department-name compatibility is preserved.

Administrator lifecycle changes use MySQL row locking (`FOR UPDATE`) when available and a transaction covering status, session invalidation, creation-history state and audit insertion. If the audit insert fails, the status change rolls back.

The isolated test fixture was expanded to include the existing `departments.description` and `departments.min_admins` columns used by the production query. The disposable MySQL migration suite remains unchanged and never targets application data.
