# Phase 1.1 database changes

The fresh-install schema now defines `users.verification_code` as `VARCHAR(255)`, which accommodates the output of `password_hash()`. Existing installations must have Phase 1 migration `001_phase1_security` applied; that migration widens the column safely. The implementation otherwise uses existing role, department, vacancy, submission, review-history, and audit tables. The workflow relies on transactional row locks supported by MySQL/InnoDB; SQLite fixtures exercise endpoint behavior but do not prove MySQL locking semantics.

The CLI expiration task updates existing approved vacancies with past deadlines. Run it only after reviewing the deployment notes. It does not run automatically on API GET requests.

The migration regression suite creates and drops a uniquely named disposable database. Its vacancy-maintenance assertion invokes the shared operation directly against the disposable connection; it does not execute the scheduler subprocess, avoiding any chance that a local `.env` could redirect the test command to the application database.
