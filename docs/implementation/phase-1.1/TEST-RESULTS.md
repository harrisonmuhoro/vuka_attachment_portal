# Phase 1.1 test results

## Local runs

- `php tests/security/phase1_security_tests.php`: **101 passed, 0 failed** on the final code run.
- `php tests/security/phase1_migration_test.php`: **6 passed, 0 failed** after changing the expiration assertion to call the shared operation directly on the disposable PDO connection. It does not run the scheduler against the configured application database.
- PHP syntax: **51 files passed** (`php -l`). JavaScript syntax: **4 changed files passed** (`node --check`). `git -c core.whitespace=cr-at-eol diff --check` passed.
- PHP emitted an Xdebug warning because `d:/wamp64/logs/xdebug.log` is unavailable. The security suite itself completed successfully.

## Scope and limitation

The CI workflow has been added but cannot be considered successful until GitHub Actions runs it. MySQL transaction row locking and production deployment remain unverified. Xdebug printed a local warning because `d:/wamp64/logs/xdebug.log` is unavailable; test results were unaffected.
