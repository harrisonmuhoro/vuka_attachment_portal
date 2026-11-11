# Repository Structure and Inventory

## Snapshot

- Branch: `main`, tracking `origin/main`; audited commit `83aa34f` (`feat: add core application configuration, database schema, and profile view component`).
- Git history: 10 commits; only `main` and its matching remote branch were present.
- Tracked file count: **100**. No tracked local changes were present at audit start.
- Tracked composition: 78 PHP, 9 JavaScript, 6 CSS, 1 SQL, 1 Markdown, 1 PNG, 1 SVG, 2 `.htaccess`, and 1 `.gitignore`.
- API scripts: 46. Dashboard/page PHP files: four dashboards, login/registration entry page, reset page, and one shared profile component.

## Directory map

```text
/
├── index.php                         Login and registration UI; includes page scripts/styles
├── session-manager.php               DB-backed sessions, role checks, scoped helpers
├── audit-logger.php                  Audit event recording and querying
├── .htaccess                         Forwards Authorization header to PHP
├── .gitignore                        Local secrets, logs, SQL, editor, and generated-file rules
├── README.md                         Feature, setup, schema, and API documentation
├── api/                              46 request handlers (see API-INVENTORY.md)
├── assets/
│   ├── css/                          Shared and role-specific stylesheets (6)
│   ├── img/default-avatar.svg        Default profile image
│   └── js/                           Shared client code and role-specific modules (9)
├── config/
│   ├── config.php                    .env loader, constants, PDO, response/auth helpers
│   ├── database.sql                  Schema creation and seed script
│   └── .htaccess                     Denies direct HTTP access to config directory
├── lib/
│   ├── email-templates.php            Transactional HTML email templates
│   ├── mailer.php                    SMTP/PHPMailer wrapper and log-only fallback
│   ├── notifications.php             In-app notification helpers
│   ├── rate-limiter.php              Login-attempt persistence and throttling
│   ├── phpmailer/                    Vendored PHPMailer implementation (3 files)
│   └── fpdf/                         Vendored FPDF 1.86 and font definitions
├── logo/png.png                     Brand image
└── pages/
    ├── admin_dashboard.php           Super-admin dashboard
    ├── hr_dashboard.php              HR dashboard
    ├── supervisor_dashboard.php      Department supervisor dashboard
    ├── student_dashboard.php          Student dashboard
    ├── reset-password.php             Reset-password form
    └── components/profile_view.php    Shared editable profile panel
```

## Component relationships

Pages load the shared CSS/JavaScript and dashboard-specific modules. Client scripts call PHP files under `api/` using `fetch`. API scripts include `config/config.php` for `$pdo`, response helpers and shared constants; protected handlers include `session-manager.php`, which exposes `requireAuth` and role wrappers. Selected APIs additionally include `audit-logger.php`, mail, notification, rate-limit, or PDF libraries. The application is not organized around controllers/services/models: SQL and business rules usually live directly in endpoint files.

## External dependencies

- PHP with PDO MySQL and fileinfo extensions; README says PHP 7.4+.
- MySQL/MariaDB; README says MySQL 5.7+ and InnoDB/utf8mb4.
- Vendored PHPMailer (`PHPMailer.php`, `SMTP.php`, `Exception.php`) and FPDF 1.86.
- Browser assets pulled from CDNs: Bootstrap 5.3, Chart.js, Font Awesome 6.4, Google Fonts, and EmailJS browser SDK references. No Composer or npm manifest pins the complete dependency graph or provides reproducible installation.

## Missing or excluded inventory

- No tracked tests, CI workflows, migrations, Docker/container files, deployment manifests, lockfiles, Composer manifest, or npm manifest were found.
- `.gitignore` excludes `.env`, generic SQL files except `config/database.sql`, logs/debug output, uploads, test-named files, and internal planning files. The local workspace also has ignored `.env`, root `database.sql`, and `debug.log`; file contents are not included here because they may contain private configuration or personal/session data.
- `config/database.sql` is tracked and is the schema inspected for this audit. The separate ignored root SQL file is not treated as a source of truth.
- `docs/audit/phase-0/` is the audit output directory. No other setup or installation scripts are tracked.

## References

See [architecture](ARCHITECTURE-ASSESSMENT.md), [database](DATABASE-ASSESSMENT.md), and [DevOps](DEVOPS-ASSESSMENT.md) for component-level conclusions.
