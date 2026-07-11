# Vuka Portal — Feature Rollout Progress

Implementation log for the 14-feature roadmap (`VUKA_FEATURE_ROADMAP.md`), delivered in dependency-ordered tiers. All roadmap code was reconciled to the **actual** codebase conventions (real columns `password_hash`, `assigned_station`, `review_history.status`/`reviewed_at`; auth via `require*()` helpers + `role_name`; department scoping by name string; frontend raw `fetch()` + `showToast(...,'error')`; files `*_dashboard.js`).

**Status: Tiers 1–4 delivered (10 of 14 features; #11 pagination deferred). Tier 5 pending.**

Delivery mode: tiered with checkpoints. Email: log-only fallback (writes to `logs/mail.log` until real SMTP creds are set in `config.php`).

---

## Shared infrastructure (built once, reused across tiers)

| File | Purpose |
|---|---|
| `config.php` | Added `APP_URL` + `SMTP_*` constants (blank USER/PASS = log-only mode) |
| `database-features.sql` | Single idempotent migration (all tiers). Includes `vuka_add_col` stored proc for MySQL 8.x column-adds. **Applied & verified live.** |
| `lib/rate-limiter.php` | Failed-login tracking + lockout |
| `lib/mailer.php` | SMTP sender with graceful **log-only fallback** to `logs/mail.log` |
| `lib/email-templates.php` | HTML email templates (reset, status-change, interview) |
| `lib/notifications.php` | `createNotification()` + `notifyDepartmentAdmins()` helpers |

---

## Tier 1 — Security foundation ✅

### #13 Login rate limiting
- `login_attempts` table; `lib/rate-limiter.php` (`checkRateLimit` / `recordLoginAttempt` / `clearLoginAttempts`).
- 5 failed attempts per IP+endpoint within 15 min → HTTP 429 lockout; auto-prunes records >24h.
- Wired into `api/login.php`, `api/student-login.php`, `api/admin-login.php`.

### #9 Password reset flow
- `password_reset_tokens` table (single-use, 1-hour expiry).
- `api/forgot-password.php` — uniform response (never leaks whether an email exists); emails reset link (log-only by default).
- `api/reset-password.php` — validates token, updates the correct table's `password_hash`, burns token.
- `pages/reset-password.php` — standalone reset page.
- "Forgot password?" link + modal on `index.php`; handlers in `index.js`.

---

## Tier 2 — Communication ✅

### #1 Email notifications
- `review_history.email_sent` + `email_sent_at` columns.
- `api/update-submission.php` hooks: on status change to accepted/rejected/deployed/ongoing (+ future interview/completed), emails the student via `emailStatusChanged()` and stamps `email_sent` to prevent duplicate sends.

### #5 In-app notification bell
- `notifications` table; `api/notifications.php` (GET recent + unread count; POST `mark_read`), works for students & admins via `requireAuth()`.
- Bell + unread badge added to all 4 dashboard headers.
- `common.js`: `pollNotifications()` (30s + on tab focus), `toggleNotifDropdown()` (marks read on open, closes on outside click), HTML-escaped rendering, `initNotificationBell()`.
- Status changes also create an in-app notification for the student.

---

## Tier 3 — Student & workflow ✅

### #12 Application withdrawal
- `api/withdraw-application.php` — student-only, ownership-checked, `applied`/`pending` only; logs to `review_history`.
- Per-row Withdraw button in student application history; `withdrawApplication()` in `student_dashboard.js`.
- Added `withdrawn` / `interview` / `completed` cases to `getStatusBadge()`.

### #7 Vacancy application deadlines
- `vacancies.deadline_at` + `positions_filled` columns.
- `api/vacancies.php` — auto-closes expired approved vacancies on read; accepts `deadline_at` on create.
- `api/submit-application.php` — blocks applications to non-approved / past-deadline vacancies.
- Supervisor create-vacancy modal: optional `datetime-local` deadline field.
- Student vacancy cards: live countdown (`initVacancyCountdowns()` in `student_dashboard.js`), red under 2 days / "Deadline passed".

### #8 Dark mode toggle
- Full `[data-theme="dark"]` theme in `common.css` (cards, tables, inputs, tabs, skeletons, borders).
- `common.js`: `toggleDarkMode()` + pre-paint apply + `initThemeToggleIcon()`.
- Pre-paint inline script (no flash) + toggle button wired into all 5 pages (`index.php` + 4 dashboards).
- Preference persisted in `localStorage` (`vuka_theme`).

### #14 Print-friendly view
- `@media print` stylesheet in `common.css` (hides nav/buttons/bell/toasts, forces light theme, prints the open modal cleanly, adds a ref print-header, table borders).
- Print-header + "Print / Save as PDF" button injected into the applicant detail modal; `printApplicantDetail()` in `common.js`.

---

## Schema changes applied (in `database-features.sql`)
- **New tables:** `login_attempts`, `password_reset_tokens`, `notifications`
- **`review_history`:** + `email_sent`, `email_sent_at`
- **`submissions.status`:** widened ENUM → `not_applied, applied, pending, interview, accepted, rejected, deployed, ongoing, completed, withdrawn`
- **`vacancies`:** + `deadline_at`, `positions_filled`
- Helper stored procedure `vuka_add_col(table, col, def)` for idempotent column adds.

---

## Verification
- All PHP files pass `php -l`; all JS files pass `node --check`; `common.css` braces balanced.
- Migration applied to `vuka_attachment_portal` and verified (tables/columns present).
- Live smoke test: `forgot-password.php` returns uniform success and writes to `logs/mail.log`.
- Nothing committed to git yet.

---

## Tier 4 — Admin productivity ✅ (except #11)

### #6 Bulk actions on applications
- `api/bulk-update-submissions.php` — admin-only, department-scoped, transactional; validates ids/status, requires a reason for bulk rejection, writes `review_history` + a per-student in-app notification.
- Frontend in `common.js`: per-row checkboxes + select-all in the HR (`hrApplicantsTable`) and Supervisor (`supervisorApplicantsTable`) tables, floating `bulkActionBar` (Accept / Reject / Export CSV / Cancel), `bulkSelectedIds` Set, and CSV export of selected rows. Refreshes the visible list after a bulk update.

### #10 Advanced analytics
- `api/get-analytics.php` — server-side aggregation scoped by department: `monthly` (12-mo trend), `by_status`, `acceptance` (total + placed per dept), `avg_days` (submission→first decision).
- Each dashboard's existing chart card upgraded to consume this endpoint (replacing the old client-side aggregation over `get-submissions.php`):
  - **Admin** (`admin_dashboard.js`) — doughnut of `by_status` with friendly status labels/colours (`STATUS_META`).
  - **HR** (`hr_dashboard.js`) — grouped bar of `acceptance` (Total applicants vs Placed per department).
  - **Supervisor** (`supervisor_dashboard.js`) — line of `monthly` (auto-scoped to the supervisor's own department server-side).

### #11 Server-side pagination — DEFERRED
- Backend is ready (`get-submissions.php` honours `page`/`per_page`, returns a `pagination` block) and `renderPagination()` exists in `common.js`, but **not wired into any loader**. Deferred because the HR/Supervisor lists filter status/type client-side, which conflicts with server paging — doing it properly needs the filters moved server-side too. Left in place, unused, for a future pass.

## Remaining work
### Tier 5 — Large workflow features
- #2 PDF letter generation (FPDF)
- #3 Interview scheduling module
- #4 Intern performance evaluation
