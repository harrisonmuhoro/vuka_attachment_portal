# Vuka Attachment & Internship Portal

**Vuka** is a full-stack web application for managing student **attachment** and **internship** placements end to end — from a student's application and document upload, through department supervisor review and HR approval, to final deployment and assignment to a workstation. It is built around a strict **4-tier Role-Based Access Control (RBAC)** model with department-level data isolation, Bearer-token sessions, and a full administrative audit trail.

---

## Table of Contents
- [Roles & Access Control](#roles--access-control)
- [Feature Overview](#feature-overview)
  - [Student / Applicant Portal](#student--applicant-portal)
  - [Department Supervisor Portal](#department-supervisor-portal)
  - [HR Coordinator Portal](#hr-coordinator-portal)
  - [System Admin Portal](#system-admin-portal)
  - [Dashboard UX & Analytics](#dashboard-ux--analytics)
- [Application Lifecycle](#application-lifecycle)
- [Security](#security)
- [Technology Stack](#technology-stack)
- [Project Structure](#project-structure)
- [Database Schema](#database-schema)
- [API Reference](#api-reference)
- [Setup & Installation](#setup--installation)
- [Default Credentials](#default-credentials)

---

## Roles & Access Control

The system defines a **4-role hierarchy**. A user's role is stored in the database and **re-verified from the DB on every request** — it is never trusted from the client. Admin sessions expire after 8 hours.

| Level | Role (code name) | Scope | Responsibilities |
|:---:|---|---|---|
| 1 | `super_admin` | System-wide (`ALL`) | Manage staff & student accounts, RBAC, audit log |
| 2 | `hr_coordinator` | System-wide (`ALL`) | Approve vacancies, review all applicants, verify documents |
| 3 | `department_supervisor` | Own department only | Request vacancies, review dept applicants, deploy & assign |
| 4 | `student` | Own records only | Apply, upload documents, track status |

**Department isolation:** `super_admin` and `hr_coordinator` see all departments; a `department_supervisor` is scoped to their own department for every query. Departments seeded by default: **ICT, Health, Procurement, Finance, HR, Education, Agriculture, Water, Transport**.

---

## Feature Overview

### Student / Applicant Portal
- **Self-registration with email verification** — sign up with full name, national ID, and email; a 6-digit verification code confirms the account.
- **Secure login** — password-based authentication issuing a Bearer-token session.
- **Vacancy browsing** — view all HR-approved attachment and internship openings.
- **Live search & filter** — instantly filter vacancies by department or job title as you type.
- **Application submission** — apply to a vacancy with course, institution, duration, and insurance details.
- **Document upload** — attach the required supporting documents (Application Letter, National ID copy, Insurance Certificate, Campus/Introduction Letter) as PDF/JPG/PNG, up to **2 MB each**, validated by MIME type, extension, and size.
- **Real-time status tracking** — a visual **status timeline** (Applied → Under Review → Decision → Deployed) shows exactly where the application stands.
- **Profile sidebar** — displays the student's name and national ID.

### Department Supervisor Portal
- **Vacancy requests** — create new attachment/internship vacancy requests for their department (title, description, skills required, number of positions, type). Requests start as `pending` and await HR approval.
- **Applicant review** — view all applicants who applied to the supervisor's department, filtered by **type** (attachment/internship) and **status**.
- **Applicant detail view** — inspect a candidate's full application and submitted documents.
- **Accept / reject** — advance or decline applicants.
- **Deploy & assign** — for accepted candidates, assign a **role/job description** and a **workstation/office**, moving them to `deployed`, then `ongoing`.
- **Line-chart analytics** — "Applications Over Time" visualization.
- **CSV export** — download the department's applicant list.

### HR Coordinator Portal
- **Vacancy approval workflow** — review supervisor-submitted vacancy requests and **approve, reject, or close** them (only HR and Super Admin can change vacancy status).
- **Cross-department applicant review** — review and update the status of applicants across all departments.
- **Document verification** — preview and download applicant documents for verification.
- **Placement tracking** — monitor selected and placed candidates.
- **Bar-chart analytics** — applicants per department.
- **CSV export** — download the applicant list.

### System Admin Portal
- **Staff account management** — create department admin accounts (HR coordinators / department supervisors), with a limit of **2 admins per department**.
- **Account lifecycle** — activate, deactivate, or toggle the status of any admin account.
- **Student account management** — view all registered student accounts.
- **Audit log** — review a compliance trail of administrative actions.
- **Doughnut-chart analytics** — submissions by status (Pending / Approved / Rejected).
- **System-wide statistics** — registered users, staff accounts, and system status at a glance.
- **CSV export** — download staff account data.

### Dashboard UX & Analytics
Every dashboard is designed to look and feel production-grade:
- **Chart.js analytics** — role-specific charts (doughnut / bar / line) on the Admin, HR, and Supervisor dashboards.
- **Skeleton loaders** — animated shimmer placeholders while data loads, instead of plain "Loading…" text.
- **Intentional empty states** — iconographic "no data yet" panels rather than blank space.
- **Color-accented stat cards** — left-border accent colors (green/clay/amber/blue) to signal card meaning.
- **CSV export** — one-click table export on Admin, HR, and Supervisor dashboards.
- **Micro-interactions** — hover lift on vacancy cards and smooth fade/slide view transitions.
- **Toast notifications** — non-blocking success/error feedback.
- **Responsive layout** — Bootstrap 5 grid, mobile-friendly tables and navigation.

---

## Application Lifecycle

A submission moves through the following statuses (`submissions.status`):

```
applied → pending → accepted → deployed → ongoing
                 ↘ rejected
```

- **applied / pending** — submitted, awaiting review.
- **accepted** — approved by the department; eligible for deployment.
- **rejected** — declined (with optional rejection reason and review notes).
- **deployed** — assigned a role and workstation by the supervisor.
- **ongoing** — attachment/internship actively in progress.

A parallel `placement_status` (`pending → shortlisted → selected → placed` / `rejected`) tracks the placement pipeline, and every status change is recorded in the **review history** table with the reviewer's PF number, department, and notes.

---

## Security

- **Password hashing** — bcrypt (`PASSWORD_BCRYPT`, cost 10).
- **Bearer-token sessions** — tokens stored server-side in a `sessions` table; role and validity re-checked from the database on every request; 8-hour expiry.
- **Server-side RBAC guards** — `requireSuperAdmin()`, `requireHR()`, `requireSupervisor()`, `requireStudent()`, `requireAnyAdmin()` enforce access per endpoint.
- **Department scoping** — supervisors are automatically confined to their own department's data.
- **Audit logging** — administrative actions and auth events are written to an audit log.
- **Strict file-upload validation** — MIME type, extension, and 2 MB size limits; randomized, sanitized filenames.
- **Security headers** — `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, and a scoped CORS policy.
- **Disabled public admin registration** — `admin-register.php` is hard-blocked (HTTP 403); admins can only be created by a Super Admin.
- **Authenticated, department-scoped document access** — `download-document.php` requires an admin-tier session and only serves documents belonging to the requester's department (super_admin/HR see all).
- **Guarded department lookups** — `get-departments.php` requires an admin-tier session.

> **Hardening note:** `submit-application.php` intentionally keeps a public/form-based code path so unauthenticated applicants can submit. Change the default Super Admin credentials immediately after first login, and serve the app over HTTPS in production (session cookies are flagged `Secure`).

---

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP 7.4+ (PDO, prepared statements) |
| Database | MySQL 5.7+ / MariaDB (InnoDB, utf8mb4) |
| Frontend | Vanilla JavaScript (modular, per-page), HTML5 |
| UI framework | Bootstrap 5.3 |
| Charts | Chart.js |
| Icons | Font Awesome 6.4 |
| Fonts | Plus Jakarta Sans, DM Sans (Google Fonts) |
| Auth | Bearer-token sessions (DB-backed) |
| Server | Apache (WAMP/XAMPP/Laragon), `.htaccess` for `Authorization` header pass-through |

---

## Project Structure

```
/
├── index.php                     # Entry point — login & registration (all roles)
├── config.php                    # DB connection, upload rules, security helpers
├── session-manager.php           # Session creation/validation, RBAC guards, dept scoping
├── audit-logger.php              # Administrative audit logging
├── database.sql                  # Source-of-truth schema + seed data
├── database-migration.sql        # Incremental schema migrations (RBAC updates)
├── .htaccess                     # Apache Authorization header pass-through
│
├── api/                          # REST-like PHP endpoints (see API Reference)
│
├── pages/                        # Role-specific dashboards
│   ├── admin_dashboard.php       # Super Admin
│   ├── hr_dashboard.php          # HR Coordinator
│   ├── supervisor_dashboard.php  # Department Supervisor
│   └── student_dashboard.php     # Student / Applicant
│
├── assets/
│   ├── js/                       # common.js (shared) + one module per dashboard
│   └── css/                      # common.css + per-page stylesheets
│
├── uploads/                      # Secure storage for uploaded documents
│   ├── application_letter/
│   ├── national_id_copy/
│   ├── insurance_cert/
│   └── campus_letter/
│
└── logo/                         # Branding assets
```

---

## Database Schema

Key tables (see `database.sql` for the full definition):

| Table | Purpose |
|---|---|
| `roles` | RBAC role definitions (super_admin, hr_coordinator, department_supervisor, student) |
| `departments` | Organizational departments with per-department admin limits |
| `users` | Student/applicant accounts (with email verification fields) |
| `admin_users` | Admin accounts (PF number, role, department) |
| `vacancies` | Attachment/internship openings (created by supervisors, approved by HR) |
| `submissions` | Applications with full status & placement lifecycle |
| `documents` | Uploaded supporting files linked to submissions |
| `sessions` | Server-side Bearer-token session store |
| `review_history` | Audit trail of every submission status change |
| `audit_log` | Administrative action log |
| `admin_creation_history` | Record of who created which admin account |

A stored procedure, `sp_get_submission_stats(dept)`, returns aggregate submission counts (total / pending / approved / rejected), scoped by department or `ALL`.

---

## API Reference

All endpoints live under `/api/`. Authenticated requests send `Authorization: Bearer <token>`.

### Authentication & Session
| Endpoint | Method | Purpose | Auth |
|---|:---:|---|---|
| `login.php` | POST | Universal login entry | Public |
| `student-login.php` | POST | Student authentication (forces `student` role) | Public |
| `admin-login.php` | POST | Admin login by PF number + password | Public |
| `logout.php` | POST | Invalidate current session | Authenticated |
| `verify-session.php` | GET | Validate session, return role | Authenticated |
| `verify-email.php` | POST | Confirm registration email | Public |

### Student / Applicant
| Endpoint | Method | Purpose | Auth |
|---|:---:|---|---|
| `register.php` | POST | Student self-registration | Public |
| `submit-application.php` | POST | Submit application + document uploads | Form flow |
| `get-user-info.php` | GET | Authenticated user's own profile | Authenticated |
| `get-user-submissions.php` | GET | The student's own submissions | Student |

### Vacancies
| Endpoint | Method | Purpose | Auth |
|---|:---:|---|---|
| `vacancies.php` | GET | List vacancies (defaults to approved) | Public |
| `vacancies.php` | POST | `action=create` / `action=update_status` | Admin tier; status change: HR / Super Admin |

### Submissions & Review
| Endpoint | Method | Purpose | Auth |
|---|:---:|---|---|
| `get-submissions.php` | GET | List/one submission (dept-scoped for admins) | Admin (scoped) |
| `update-submission.php` | POST | Change status / add review notes | Admin tier |
| `delete-submission.php` | POST | Delete a submission (dept-scoped) | Admin tier |
| `download-document.php` | GET | Stream/view an uploaded document (dept-scoped) | Admin tier |

### Admin / RBAC Management (Super Admin)
| Endpoint | Method | Purpose |
|---|:---:|---|
| `create-admin-account.php` | POST | Create a department admin (max 2/dept) |
| `activate-admin-account.php` | POST | Activate a pending admin |
| `deactivate-admin-account.php` | POST | Deactivate an admin |
| `toggle-admin-status.php` | POST | Toggle admin active status |
| `get-admin-accounts.php` / `get-admins.php` | GET | List admin accounts |
| `get-users.php` | GET | List student accounts |
| `admin-register.php` | — | Disabled (HTTP 403) |

### Audit, Stats & Utility
| Endpoint | Method | Purpose | Auth |
|---|:---:|---|---|
| `get-audit-log.php` | GET | Audit trail (dept-scoped for supervisors) | Admin tier |
| `get-dashboard-stats.php` | GET | Role-based dashboard statistics | Authenticated |
| `get-departments.php` | GET | Department list with admin counts | Admin tier |

---

## Setup & Installation

### Requirements
- **Server:** Apache (WAMP, XAMPP, or Laragon for local development)
- **PHP:** 7.4 or higher (PDO MySQL extension enabled)
- **Database:** MySQL 5.7+ / MariaDB
- **Browser:** Any modern browser (Chrome, Firefox, Safari, Edge)

### 1. Database
1. Create a database named `vuka_attachment_portal`.
2. Import **`database.sql`** — this builds the full schema and seeds roles, departments, and the default Super Admin.
3. If upgrading an existing install, also import **`database-migration.sql`**.

### 2. Configuration
Edit `config.php` and set your database credentials if they differ from the defaults:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'vuka_attachment_portal');
define('DB_PORT', 3306);
```

### 3. Environment
1. Ensure the `uploads/` directory exists and is writable by the web server.
2. Keep the included `.htaccess` in place so Apache passes the `Authorization` header to the API.

### 4. Run
- Open `http://localhost/attachment/index.php`.
- Register a student account to test the applicant flow, or log in as the Super Admin to configure staff accounts.

---

## Default Credentials

A default Super Admin is seeded on install:

| Field | Value |
|---|---|
| PF Number | `SUPER/ADMIN/001` |
| National ID | `12345678` |
| Password | `admin123` |

> ⚠️ **Change these immediately after first login.** The default credentials are public knowledge and must not be used in any live environment.
