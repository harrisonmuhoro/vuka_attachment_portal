# Phase 1.1 deployment notes

1. Review and merge the priority-fix branch through the repository's normal review process; the changes are pushed to `feature` but are not merged to `main`.
2. Back up the database and deploy the application files. Fresh installs use the corrected `config/database.sql`; existing databases must have migration `001_phase1_security` applied so hashed verification codes fit.
3. Confirm `.env` remains private and contains the deployment's actual database and mail configuration; do not copy local credentials into source control.
4. Configure a CLI scheduler to run `php scripts/close-expired-vacancies.php` at an appropriate cadence if automatic expiration is desired. The command rejects web execution and closes approved vacancies with passed deadlines.
5. Verify PHP extensions, database permissions, application URL, HTTPS, and CI before production rollout.

The migration test requires a MySQL account permitted to create and drop a temporary database. The HTTP suite uses isolated fixtures. Local verification is not a substitute for deployment acceptance or monitoring.
