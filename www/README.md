# Vuka Attachment Portal
## Complete Backend Setup with MySQL Database

### Overview
This is a comprehensive web application for managing attachment applications for the Vuka. It includes:
- User registration and authentication
- Email verification
- Application form submission with document uploads
- Admin dashboard for review and approval
- Complete database backend with MySQL

### System Requirements
- **Server:** Laragon, XAMPP, or WAMP
- **PHP:** 7.4 or higher
- **MySQL:** 5.7 or higher
- **Browser:** Modern browser (Chrome, Firefox, Edge, Safari)
- **File Storage:** Minimum 500MB for uploads

### Quick Start

#### 1. Automatic Setup (Windows)
```batch
cd C:\laragon\www
setup.bat
```

#### 2. Manual Setup

**Step 1: Create Database**
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Create new database: `vuka_attachment_portal`
3. Go to SQL tab and run `database.sql`

**Step 2: Verify Configuration**
- Check `config.php` has correct database credentials
- Ensure `uploads/` directory exists and is writable

**Step 3: Test the Application**
- Open: `http://localhost/index.html`
- Try registering a new user
- Admin login: `PF/ADMIN/001` / `admin123`

### Project Structure
```
/www/
├── index.html                 # Main application
├── config.php                 # Database configuration
├── database.sql               # Database schema
├── setup.bat                  # Windows setup script
├── DATABASE_SETUP.md          # Setup guide
├── IMPLEMENTATION_GUIDE.md    # Code integration guide
├── README.md                  # This file
├── uploads/                   # Document storage
└── api/
    ├── register.php           # User registration
    ├── verify-email.php       # Email verification
    ├── login.php              # User login
    ├── submit-application.php # Application submission
    ├── admin-login.php        # Admin login
    ├── admin-register.php     # Admin registration
    ├── get-submissions.php    # Fetch submissions
    ├── update-submission.php  # Update status/review
    ├── delete-submission.php  # Delete submission
    └── download-document.php  # Download files
```

### Database Schema

#### Users Table
- Stores applicant information
- Email verification tracking
- Password hashing with bcrypt

#### Admin Users Table
- Admin/reviewer accounts
- Department-based access control
- Multiple admins per department

#### Submissions Table
- Application submissions
- Attachment details (duration, course, institution)
- Status tracking (pending/approved/rejected)
- Review notes and rejection reasons

#### Documents Table
- Uploaded files metadata
- File paths and MIME types
- File size tracking

#### Review History Table
- Audit trail of all status changes
- Reviewer information
- Review notes and timestamps

### User Features

#### Registration
1. Enter full name, ID, email, registration date
2. Create password (min 6 chars)
3. Verify email with code
4. Ready to apply

#### Login
1. Enter National ID and password
2. System checks if already submitted
3. Access application form

#### Application Form
- Pre-filled applicant information
- Attachment details (duration, insurance, course, department)
- Document uploads (max 5 files, 2MB each)
- Mandatory document types:
  - Application Letter
  - Campus Letter of Application
  - Insurance Certificate
  - Academic Certificates & Transcripts
  - National ID Copy

#### File Upload
- Supports PDF, JPG, PNG formats
- Maximum 2MB per file
- Real-time validation
- Secure file storage with unique names

### Admin Features

#### Dashboard Statistics
- Total submissions count
- Pending review count
- Approved count
- Rejected count

#### Submission Management
- Search by name or ID
- Filter by status
- Department-based filtering (if not admin of all depts)
- View detailed submission information

#### Document Review
- Preview uploaded documents
- Download documents for offline review
- View submission details
- View review history

#### Status Management
- Update submission status (pending/approved/rejected)
- Add review notes
- Add rejection reason (required for rejections)
- Automatic email notifications (when configured)
- Audit trail of all changes

#### User Management
- View registered users
- Track submission status
- Filter by department
- Search functionality

### API Endpoints

#### Public Endpoints (No Authentication)

**POST /api/register.php**
```json
{
  "fullName": "John Doe",
  "nationalId": "12345678",
  "email": "john@example.com",
  "password": "password123",
  "registrationDate": "2026-02-04"
}
```
Response: User ID and verification code

**POST /api/verify-email.php**
```json
{
  "nationalId": "12345678",
  "code": "123456"
}
```
Response: Verification confirmation

**POST /api/login.php**
```json
{
  "nationalId": "12345678",
  "password": "password123"
}
```
Response: User details and session token

#### Authenticated Endpoints (Session Required)

**POST /api/submit-application.php** (FormData with files)
- userId, duration, insuranceCover, courseApplying
- institutionName, departmentApplying
- Files: fileApplicationLetter, fileCampusLetter, fileInsuranceCert, fileAcademic, fileId

**POST /api/admin-login.php**
```json
{
  "pfNumber": "PF/ADMIN/001",
  "password": "admin123"
}
```

**POST /api/admin-register.php**
```json
{
  "secretCode": "Swarovski94@vuka",
  "pfNumber": "PF/2024/001",
  "department": "ICT",
  "password": "password123",
  "passwordConfirm": "password123"
}
```

**GET /api/get-submissions.php?department=ICT**
- Fetch all submissions for department
- Use "ALL" for all departments (admin only)

**POST /api/update-submission.php**
```json
{
  "submissionId": 1,
  "status": "approved",
  "pfNumber": "PF/ADMIN/001",
  "rejectionReason": "",
  "reviewNotes": "Application approved"
}
```

**POST /api/delete-submission.php**
```json
{
  "submissionId": 1,
  "pfNumber": "PF/ADMIN/001"
}
```

**GET /api/download-document.php?submissionId=1&documentType=application_letter**
- Downloads specific document file

### Security Features

- **Password Hashing:** bcrypt (cost factor 10)
- **SQL Injection Prevention:** Prepared statements
- **File Validation:** MIME type and extension checking
- **File Size Limits:** 2MB maximum per file
- **Unique File Names:** Prevents directory traversal
- **Access Control:** Department-based admin permissions
- **Audit Trail:** All admin actions logged in review_history
- **Session Management:** Simple session tracking

### Default Credentials

| Type | Username | Password |
|------|----------|----------|
| Admin | PF/ADMIN/001 | admin123 |

**⚠️ IMPORTANT:** Change default admin password in production!

### Configuration

Edit `config.php` to customize:
- Database host, user, password
- Database name
- File upload directory
- Maximum file size
- Allowed file types
- Email settings (SMTP)
- Timezone

### Troubleshooting

#### Database Connection Error
1. Ensure MySQL service is running
2. Check credentials in `config.php`
3. Verify database exists: `CREATE DATABASE vuka_attachment_portal`

#### File Upload Error
1. Check `uploads/` directory exists
2. Verify folder permissions (755)
3. Ensure file is under 2MB
4. Confirm file format is PDF/JPG/PNG

#### Admin Login Error
1. Verify admin exists: Run `setup.bat` again
2. Check PF number spelling
3. Ensure password is correct
4. Try with default: `PF/ADMIN/001` / `admin123`

#### Email Verification Issues
1. In local development, verification code is displayed
2. For production, configure SMTP in `config.php`
3. Check email spam folder

#### Permission Denied Errors
```bash
# Fix directory permissions (Windows CMD as Administrator)
icacls C:\laragon\www\uploads /grant Users:F

# Fix file permissions
icacls C:\laragon\www\*.php /grant Users:R
```

### Performance Optimization

**Database Indexes**
- national_id (users and submissions)
- email (users)
- status (submissions)
- department_applied (submissions)
- submitted_at (submissions)
- submission_id (documents)

**File Management**
- Documents stored outside web root
- Unique filenames prevent conflicts
- Automatic cleanup script available

### Maintenance Tasks

**Regular Backups**
```bash
mysqldump -u root vuka_attachment_portal > backup_date.sql
```

**Database Cleanup** (optional, remove submissions older than 6 months)
```sql
DELETE FROM submissions 
WHERE DATE(submitted_at) < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

**Archive Old Files**
- Move files older than 1 year to archive storage
- Update file_path in documents table

### Production Deployment Checklist

- [ ] Change default admin password
- [ ] Change secret codes (admin registration)
- [ ] Enable HTTPS/SSL
- [ ] Configure SMTP for email notifications
- [ ] Set up database backups
- [ ] Move uploads outside web root
- [ ] Enable file virus scanning
- [ ] Set up log monitoring
- [ ] Configure firewall rules
- [ ] Enable rate limiting
- [ ] Review CORS settings
- [ ] Set strong permissions on config.php
- [ ] Disable debug mode
- [ ] Test all features

### Support & Documentation

- **Setup Guide:** DATABASE_SETUP.md
- **Integration Guide:** IMPLEMENTATION_GUIDE.md
- **API Reference:** See endpoint details above
- **Database Schema:** database.sql

### License
Vuka - Internal Use Only

### Version
Version 1.0 - February 4, 2026

### Support Contact
IT Department - Vuka
Email: admin@vuka.go.ke

---

## Additional Notes

### Session Management
- Sessions are stored in sessionStorage (browser)
- For production, consider server-side sessions
- Token-based authentication available in API response

### Email Configuration
- Default: No email sending in development
- Verification codes displayed in UI
- For production: Configure SMTP in config.php
- Email templates can be customized

### File Storage
- Files stored with unique names for security
- Original filenames preserved in database
- Default location: `/www/uploads/`
- Recommended: Move to `/uploads/` outside web root for production

### Scalability
- Database is normalized for efficient queries
- Ready for multi-server deployment
- Session management can be moved to Redis
- File storage can be moved to cloud (S3, Azure Blob)

### Monitoring
- Review history table tracks all admin actions
- Application logs in browser console
- Server logs in error_log
- Query logs available in MySQL

---

**Last Updated:** February 4, 2026
**Created:** February 4, 2026
**Status:** Production Ready
