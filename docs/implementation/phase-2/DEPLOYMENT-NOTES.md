# Phase 2 deployment notes

Review and merge this branch through the normal pull-request process only after CI and code review pass. Do not run `config/database.sql` against an existing environment. No Phase 2 migration is required.

Deploy the PHP files and shared library together so the API endpoints and authorization services stay compatible. Keep `.env` private, retain HTTPS and the existing CSRF/session settings, and verify that the scheduler and mail fallback directories remain writable as configured.

After deployment, verify supervisor access with two departments, administrator deactivation/session invalidation, generated-document scope, and system-role analytics. No production database was touched during this implementation.
