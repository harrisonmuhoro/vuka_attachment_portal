# Quick Testing Guide

## Start Here

### 1. Start Laragon
- Open Laragon application
- Make sure Apache and MySQL are running (green indicator)
- Access: http://localhost

### 2. Default Admin Account
```
PF Number: PF/ADMIN/001
Password: admin123
```

### 3. Test User Registration
1. Click "Register" tab
2. Fill in details:
   - Full Name: Test User
   - National ID: 12345678
   - Email: test@example.com
   - Password: password123
3. Click Register
4. Enter verification code when prompted (check browser console if email fails)
5. Go to Login tab and login with same credentials

### 4. Test User Submission
1. After login, fill in attachment details
2. Upload all 5 documents:
   - Application Letter (PDF/JPG/PNG)
   - Campus Letter (PDF/JPG/PNG)
   - Insurance Certificate (PDF/JPG/PNG)
   - Academic Certificates (PDF/JPG/PNG)
   - National ID Copy (PDF/JPG/PNG)
3. Accept terms and submit
4. Should see success page

### 5. Test Admin Dashboard
1. Login with admin account
2. Should see submissions table
3. Click "Review" on any submission
4. Test features:
   - Change status (Approved/Rejected/Pending)
   - Add rejection reason (if rejected)
   - Add review notes
   - Delete submission

## File Locations

| File | Location |
|------|----------|
| Main HTML | `c:\laragon\www\index.html` |
| Config | `c:\laragon\www\config.php` |
| Database SQL | `c:\laragon\www\database.sql` |
| API Endpoints | `c:\laragon\www\api\*.php` |

## Database Setup

If database is not set up:
```bash
cd c:\laragon\www
php setup.bat
```

Or manually:
```sql
mysql -u root
create database vuka_attachment_portal;
use vuka_attachment_portal;
source database.sql;
```

## Verify System

Visit: http://localhost/verify.php

Should show:
- ✅ PHP version
- ✅ MySQL connection
- ✅ Database exists
- ✅ All tables created
- ✅ Uploads directory exists

## Common Issues

### "Cannot reach API"
- Make sure Laragon is running
- Check that index.html is in `c:\laragon\www\`
- Verify config.php database settings

### "Database not found"
- Run setup.bat in `c:\laragon\www\`
- Or manually run database.sql

### "Files not uploading"
- Check `c:\laragon\www\uploads\` exists
- Verify file size < 2MB
- Check file type (PDF, JPG, PNG only)

### "Email not sending"
- This is normal for local development
- Verification codes will show in console or alert
- For production, configure SMTP in config.php

## Next Steps After Testing

1. **Configure SMTP Email**
   - Edit `c:\laragon\www\config.php`
   - Add your email service credentials

2. **Set Production URL**
   - Update API paths if deploying to live server

3. **Enable HTTPS**
   - Install SSL certificate
   - Update config.php with HTTPS settings

4. **Add Security Features**
   - Rate limiting
   - CSRF tokens
   - Request logging

---

**Everything is integrated and ready to test!** 🚀
