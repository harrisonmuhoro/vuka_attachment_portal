# Vuka Attachment Portal - Database Setup Guide

## Overview
This document provides complete instructions for setting up and running the Vuka Attachment Portal with MySQL database backend.

## Prerequisites
- Laragon (or XAMPP/WAMP) with PHP 7.4+ and MySQL 5.7+
- MySQL running
- Basic understanding of databases

## Setup Instructions

### Step 1: Create the Database

1. **Open phpMyAdmin** (usually at `http://localhost/phpmyadmin`)
   
2. **Create new database:**
   - Click on "New" button
   - Database name: `vuka_attachment_portal`
   - Collation: `utf8mb4_unicode_ci`
   - Click "Create"

3. **Run the SQL script:**
   - Go to "SQL" tab
   - Copy the contents of `database.sql` file
   - Paste it in the SQL editor
   - Click "Go" to execute

### Step 2: Configure the Application

1. **Verify database credentials in `config.php`:**
   ```php
   DB_HOST = 'localhost'
   DB_USER = 'root'
   DB_PASS = '' (empty for Laragon)
   DB_NAME = 'vuka_attachment_portal'
   ```

2. **Create uploads directory:**
   - Ensure `/www/uploads/` directory exists
   - Set permissions to 755

### Step 3: Set File Permissions

```bash
# Windows Command Prompt (run as Administrator):
cd C:\laragon\www
mkdir uploads
icacls uploads /grant Users:F
```

### Step 4: Start the Server

1. **Start Laragon:**
   - Open Laragon application
   - Click "Start All"
   - Apache and MySQL should show as running

2. **Access the application:**
   - URL: `http://localhost/index.html` or `http://localhost/`
   - The portal should load successfully

## Database Schema Overview

### Tables

1. **users** - Application user accounts
   - id, full_name, national_id (unique), email (unique)
   - password_hash, registration_date
   - verified, verification_code

2. **admin_users** - Admin/reviewer accounts
   - id, pf_number (unique), password_hash
   - department, created_at

3. **submissions** - Application submissions
   - id, user_id (FK), national_id
   - Attachment details (duration, insurance_cover, course_applying, etc.)
   - Status tracking (pending_review, approved, rejected)
   - Review notes and rejection reason

4. **documents** - Uploaded documents
   - id, submission_id (FK)
   - document_type, file_path, mime_type, file_size
   - uploaded_at

5. **review_history** - Review audit trail
   - id, submission_id (FK)
   - reviewer_pf, reviewer_department
   - status, notes, reviewed_at

## API Endpoints

### User Endpoints

#### Register
- **URL:** `/api/register.php`
- **Method:** POST
- **Body:**
  ```json
  {
    "fullName": "John Doe",
    "nationalId": "12345678",
    "email": "john@example.com",
    "password": "password123",
    "registrationDate": "2026-02-04"
  }
  ```

#### Verify Email
- **URL:** `/api/verify-email.php`
- **Method:** POST
- **Body:**
  ```json
  {
    "nationalId": "12345678",
    "code": "123456"
  }
  ```

#### Login
- **URL:** `/api/login.php`
- **Method:** POST
- **Body:**
  ```json
  {
    "nationalId": "12345678",
    "password": "password123"
  }
  ```

#### Submit Application
- **URL:** `/api/submit-application.php`
- **Method:** POST
- **FormData:**
  - userId, duration, insuranceCover, courseApplying
  - institutionName, departmentApplying
  - Files: fileApplicationLetter, fileCampusLetter, fileInsuranceCert, fileAcademic, fileId

### Admin Endpoints

#### Admin Login
- **URL:** `/api/admin-login.php`
- **Method:** POST
- **Body:**
  ```json
  {
    "pfNumber": "PF/ADMIN/001",
    "password": "admin123"
  }
  ```

#### Admin Register
- **URL:** `/api/admin-register.php`
- **Method:** POST
- **Body:**
  ```json
  {
    "secretCode": "Swarovski94@vuka",
    "pfNumber": "PF/2024/001",
    "department": "ICT",
    "password": "password123",
    "passwordConfirm": "password123",
    "allUniqueCode": "AllDepts2026@vuka" (if department=ALL)
  }
  ```

#### Get Submissions
- **URL:** `/api/get-submissions.php?department=ICT`
- **Method:** GET
- **Parameters:** department (ICT, Health, Finance, etc., or ALL)

#### Update Submission Status
- **URL:** `/api/update-submission.php`
- **Method:** POST
- **Body:**
  ```json
  {
    "submissionId": 1,
    "status": "approved",
    "pfNumber": "PF/ADMIN/001",
    "rejectionReason": "",
    "reviewNotes": "Application approved"
  }
  ```

#### Delete Submission
- **URL:** `/api/delete-submission.php`
- **Method:** POST
- **Body:**
  ```json
  {
    "submissionId": 1,
    "pfNumber": "PF/ADMIN/001"
  }
  ```

#### Download Document
- **URL:** `/api/download-document.php?submissionId=1&documentType=application_letter`
- **Method:** GET

## Default Admin Account

**PF Number:** `PF/ADMIN/001`
**Password:** `admin123`
**Department:** `HR`

> **Important:** Change this password in production!

## File Upload Settings

- **Maximum file size:** 2MB
- **Allowed formats:** PDF, JPG, PNG
- **Upload directory:** `/www/uploads/`
- **Files are stored with unique names for security**

## Troubleshooting

### Database Connection Failed
1. Check MySQL is running (Laragon console)
2. Verify credentials in config.php match your setup
3. Ensure database is created

### File Upload Errors
1. Check /uploads directory exists and is writable
2. Verify file size is under 2MB
3. Ensure file format is PDF, JPG, or PNG

### Permission Denied Errors
1. Check file/folder permissions (755 for folders, 644 for files)
2. Ensure PHP has write access to uploads directory

### Database Quota Issues
1. Clear old submissions and documents
2. Reduce file sizes
3. Archive old records

## Security Notes

- Passwords are hashed using bcrypt (cost factor 10)
- File uploads are stored outside web root (recommended)
- SQL queries use prepared statements (prevent SQL injection)
- File MIME types are validated
- Admin operations logged in review_history table
- Sensitive info (secret codes) should be changed in production

## Maintenance

### View Active Submissions
```sql
SELECT s.full_name, s.status, COUNT(d.id) as docs 
FROM submissions s
LEFT JOIN documents d ON s.id = d.submission_id
WHERE s.status = 'pending_review'
GROUP BY s.id;
```

### View Admin Activity
```sql
SELECT rh.reviewer_pf, COUNT(*) as reviews
FROM review_history rh
GROUP BY rh.reviewer_pf
ORDER BY reviews DESC;
```

### Clean Old Files
```sql
DELETE FROM submissions 
WHERE DATE(submitted_at) < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

## Support

For issues or questions, contact:
- Email: admin@vuka.go.ke
- Phone: IT Department

---
**Last Updated:** February 4, 2026
**Version:** 1.0
