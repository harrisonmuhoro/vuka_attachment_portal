# Phase 1.1 test results

## Local runs

- `php tests/security/phase1_security_tests.php`: **101 passed, 0 failed** on the final code run.
- `php tests/security/phase1_migration_test.php`: **7 passed, 0 failed**, including the fresh-install schema width assertion and maintenance operation against the disposable PDO connection. It does not run the scheduler against the configured application database.
- PHP syntax: **51 files passed** (`php -l`). JavaScript syntax: **4 changed files passed** (`node --check`). `git -c core.whitespace=cr-at-eol diff --check` passed.
- PHP emitted an Xdebug warning because `d:/wamp64/logs/xdebug.log` is unavailable. The security suite itself completed successfully.

## Scope and limitation

GitHub Actions run `43018105895` was reported as failed for commit `313750767f212eb85ed9b6641fec22a8eac1601e`; GitHub's API listed no jobs, and the logs endpoint returned 403 because repository admin rights are required. A new push will trigger another run, but its result must be checked. MySQL transaction row locking and production deployment remain unverified. Xdebug printed a local warning because `d:/wamp64/logs/xdebug.log` is unavailable; local test results were unaffected.
