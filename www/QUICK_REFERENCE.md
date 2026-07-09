# 🚀 QUICK REFERENCE CARD
## Vuka Attachment Portal - Backend Setup

---

## ⚡ IMMEDIATE ACTION ITEMS

### 1. Setup (5 minutes)
```cmd
cd C:\laragon\www
setup.bat
```

### 2. Verify (1 minute)
Open: `http://localhost/verify.php`

### 3. Test (2 minutes)
Open: `http://localhost/index.html`

---

## 📍 KEY FILES LOCATION

| File | Purpose | Location |
|------|---------|----------|
| Main App | HTML Application | `index.html` |
| Database | MySQL Schema | `database.sql` |
| Config | DB Settings | `config.php` |
| API | Backend Endpoints | `/api/*.php` |
| Uploads | User Files | `/uploads/` |

---

## 🔐 DEFAULT LOGIN

**Admin Portal:**
- **URL:** http://localhost/index.html
- **Tab:** Admin
- **PF Number:** `PF/ADMIN/001`
- **Password:** `admin123`

⚠️ **Change immediately in production!**

---

## 🎯 COMMON TASKS

### Add New Admin User
```sql
INSERT INTO admin_users (pf_number, password_hash, department)
VALUES ('PF/2024/001', [hashed_password], 'ICT');
```

### Check Submissions
```sql
SELECT full_name, status, submitted_at FROM submissions ORDER BY submitted_at DESC;
```

### View Recent Changes
```sql
SELECT * FROM review_history ORDER BY reviewed_at DESC LIMIT 10;
```

### Find User
```sql
SELECT * FROM users WHERE national_id = '12345678';
```

---

## 🐛 QUICK TROUBLESHOOTING

| Issue | Solution |
|-------|----------|
| Database won't connect | Check MySQL running, verify config.php |
| Files won't upload | Check uploads/ exists, permissions 755 |
| Admin login fails | Use PF/ADMIN/001 / admin123, or run setup.bat |
| Verification code not showing | Configured for local dev, check browser console |

---

## 📊 DATABASE TABLES

```
users              → Applicants
admin_users        → Reviewers
submissions        → Applications
documents          → Uploaded files
review_history     → Audit trail
```

---

## 🔗 API ENDPOINTS

### Registration Flow
1. `POST /api/register.php` → User account
2. `POST /api/verify-email.php` → Verify account
3. `POST /api/login.php` → Login

### Application Flow
1. `POST /api/submit-application.php` → Submit with files

### Admin Flow
1. `POST /api/admin-login.php` → Admin login
2. `GET /api/get-submissions.php` → View apps
3. `POST /api/update-submission.php` → Review

---

## 📦 FILE FORMATS ACCEPTED

✅ **PDF** - .pdf
✅ **Images** - .jpg, .jpeg, .png
✅ **Max Size** - 2MB per file
✅ **Required** - 5 documents

---

## 🔄 REQUEST/RESPONSE FORMAT

**Request:**
```json
{
    "nationalId": "12345678",
    "password": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "data": { /* endpoint data */ },
    "error": null
}
```

---

## 🛡️ SECURITY CHECKLIST

- [ ] Changed admin password
- [ ] Changed secret codes
- [ ] Disabled debug mode
- [ ] Enabled HTTPS
- [ ] Configured SMTP
- [ ] Set file permissions (755)
- [ ] Backed up database
- [ ] Reviewed CORS settings
- [ ] Tested all endpoints
- [ ] Checked error logs

---

## 📝 DOCUMENTATION

| Doc | Content |
|-----|---------|
| README.md | Full overview |
| DATABASE_SETUP.md | Database guide |
| IMPLEMENTATION_GUIDE.md | Code integration |
| BACKEND_SUMMARY.md | This summary |

---

## 🚨 EMERGENCY RESET

Reset admin password:
```sql
UPDATE admin_users SET password_hash = '$2y$10$9PqFE7RkHn1rVsHSJEv7Se3OwCEP.iRdKplI9VG7nUkpqRuHeB0Za' 
WHERE pf_number = 'PF/ADMIN/001';
```
(Resets to: PF/ADMIN/001 / admin123)

---

## 💾 BACKUP DATABASE

```bash
mysqldump -u root vuka_attachment_portal > backup.sql
```

## 🔄 RESTORE DATABASE

```bash
mysql -u root vuka_attachment_portal < backup.sql
```

---

## 🌐 ACCESS POINTS

| Page | URL |
|------|-----|
| App | http://localhost/index.html |
| Verify | http://localhost/verify.php |
| API | http://localhost/api/*.php |
| PHPMyAdmin | http://localhost/phpmyadmin |

---

## 📞 QUICK SUPPORT

**Setup issues?** → See DATABASE_SETUP.md
**Code issues?** → See IMPLEMENTATION_GUIDE.md
**API issues?** → See README.md (API section)
**System issues?** → Run verify.php

---

## ✅ DEPLOYMENT CHECKLIST

### Pre-Launch
- [ ] Database created
- [ ] All tables verified
- [ ] Admin account working
- [ ] File uploads working
- [ ] Email configured
- [ ] Backups setup

### Launch
- [ ] Test registration flow
- [ ] Test admin functions
- [ ] Verify file uploads
- [ ] Check email notifications
- [ ] Monitor error logs

### Post-Launch
- [ ] Monitor submissions
- [ ] Archive old files monthly
- [ ] Review user activity
- [ ] Backup data weekly
- [ ] Update documentation

---

## 🎯 SUCCESS INDICATORS

✅ setup.bat runs without errors
✅ verify.php shows all green
✅ Can register new user
✅ Can login with credentials
✅ Can submit application
✅ Admin can view submissions
✅ Admin can approve/reject
✅ Files persist in database

---

**Last Updated:** February 4, 2026
**Status:** Ready for Production
**Support:** admin@vuka.go.ke

---
