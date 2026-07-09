# Vuka Attachment Portal

Welcome to the **Vuka Attachment Portal**, a comprehensive web application for managing student attachment applications. This platform facilitates seamless communication between students applying for attachments and the HR/Supervisor administrative teams responsible for managing them.

## Key Features

### Role-Based Access Control (RBAC)
The application implements strict role-based access for different types of administrative users:
1. **System Admin:** High-level access to manage user accounts, roles, and system-wide settings.
2. **HR Admin:** Reviews applications, manages onboarding, and verifies applicant documentation.
3. **Department Supervisor:** Requests vacancies, reviews applicants assigned to their department, and manages ongoing attachments.
4. **Student/Applicant:** Submits applications, uploads mandatory documents, and tracks their application status.

### Student Portal
- **Secure Registration & Login:** Students can register with email verification and secure login.
- **Application Submission:** Easy-to-use form to submit attachment details (duration, institution, department).
- **Document Management:** Supports uploading critical documents (PDF, JPG, PNG) such as Application Letters, ID Copies, Insurance Certificates, and Transcripts (max 2MB per file).
- **Real-Time Status Tracking:** Students can see whether their application is pending, approved, or rejected.

### Administrative Features
- **Dashboard Analytics:** Visual statistics showing total submissions, pending reviews, approvals, and rejections.
- **Vacancy Management:** Supervisors can create requests for new attachment vacancies in their departments.
- **Document Verification:** HR can preview, download, and review applicant documents securely.
- **Applicant Assignment:** Approved students can be deployed and assigned to specific roles and stations within a department.

## System Requirements
- **Server:** Apache/Nginx (e.g., WAMP, XAMPP, or Laragon for local development)
- **PHP:** 7.4 or higher
- **Database:** MySQL 5.7 or higher
- **Browser:** Any modern web browser (Chrome, Firefox, Safari, Edge)

## Project Structure
```
/
├── assets/                     # Modular JS, CSS, and Image files
│   ├── js/                     # Page-specific and shared Javascript
│   └── css/                    # Page-specific and shared CSS
├── pages/                      # Role-specific dashboard views
│   ├── admin_dashboard.php     # System Admin view
│   ├── hr_dashboard.php        # HR view
│   ├── student_dashboard.php   # Student view
│   └── supervisor_dashboard.php# Department Supervisor view
├── api/                        # PHP Backend endpoints (REST-like)
├── uploads/                    # Secure storage directory for uploaded documents
├── index.php                   # Entry point (Login & Registration)
├── config.php                  # Database connection and configuration
├── database.sql                # Original database schema
└── database-migration.sql      # Database migrations (e.g., RBAC schema updates)
```

## Setup Instructions

### 1. Database Configuration
1. Open your database manager (e.g., phpMyAdmin) and create a database named `vuka_attachment_portal`.
2. Import the `database.sql` file.
3. If necessary, import `database-migration.sql` to apply the latest schema updates for RBAC features.
4. Open `config.php` and update the database credentials (DB_HOST, DB_USER, DB_PASS, DB_NAME) if they differ from the defaults.

### 2. Environment Setup
1. Ensure the `uploads/` directory exists in the root folder and has the correct write permissions.
2. The server must support URL rewriting if applicable (an `.htaccess` file is included for Apache environments to ensure `Authorization` headers are passed correctly).

### 3. Testing the Application
- Access the portal at `http://localhost/attachment/index.php`.
- Register a new student account to test the application flow.
- Login with an admin account to access the administrative dashboards.

## Security
- Passwords are securely hashed using bcrypt.
- API endpoints are protected using Bearer Tokens (JWT style session tokens).
- File uploads are validated strictly by MIME type, extension, and file size to prevent malicious uploads.
