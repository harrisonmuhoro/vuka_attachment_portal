# Backend Integration Guide

## Overview
This guide explains how the HTML application connects to the PHP/MySQL backend.

## Key Changes Required

### 1. Update JavaScript API Calls

Replace localStorage operations with API calls. Example:

**Before (localStorage):**
```javascript
localStorage.setItem('vuka_user_' + idNumber, JSON.stringify(userData));
```

**After (PHP API):**
```javascript
fetch('/api/register.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        fullName: fullName,
        nationalId: nationalId,
        email: email,
        password: password,
        registrationDate: registrationDate
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        // Handle success
    } else {
        alert(data.error);
    }
});
```

## Implementation Steps

### Step 1: Update Registration Handler

**Location:** JavaScript `registrationForm` event listener

Replace the localStorage logic with:
```javascript
registrationForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        fullName: document.getElementById('regFullName').value,
        nationalId: document.getElementById('regIdNumber').value,
        email: document.getElementById('regEmail').value,
        password: document.getElementById('regPassword').value,
        registrationDate: document.getElementById('regLoginDate').value
    };
    
    try {
        const response = await fetch('/api/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Store in session for verification
            sessionStorage.setItem('pendingVerifyUser', formData.nationalId);
            
            const verificationCode = result.data.verificationCode || '';
            if (verificationCode) {
                document.getElementById('verifyFeedback').style.display = 'block';
                document.getElementById('verifyFeedback').textContent = 'Verification code: ' + verificationCode;
            }
            
            openVerificationModalForUser(formData.nationalId);
            registrationForm.reset();
            registrationForm.classList.remove('was-validated');
        } else {
            alert('Registration error: ' + result.error);
        }
    } catch (error) {
        alert('Network error: ' + error.message);
    }
});
```

### Step 2: Update Login Handler

Replace the localStorage login with API call:
```javascript
loginForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const idNumber = document.getElementById('loginId').value.trim();
    const password = document.getElementById('loginPassword').value;
    
    try {
        const response = await fetch('/api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nationalId: idNumber,
                password: password
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Store user session
            sessionStorage.setItem('currentUser', idNumber);
            sessionStorage.setItem('userId', result.data.userId);
            
            // Populate form
            document.getElementById('displayFullName').value = result.data.fullName;
            document.getElementById('displayId').value = result.data.nationalId;
            document.getElementById('displayEmail').value = result.data.email;
            document.getElementById('displayRegDate').value = result.data.registrationDate;
            
            switchView('dashboard');
            loginForm.reset();
            loginForm.classList.remove('was-validated');
        } else if (result.data && result.data.alreadySubmitted) {
            document.getElementById('blockedIdDisplay').textContent = idNumber;
            switchView('blocked');
        } else {
            alert(result.error || 'Login failed');
        }
    } catch (error) {
        alert('Network error: ' + error.message);
    }
});
```

### Step 3: Update Application Submission Handler

Replace with FormData for file uploads:
```javascript
uploadForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Validate form...
    if (!uploadForm.checkValidity()) {
        e.stopPropagation();
        uploadForm.classList.add('was-validated');
        return;
    }
    
    // Show confirmation modal first
    confirmModal.show();
});

// In the confirmation submission handler:
confirmSubmitBtn.addEventListener('click', async function(e) {
    e.preventDefault();
    
    try {
        const userId = sessionStorage.getItem('userId');
        if (!userId) {
            alert('User session lost');
            return;
        }
        
        // Create FormData for files
        const formData = new FormData();
        formData.append('userId', userId);
        formData.append('duration', document.getElementById('attachmentDuration').value);
        formData.append('insuranceCover', document.getElementById('insuranceCover').value);
        formData.append('courseApplying', getCourseValue());
        formData.append('institutionName', getInstitutionValue());
        formData.append('departmentApplying', getDepartmentValue());
        
        // Add files
        const fileInputs = {
            'fileApplicationLetter': 'fileApplicationLetter',
            'fileCampusLetter': 'fileCampusLetter',
            'fileInsuranceCert': 'fileInsuranceCert',
            'fileAcademic': 'fileAcademic',
            'fileId': 'fileId'
        };
        
        for (let [inputId, formKey] of Object.entries(fileInputs)) {
            const input = document.getElementById(inputId);
            if (input.files[0]) {
                formData.append(formKey, input.files[0]);
            }
        }
        
        // Submit to API
        const response = await fetch('/api/submit-application.php', {
            method: 'POST',
            body: formData  // Don't set Content-Type for FormData
        });
        
        const result = await response.json();
        
        if (result.success) {
            confirmModal.hide();
            uploadForm.reset();
            uploadForm.classList.remove('was-validated');
            sessionStorage.clear();
            switchView('success');
        } else {
            alert('Submission error: ' + result.error);
        }
    } catch (error) {
        alert('Network error: ' + error.message);
    }
});
```

### Step 4: Update Admin Functions

Replace localStorage admin operations:
```javascript
adminLoginForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const pfNumber = document.getElementById('adminPFNumber').value.trim();
    const password = document.getElementById('adminPassword').value;
    
    try {
        const response = await fetch('/api/admin-login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                pfNumber: pfNumber,
                password: password
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            sessionStorage.setItem('adminLoggedIn', 'true');
            sessionStorage.setItem('adminPF', result.data.pfNumber);
            sessionStorage.setItem('adminDept', result.data.department);
            
            document.getElementById('adminPFDisplay').textContent = pfNumber;
            switchView('admin');
            loadAdminDashboard();
            adminLoginForm.reset();
        } else {
            alert(result.error || 'Login failed');
        }
    } catch (error) {
        alert('Network error: ' + error.message);
    }
});
```

### Step 5: Update Dashboard Loading

```javascript
function loadAdminDashboard() {
    const adminDept = sessionStorage.getItem('adminDept') || 'ALL';
    
    fetch('/api/get-submissions.php?department=' + encodeURIComponent(adminDept))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentSubmissions = data.data.submissions;
                updateAdminStats(currentSubmissions);
                displaySubmissions(currentSubmissions);
            } else {
                alert('Error loading submissions: ' + data.error);
            }
        })
        .catch(error => alert('Network error: ' + error.message));
}
```

### Step 6: Update Status Update Handler

```javascript
updateStatusBtn.addEventListener('click', async function() {
    if (!currentViewingSubmissionId) return;
    
    const newStatus = document.getElementById('statusDropdown').value;
    const rejectionReason = document.getElementById('rejectionReason').value;
    const reviewNotes = document.getElementById('reviewNotes').value;
    
    try {
        const response = await fetch('/api/update-submission.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                submissionId: currentViewingSubmissionId,
                status: newStatus,
                rejectionReason: rejectionReason,
                reviewNotes: reviewNotes,
                pfNumber: sessionStorage.getItem('adminPF')
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Status updated successfully');
            detailsModal.hide();
            loadAdminDashboard();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        alert('Network error: ' + error.message);
    }
});
```

## Testing Checklist

- [ ] Database created and populated
- [ ] config.php credentials correct
- [ ] uploads/ directory exists and writable
- [ ] User registration works
- [ ] Email verification works
- [ ] User login works
- [ ] Application submission works
- [ ] Files upload correctly
- [ ] Admin login works
- [ ] Admin can view submissions
- [ ] Admin can update status
- [ ] Status changes save to database
- [ ] Files can be downloaded

## Common Issues

### 405 Method Not Allowed
- Ensure POST endpoints use POST, GET use GET
- Check .htaccess if using Apache redirects

### 500 Internal Server Error
- Check PHP error logs
- Verify database connection
- Check file permissions

### File Upload Fails
- Verify uploads/ directory exists
- Check file size (max 2MB)
- Check file format (PDF/JPG/PNG)

### Database Errors
- Run database.sql script
- Verify table structure
- Check MySQL is running

## Production Deployment

1. **Security hardening:**
   - Change default admin password
   - Change secret codes
   - Implement proper CORS headers
   - Add rate limiting
   - Enable HTTPS only

2. **Database optimization:**
   - Add indexes for frequently queried columns
   - Archive old submissions
   - Set up regular backups

3. **File management:**
   - Move uploads outside web root
   - Implement virus scanning
   - Set up file retention policy
   - Enable access logging

4. **Email configuration:**
   - Set up proper SMTP
   - Configure email templates
   - Add email queueing for notifications

---
For more details, see DATABASE_SETUP.md
