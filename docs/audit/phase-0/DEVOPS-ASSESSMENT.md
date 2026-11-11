# DevOps and Deployment Assessment

**Audit Scope:** Source control practices, dependency lifecycle, CI/CD automation, environment configuration, server runtime dependencies, observability, and backup procedures at commit `83aa34f`.

---

## 1. Source Control & Repository Hygiene

| Metric | Current State | Risk / Impact |
|---|---|---|
| **Branching Strategy** | Single branch (`main`) | High: No staging, develop, or feature branch workflow is configured; direct pushes to main risk introducing regressions. |
| **Commit History** | 10 linear commits; descriptive commit messages | Moderate: History is clean and linear, but lack of tags or semantic versioning makes tracking releases difficult. |
| **Release Management** | No Git tags or GitHub releases | High: No immutable version snapshots exist for institutional deployment or rollback verification. |
| **Sensitive File Tracking** | `.gitignore` covers `.env`, logs, and uploads | Moderate: Core secrets are kept out of Git, but default super-admin credentials were committed to `README.md` and `config/database.sql`. |

---

## 2. Dependency Management Lifecycle

### A. PHP Dependencies (Backend)
- **Tooling:** No `composer.json` or `composer.lock` exists.
- **Vendoring Method:**
  - `PHPMailer` (v6.x) is manually vendored into `lib/phpmailer/` (`PHPMailer.php`, `SMTP.php`, `Exception.php`).
  - `FPDF` (v1.86) is manually vendored into `lib/fpdf/` along with 15 font definitions.
- **Risks:**
  1. No automated security auditing (e.g. `composer audit`) for known CVEs in third-party libraries.
  2. Manual upgrades are error-prone and risk introducing missing class definitions or autoloader breakages.
  3. No standardized PSR-4 autoloading; code relies on manual `require_once` statements across all scripts.

### B. JavaScript & CSS Dependencies (Frontend)
- **Tooling:** No `package.json` or `package-lock.json` exists.
- **Delivery Method:** Browser libraries are loaded directly from external Content Delivery Networks (CDNs):
  - Bootstrap 5.3 (`cdn.jsdelivr.net`)
  - Chart.js 4.4 (`cdn.jsdelivr.net`)
  - Font Awesome 6.4 (`cdnjs.cloudflare.com`)
  - Google Fonts (`fonts.googleapis.com`)
  - EmailJS SDK (`cdn.jsdelivr.net`)
- **Risks:**
  1. External CDN outages disrupt portal functionality.
  2. No Subresource Integrity (SRI) hashes (`integrity="sha384-..."`) are configured on CDN `<script>` tags, exposing clients to supply-chain tampering if a CDN is compromised.
  3. Deployment into air-gapped or restricted government intranet environments will fail without internet connectivity.

---

## 3. Server Runtime & Web Server Configuration

### A. Runtime Prerequisites
- **Web Server:** Apache 2.4+ (or Nginx with PHP-FPM).
- **PHP Version:** PHP 7.4+ (tested on PHP 8.3 during audit).
- **Required PHP Extensions:** `pdo_mysql`, `fileinfo`, `mbstring`, `json`, `openssl`.
- **Database:** MySQL 5.7+ or MariaDB 10.4+ with `InnoDB` and `utf8mb4` support.
- **Crucial MySQL Setting:** `max_allowed_packet` must be configured to at least `64M` to handle multi-document uploads.

### B. Apache Configuration (`.htaccess`)
- **Root `.htaccess`:**
  ```apache
  RewriteEngine On
  RewriteCond %{HTTP:Authorization} ^(.*)
  RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]
  ```
  Passes the `Authorization` header to PHP in CGI/FastCGI environments.
- **Directory Protection Gap:**
  `config/.htaccess` protects configuration files (`Require all denied`). However:
  - The `logs/` directory has no `.htaccess` file, leaving `logs/mail.log` (which records password reset links) accessible via web browser.
  - Root `debug.log` has no rewrite rule blocking direct browser downloads.
  - No HTTPS redirect rule or HSTS security headers (`Strict-Transport-Security`) are configured in `.htaccess`.

---

## 4. Environment & Secret Management

- **Configuration Loader:** `config/config.php` contains a custom parser that reads `.env` into environment variables via `putenv()` and `$_ENV`.
- **Limitation:** The custom parser only supports simple `KEY=VALUE` pairs with optional single/double quotes. It does not support multi-line values, variable expansion, or export syntax.
- **Default Credentials:** `README.md` documents default super-administrator credentials and `config/database.sql` seeds an active account. Values are omitted here; deployments must replace/remove the seed credential before use.

---

## 5. Continuous Integration, Deployment & Disaster Recovery

### A. CI/CD Automation
- **Current State:** **0% Automated.** No GitHub Actions, GitLab CI, or deployment scripts exist.
- **Deployment Method:** Entirely manual (manual git pull or FTP/SFTP copy into document root, manual MySQL import).

### B. Observability & Logging
- **Error Tracking:** PHP errors are written to server error logs or local `debug.log`. No centralized exception tracker (e.g. Sentry, Monolog) exists.
- **Audit Logging:** The `audit_logs` table records administrative actions. However, auth failures and system anomalies are not integrated into a SIEM or syslog facility.

### C. Backup & Disaster Recovery
- **Current State:** Non-existent in the repository. No automated script exists for database dumps, BLOB export, or disaster recovery drills.

---

## 6. DevOps Maturity Roadmap Recommendations

1. **Composer Standardization (Phase 1):** Introduce `composer.json` for PSR-4 autoloading, pinning PHPMailer, FPDF, and PHPUnit.
2. **GitHub Actions CI Pipeline (Phase 1):** Create `.github/workflows/ci.yml` running PHP linting, PHPUnit tests, and static analysis on every pull request.
3. **Hardened Web Server Configuration (Phase 1):** Add `.htaccess` denying HTTP access to `logs/` and `*.log`, and enforce HTTPS redirection in production.
4. **Containerization (Phase 10):** Author a production-ready `Dockerfile` and `docker-compose.yml` defining reproducible PHP-FPM, Apache/Nginx, and MySQL 8.4 containers.
5. **Automated Database Backups (Phase 10):** Implement a daily scheduled backup script (`mysqldump` with `--single-transaction`) storing encrypted dumps in off-site storage.
