# Frontend and User Experience Assessment

**Audit Scope:** Client-side HTML/PHP views, CSS styling, vanilla JavaScript modules, state handling, and browser-to-API integration at commit `83aa34f`.

---

## 1. UI Inventory & Page Structure

The presentation tier is organized into entry pages and role-specific dashboards with shared assets:

| View / Component | File Path | Script Dependencies | Purpose & Capabilities |
|---|---|---|---|
| **Universal Portal Entry** | `index.php` | `assets/js/common.js`, `assets/js/index.js` | Landing view, unified login modal (National ID or PF number), student registration modal, email verification modal, and public vacancy card browsing. |
| **Student Dashboard** | `pages/student_dashboard.php` | `assets/js/common.js`, `assets/js/student_dashboard.js`, `assets/js/profile.js` | Multi-view layout: (1) Application Status Timeline, (2) Vacancy Catalog with live search and filter, (3) Application Submission Form with document file upload inputs, (4) Scheduled Interviews, and (5) Student Profile view. |
| **Department Supervisor Dashboard** | `pages/supervisor_dashboard.php` | `assets/js/common.js`, `assets/js/supervisor_dashboard.js`, `assets/js/evaluations.js`, `assets/js/interviews.js`, `assets/js/profile.js` | (1) Department KPI Stat Cards, (2) Vacancy Request Form & Management, (3) Applications Table filtered by status/type, (4) Applicant Detail Modal with decision actions, (5) Workstation Deployment Modal, (6) Intern Evaluations, (7) Chart.js Applications-over-time trend, and (8) CSV export. |
| **HR Coordinator Dashboard** | `pages/hr_dashboard.php` | `assets/js/common.js`, `assets/js/hr_dashboard.js`, `assets/js/profile.js` | (1) Cross-department Applicant Review Table, (2) Vacancy Approval/Rejection Queue, (3) Department Breakdown Bar Chart, (4) Document Verification modal, and (5) CSV export. |
| **Super Admin Dashboard** | `pages/admin_dashboard.php` | `assets/js/common.js`, `assets/js/admin_dashboard.js`, `assets/js/profile.js` | (1) System-wide KPI summary, (2) Staff Account Management (Create Department Admin, Activate, Deactivate, Toggle status), (3) Student Accounts Table, (4) Audit Log viewer, (5) Application Status Doughnut Chart, and (6) CSV export. |
| **Password Reset Page** | `pages/reset-password.php` | Inline JavaScript | Public form validating token parameter and submitting new password to `/api/reset-password.php`. |
| **Shared Profile View** | `pages/components/profile_view.php` | `assets/js/profile.js` | Embedded partial allowing photo upload, phone update, and 6-digit email change verification flow. |

---

## 2. JavaScript Architecture & Maintainability

### A. The Monolithic `common.js` Pattern
`assets/js/common.js` contains **1,509 lines** of tightly coupled logic. Rather than serving purely as a shared utility library, it houses:
- Global state management (`currentUser`, `currentRole`, `authToken`).
- Toast notification and loading overlay implementations.
- Dark/light mode theme toggle logic.
- Senior Admin panel switching and staff manipulation.
- Multi-portal dashboard rendering logic.
- Application submission handling and multipart file encoding.
- Applicant detail modal rendering with status transition buttons.
- Document preview triggers.
- Reusable pagination calculations.
- Notification polling loops.

**Impact:** Changes made to support the Supervisor flow frequently risk unintended side effects on the HR or Student dashboards. Conversely, role-specific scripts such as `hr_dashboard.js` (64 lines) and `supervisor_dashboard.js` (67 lines) are hollow wrappers that configure charts and filters while delegating core business operations back into `common.js`.

### B. Inline DOM Generation & XSS Risks
Across the frontend scripts, tables, cards, and modal bodies are constructed via template literals concatenated into `.innerHTML`:
```javascript
// Example pattern in common.js & admin_dashboard.js
row.innerHTML = `
    <td><strong>${app.full_name}</strong></td>
    <td>${app.institution_name}</td>
    <td>${app.course_applying}</td>
    <td><span class="badge ${badgeClass}">${app.status}</span></td>
`;
```
- **Finding:** No HTML sanitization function (`escapeHtml`) is applied to user-controlled values before DOM injection. If an applicant registers with special characters (`<img src=x onerror=...>`) in their institution or course name, the payload executes when an administrator inspects their application.

### C. Client-Side State & Session Synchronization
- The frontend stores the Bearer token in `localStorage.getItem('token')` or `sessionStorage` and attaches it to request headers (`Authorization: Bearer <token>`).
- If a session expires on the server (after 8 hours), several AJAX calls fail silently or show generic network errors before `verify-session.php` redirects to `index.php`.
- Inconsistent redirect paths exist between the root (`/index.php`) and subfolder paths (`/pages/admin_dashboard.php`), causing intermittent 404s when navigating behind reverse proxies or virtual directories.

---

## 3. UI/UX Strengths Observed

1. **Design System & Typography:**
   - Consistent use of modern typography (Plus Jakarta Sans and DM Sans) loaded via Google Fonts.
   - Clean color palette adhering to professional administrative themes (Deep Slate `#1e293b`, Forest Emerald `#059669`, Warm Amber `#d97706`, and Crimson Rose `#dc2626`).
2. **Visual Feedback & Loading States:**
   - Animated CSS shimmer skeleton loaders during data fetch operations prevent jarring content flashes.
   - Centralized non-blocking toast notifications (`showToast()`) provide clear visual feedback for success and error conditions.
3. **Empty States & Guided Workflows:**
   - Tables and vacancy cards implement intentional empty-state graphics and helpful messaging ("No vacancies match your criteria") rather than leaving blank spaces.
4. **Data Visualizations:**
   - Responsive Chart.js charts on administrative dashboards (Status Distribution Doughnut, Applications Over Time Line Chart, Department Breakdown Bar Chart) with custom tooltips.
5. **Theme Support:**
   - Built-in Dark Mode toggle with persistent cookie storage (`vuka_theme=dark|light`) and CSS variable overrides.

---

## 4. Gaps, Disconnections & Visual-Only Features

| Feature | Visual Presentation | Backend Reality / Defect |
|---|---|---|
| **EmailJS Initialization** | `emailjs.init('MBLFEPb0p8Uh5gS_V')` present in `common.js` | Hardcoded public key. Browser-based email sending is redundant with backend transactional mailer (`lib/mailer.php`). |
| **Pagination Controls** | Pagination pills present in `common.js` | Most API endpoints return all matching rows without SQL `LIMIT`/`OFFSET` unless explicit `?page=` query parameter is supplied. Frontend pagination slices in-memory arrays. |
| **Document Preview** | "Preview" button in document list | Only downloads binary file via `api/download-document.php`. Previews depend on native browser handling of MIME types. |
| **Workstation Deployment** | Inputs for "Assigned Role" and "Workstation" | Functional in `supervisor_dashboard.js`, but no validation exists to ensure workstation strings correspond to physical municipal locations. |
| **Password Change Form** | Standard modal in `pages/components/profile_view.php` | Fails on submit because backend endpoint `api/change-password.php` queries nonexistent `password` column instead of `password_hash`. |

---

## 5. Responsive Design & Accessibility Assessment

- **Grid Framework:** Built on Bootstrap 5.3 grid utilities (`col-lg-`, `col-md-`, `col-12`), providing responsive layouts for desktop and tablet screens.
- **Mobile Tables:** Tables use `.table-responsive` wrappers. However, wide tables (e.g. Submissions Table with 10+ columns) require substantial horizontal scrolling on mobile viewports.
- **Accessibility (a11y) Gaps:**
  - Form inputs in modals occasionally lack explicit `<label for="...">` associations.
  - Interactive status badges and action icons lack `aria-label` attributes for screen readers.
  - Color contrast ratios in Dark Mode for muted text (`--c-slate`) fall slightly below WCAG 2.1 AA standards (3.8:1 vs required 4.5:1).
