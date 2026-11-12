# Phase 2.1 remediation report

## Baseline

- Main SHA: `99a3da7e0a309be37d264aba74b81b342d17f93e`
- Working branch: `feature/phase-2-1-rbac-integration-ci-recovery`
- API surface inspected: 46 PHP files under `api/`.

## CI diagnosis

The workflow passed `fail-fast: true` to `shivammathur/setup-php@v2`. That action does not declare this input, producing the reported `Invalid action input 'fail-fast'` warning. The unsupported input was removed. GitHub's public logs endpoint returned HTTP 403 for the historical failed job, so the exact assertion-level hosted failure cannot be recovered without repository credentials. Local reproduction on Windows is environment-limited: the isolated PHP server did not start and the MySQL migration suite could not connect to the configured socket. These are recorded limitations, not passing results.

## Implementation

- Expanded `lib/authorization.php` into a single permission registry covering account lifecycle, applications, evaluations, reports, audit, interviews and profiles.
- Integrated explicit permission checks into bulk review, submission updates, analytics and interview endpoints.
- Reworked submission deletion so database deletion and its audit event commit before physical file cleanup; cleanup failures are returned and logged for retry handling.
- Added an individual 46-row endpoint inventory at `docs/security/API-ENDPOINT-INVENTORY.md`.
- Removed the invalid CI action input without changing unrelated workflow behavior.

## Verification

- PHP syntax checks for all changed PHP files: passed.
- `git diff --check`: passed.
- Existing security regression suite: not passing locally on Windows because its isolated PHP server did not start; no assertions were counted.
- Disposable MySQL migration suite: not executed successfully locally because the configured MySQL socket was inaccessible.
- Hosted GitHub Actions: historical failed logs unavailable through the unauthenticated public API; a successful rerun is still required before merge approval.

## Remaining risks and next steps

The CI job must be rerun on this branch with repository Actions access. The deletion cleanup path currently exposes cleanup failures and logs them; a durable cleanup queue should be added if operational retry guarantees are required. Full endpoint negative tests for every role and department, plus disposable MySQL migration execution, remain mandatory before a pull request is merge-ready.
