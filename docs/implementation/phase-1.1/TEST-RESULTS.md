# Phase 1.1 test results

## Local runs

- `php tests/security/phase1_security_tests.php`: **101 passed, 0 failed** on the final code run.
- `php tests/security/phase1_migration_test.php`: **7 passed, 0 failed**, including the fresh-install schema width assertion and maintenance operation against the disposable PDO connection. It does not run the scheduler against the configured application database.
- PHP syntax: **51 files passed** (`php -l`). JavaScript syntax: **4 changed files passed** (`node --check`). `git -c core.whitespace=cr-at-eol diff --check` passed.
- PHP emitted an Xdebug warning because `d:/wamp64/logs/xdebug.log` is unavailable. The security suite itself completed successfully.

## Scope and limitation

GitHub Actions run [43019440653](https://github.com/harrisonmuhoro/vuka_attachment_portal/actions/runs/43019440653) passed on commit `a0b4be9`. The two earlier runs failed workflow validation because `runner.temp` was used in job-level environment; the workflow now uses `/tmp/vuka-ci-logs` on the Ubuntu runner. MySQL transaction row locking and production deployment remain unverified. Xdebug printed a local warning because `d:/wamp64/logs/xdebug.log` is unavailable; local test results were unaffected.
