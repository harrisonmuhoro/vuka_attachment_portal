# Phase 1 deployment notes

1. Keep this work on `feature` until reviewed; it has not been merged to `main` or deployed.
2. Review `.env` and server configuration locally. Do not commit secrets. Production must use HTTPS and an external, protected log directory.
3. Back up the database and rehearse restoration. Apply `php scripts/migrate.php` from CLI in staging before production. Do not use `config/database.sql` to upgrade an existing database.
4. Provision the initial Super Admin from a trusted CLI terminal using `php scripts/provision-admin.php`. The provisioner prompts for the password; use a unique password and configure MFA/operational controls outside this application as available.
5. Confirm outgoing email delivery with the configured Resend settings. The application must not be considered operational for account verification/reset if the mailer falls back to local logging.
6. Validate sign-in, registration and verification, password reset, applicant submission, document download, staff review, and role scopes in staging with separate accounts.
7. Confirm review status transitions and applicant visibility of rejection reasons with the product owner.
8. Re-run the documented tests and review the changes before merging. No production-readiness claim is made by these notes.
