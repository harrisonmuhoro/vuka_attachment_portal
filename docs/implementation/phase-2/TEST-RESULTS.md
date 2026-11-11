# Phase 2 test results

Latest local run:

- `php tests/security/phase1_security_tests.php`: **107 passed, 0 failed**, including Phase 2 department isolation, supervisor destructive-action denial, administrator lifecycle/session invalidation, HR lifecycle denial and authenticated profile-update checks.
- PHP syntax checks passed for all Phase 2 changed PHP files.
- `git diff --check` passed.

The existing disposable MySQL migration suite remains part of CI. Local Xdebug may print a warning when `d:/wamp64/logs/xdebug.log` is unavailable; it does not change the test exit code. GitHub Actions runs `43027350312` and `43027435810` reached the regression step but failed there; GitHub's public API exposes no test output without repository log access. The local suite passes, but hosted CI is an unresolved review item and is not claimed as successful.
