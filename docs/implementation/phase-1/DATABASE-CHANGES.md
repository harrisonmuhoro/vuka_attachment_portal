# Phase 1 database changes

## Migration

`migrations/001_phase1_security.sql` makes two data-preserving changes:

1. Changes `users.verification_code` from `VARCHAR(10)` to `VARCHAR(255)` so it can hold a password hash.
2. Marks the original seeded `admin@vuka.local` Super Admin inactive, preserving the row while preventing use of the published default account.

The migration runner is `scripts/migrate.php`. It is CLI-only and tracks applied migrations in `schema_migrations`; running it again is idempotent. Back up the database before applying it. The application database was not changed during this implementation.

## Rollback

`scripts/rollback-001.php` narrows the verification column only if every stored value fits the legacy width. It refuses to truncate verification hashes. It intentionally leaves the seeded administrator inactive; reactivate only after setting a new, controlled password.

## Verification

`tests/security/phase1_migration_test.php` creates a uniquely named disposable MySQL database, applies and re-applies the migration, checks the targeted account and unrelated data, tests a safe rollback, verifies unsafe rollback refusal, and drops only the database it created. It passed 5 checks. Do not run `config/database.sql` as a migration; it is an initial setup script and contains destructive table setup statements.
