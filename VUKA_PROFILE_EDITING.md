# Vuka Portal — Profile Editing Feature

> Implementation guide for role-aware profile editing across all four user tiers.
> Covers the security model, database changes, API endpoints, and frontend UI for each role.

---

## Table of Contents
1. [Core Design Rule](#1-core-design-rule)
2. [What Each Role Can Edit](#2-what-each-role-can-edit)
3. [The Email Change Trap](#3-the-email-change-trap)
4. [Database Changes](#4-database-changes)
5. [API Endpoints](#5-api-endpoints)
6. [Frontend Implementation](#6-frontend-implementation)
7. [Password Change Flow](#7-password-change-flow)
8. [Profile Photo Upload](#8-profile-photo-upload)

---

## 1. Core Design Rule

**Identity fields are immutable. Preference and contact fields are editable.**

This is not a UX decision — it is a security constraint. The moment you allow a student
to change their National ID, they can detach from their submission history, uploaded
documents, and audit trail. That is a fraud vector, not a feature.

The same applies to PF Numbers on admin accounts. PF Numbers are institutional
identifiers assigned externally by HR. They are not user preferences. A supervisor
who changes their PF Number is effectively impersonating a different employee.

**The rule in one sentence:** if a field was assigned to a user by an external authority
(the institution, the HR department, the government), it is immutable in this system.
If the user themselves provided it as a communication preference, it is editable.

---

## 2. What Each Role Can Edit

### Student (`users` table)

| Field | Editable | Reason |
|-------|----------|--------|
| `full_name` | ✅ Yes | Typos happen at self-registration |
| `email` | ✅ Yes | With re-verification to new address + alert to old |
| `phone` | ✅ Yes | Contact detail, user-owned |
| `password` | ✅ Yes | Via current password confirmation gate |
| `profile_photo` | ✅ Optional | UX nicety, portfolio value |
| `national_id` | ❌ No | Government-issued identity anchor |
| `role` | ❌ No | System-assigned |
| `is_verified` | ❌ No | System-managed |

### Department Supervisor & HR Coordinator (`admin_users` table)

| Field | Editable | Reason |
|-------|----------|--------|
| `email` | ✅ Yes | With re-verification |
| `phone` | ✅ Yes | Contact detail |
| `password` | ✅ Yes | Via current password confirmation |
| `profile_photo` | ✅ Optional | |
| `pf_number` | ❌ No | Institutional identifier, externally assigned |
| `department_id` | ❌ No | Assigned by Super Admin only |
| `role` | ❌ No | Assigned by Super Admin only |
| `is_active` | ❌ No | Managed by Super Admin only |

### Super Admin (`admin_users` table, role = `super_admin`)

Same as Supervisor/HR above. The Super Admin **cannot self-edit** their own role
or PF Number. Changing those fields requires a second Super Admin account —
this is a deliberate constraint that prevents privilege escalation and accidental
self-lockout.

---

## 3. The Email Change Trap

Most developers make a mistake here. Two implementations to **never build**:

**Wrong approach A:** Update the email column immediately on form submit.
- Problem: A mistyped email locks the user out of their account permanently.

**Wrong approach B:** Send the verification code to the *old* email.
- Problem: An attacker who temporarily has access to the account can intercept
  the code on the old email, then change it to one they control.

**The correct flow:**

```
1. User enters new email on profile page
2. System sends 6-digit verification code to NEW email
3. User enters code on the portal (code valid for 10 minutes)
4. Only after code confirmed → UPDATE email in DB
5. Send a "your email was changed" security alert to OLD email
```

This means you need two separate columns in the DB during the transition period:
`email` (the current confirmed address) and `pending_email` + `email_change_token`
(the unconfirmed new address). Only swap them after verification.

---

## 4. Database Changes

### 4a. Add `phone` and `profile_photo` to `users`

```sql
ALTER TABLE users
  ADD COLUMN phone             VARCHAR(20)  NULL          AFTER email,
  ADD COLUMN profile_photo     VARCHAR(255) NULL          AFTER phone,
  ADD COLUMN pending_email     VARCHAR(255) NULL          AFTER profile_photo,
  ADD COLUMN email_change_token VARCHAR(64) NULL          AFTER pending_email,
  ADD COLUMN email_change_expires DATETIME NULL           AFTER email_change_token;
```

### 4b. Add `phone` and `profile_photo` to `admin_users`

```sql
ALTER TABLE admin_users
  ADD COLUMN phone             VARCHAR(20)  NULL          AFTER email,
  ADD COLUMN profile_photo     VARCHAR(255) NULL          AFTER phone,
  ADD COLUMN pending_email     VARCHAR(255) NULL          AFTER profile_photo,
  ADD COLUMN email_change_token VARCHAR(64) NULL          AFTER pending_email,
  ADD COLUMN email_change_expires DATETIME NULL           AFTER email_change_token;
```

### 4c. Upload folder for profile photos

Create the directory and add it to `.gitignore`:

```
/uploads/
    profile_photos/       ← new
    application_letter/
    national_id_copy/
    insurance_cert/
    campus_letter/
```

---

## 5. API Endpoints

Build **one endpoint per role group**. Never share a single endpoint across roles
and branch inside it — that pattern leaks the risk of updating wrong fields on
the wrong account type.

| Endpoint | Method | Auth Guard | Purpose |
|----------|--------|------------|---------|
| `api/update-student-profile.php` | POST | `requireStudent()` | Edit student name, phone, photo |
| `api/update-admin-profile.php` | POST | `requireAnyAdmin()` | Edit admin phone, photo |
| `api/request-email-change.php` | POST | `requireStudent()` or `requireAnyAdmin()` | Send verification code to new email |
| `api/confirm-email-change.php` | POST | `requireStudent()` or `requireAnyAdmin()` | Confirm code, swap email |
| `api/change-password.php` | POST | `requireStudent()` or `requireAnyAdmin()` | Current password gate + update |
| `api/upload-profile-photo.php` | POST | `requireStudent()` or `requireAnyAdmin()` | Upload and resize photo |
| `api/get-profile.php` | GET | Authenticated | Return current user's profile data |

---

### 5a. `api/get-profile.php`

Returns the authenticated user's editable profile data. Used to pre-fill the
profile form on page load.

```php
<?php
require_once '../session-manager.php';

$session = getSession(); // your existing session helper

if (isset($session['user_id'])) {
    // Student
    $stmt = $pdo->prepare("SELECT full_name, email, phone, national_id,
                                   profile_photo, pending_email
                            FROM users WHERE id = ?");
    $stmt->execute([$session['user_id']]);
} else {
    // Admin (any tier)
    $stmt = $pdo->prepare("SELECT pf_number, email, phone, profile_photo,
                                   pending_email,
                                   r.name AS role, d.name AS department
                            FROM admin_users a
                            JOIN roles r ON r.id = a.role_id
                            LEFT JOIN departments d ON d.id = a.department_id
                            WHERE a.id = ?");
    $stmt->execute([$session['admin_id']]);
}

$profile = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$profile) {
    http_response_code(404);
    echo json_encode(['error' => 'Profile not found']);
    exit;
}

echo json_encode(['success' => true, 'profile' => $profile]);
```

---

### 5b. `api/update-student-profile.php`

```php
<?php
require_once '../session-manager.php';
require_once '../audit-logger.php';

requireStudent();

$fullName = trim($_POST['full_name'] ?? '');
$phone    = trim($_POST['phone']     ?? '');

// --- Validation ---
$errors = [];

if (empty($fullName)) {
    $errors[] = 'Full name is required.';
} elseif (strlen($fullName) > 100) {
    $errors[] = 'Full name must be 100 characters or fewer.';
}

if (!empty($phone) && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
    $errors[] = 'Invalid phone number format.';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// --- Update (only editable columns, never national_id or role) ---
$stmt = $pdo->prepare("UPDATE users
                        SET full_name = ?, phone = ?, updated_at = NOW()
                        WHERE id = ?");
$stmt->execute([$fullName, $phone, $session['user_id']]);

logAudit($pdo, 'student_profile_updated', "User ID {$session['user_id']} updated profile");

echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
```

---

### 5c. `api/update-admin-profile.php`

```php
<?php
require_once '../session-manager.php';
require_once '../audit-logger.php';

requireAnyAdmin();

$phone = trim($_POST['phone'] ?? '');

$errors = [];
if (!empty($phone) && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
    $errors[] = 'Invalid phone number format.';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// NEVER allow pf_number, department_id, role_id, or is_active to be updated here
$stmt = $pdo->prepare("UPDATE admin_users
                        SET phone = ?, updated_at = NOW()
                        WHERE id = ?");
$stmt->execute([$phone, $session['admin_id']]);

logAudit($pdo, 'admin_profile_updated', "Admin ID {$session['admin_id']} updated profile");

echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
```

---

### 5d. `api/request-email-change.php`

```php
<?php
require_once '../session-manager.php';
require_once '../lib/mailer.php';

// Works for both students and admins
$isStudent = isset($session['user_id']);
$userId    = $isStudent ? $session['user_id'] : $session['admin_id'];
$table     = $isStudent ? 'users' : 'admin_users';

$newEmail = trim($_POST['new_email'] ?? '');

if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid email address.']);
    exit;
}

// Check if email is already taken
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?
                        UNION SELECT id FROM admin_users WHERE email = ?");
$stmt->execute([$newEmail, $newEmail]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'That email address is already in use.']);
    exit;
}

// Generate 6-digit code (not a URL token — simpler UX for the user)
$code    = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
$hashed  = password_hash($code, PASSWORD_BCRYPT);  // store hashed, compare on confirm

$pdo->prepare("UPDATE {$table}
               SET pending_email = ?, email_change_token = ?,
                   email_change_expires = ?
               WHERE id = ?")
    ->execute([$newEmail, $hashed, $expires, $userId]);

// Email the code to the NEW address
$html = "
    <p>You requested an email change on your Vuka Portal account.</p>
    <p>Your verification code is: <strong style='font-size:24px'>{$code}</strong></p>
    <p>This code expires in <strong>10 minutes</strong>.</p>
    <p>If you did not request this, ignore this email — your address has not changed.</p>";

sendMail($newEmail, '', 'Verify Your New Email — Vuka Portal', $html);

// Also alert the OLD email
$oldStmt = $pdo->prepare("SELECT email FROM {$table} WHERE id = ?");
$oldStmt->execute([$userId]);
$old = $oldStmt->fetchColumn();

if ($old) {
    $alertHtml = "
        <p>A request was made to change your Vuka Portal email address to
        <strong>{$newEmail}</strong>.</p>
        <p>If this was not you, contact your system administrator immediately.</p>";
    sendMail($old, '', 'Security Alert: Email Change Requested — Vuka Portal', $alertHtml);
}

echo json_encode(['success' => true,
    'message' => 'A verification code has been sent to your new email address.']);
```

---

### 5e. `api/confirm-email-change.php`

```php
<?php
require_once '../session-manager.php';

$isStudent = isset($session['user_id']);
$userId    = $isStudent ? $session['user_id'] : $session['admin_id'];
$table     = $isStudent ? 'users' : 'admin_users';

$code = trim($_POST['code'] ?? '');

$stmt = $pdo->prepare("SELECT pending_email, email_change_token, email_change_expires
                        FROM {$table} WHERE id = ?");
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || !$row['pending_email'] || !$row['email_change_token']) {
    http_response_code(400);
    echo json_encode(['error' => 'No pending email change found.']);
    exit;
}

if (strtotime($row['email_change_expires']) < time()) {
    http_response_code(400);
    echo json_encode(['error' => 'Verification code has expired. Please request a new one.']);
    exit;
}

if (!password_verify($code, $row['email_change_token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Incorrect verification code.']);
    exit;
}

// Swap email — clear pending fields
$pdo->prepare("UPDATE {$table}
               SET email = ?, pending_email = NULL,
                   email_change_token = NULL, email_change_expires = NULL
               WHERE id = ?")
    ->execute([$row['pending_email'], $userId]);

echo json_encode(['success' => true, 'message' => 'Email address updated successfully.']);
```

---

### 5f. `api/change-password.php`

```php
<?php
require_once '../session-manager.php';
require_once '../audit-logger.php';

$isStudent   = isset($session['user_id']);
$userId      = $isStudent ? $session['user_id'] : $session['admin_id'];
$table       = $isStudent ? 'users' : 'admin_users';

$currentPassword = $_POST['current_password'] ?? '';
$newPassword     = $_POST['new_password']     ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$errors = [];

if (empty($currentPassword)) {
    $errors[] = 'Current password is required.';
}
if (strlen($newPassword) < 8) {
    $errors[] = 'New password must be at least 8 characters.';
}
if ($newPassword !== $confirmPassword) {
    $errors[] = 'New passwords do not match.';
}
if ($newPassword === $currentPassword) {
    $errors[] = 'New password must be different from your current password.';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// Verify current password against DB
$stmt = $pdo->prepare("SELECT password FROM {$table} WHERE id = ?");
$stmt->execute([$userId]);
$stored = $stmt->fetchColumn();

if (!$stored || !password_verify($currentPassword, $stored)) {
    http_response_code(401);
    echo json_encode(['error' => 'Current password is incorrect.']);
    exit;
}

// Update
$hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
$pdo->prepare("UPDATE {$table} SET password = ?, updated_at = NOW() WHERE id = ?")
    ->execute([$hashed, $userId]);

// Invalidate all other sessions for this user (force re-login on other devices)
if ($isStudent) {
    $pdo->prepare("DELETE FROM sessions WHERE user_id = ? AND token != ?")
        ->execute([$userId, $session['token']]);
} else {
    $pdo->prepare("DELETE FROM sessions WHERE admin_id = ? AND token != ?")
        ->execute([$userId, $session['token']]);
}

logAudit($pdo, 'password_changed', ($isStudent ? 'Student' : 'Admin') . " ID {$userId} changed password");

echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
```

> **Note:** `sessions` table may use `user_id` or `admin_id` depending on your
> current schema. Adjust the column name to match your implementation.

---

### 5g. `api/upload-profile-photo.php`

```php
<?php
require_once '../session-manager.php';

$isStudent = isset($session['user_id']);
$userId    = $isStudent ? $session['user_id'] : $session['admin_id'];
$table     = $isStudent ? 'users' : 'admin_users';

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error.']);
    exit;
}

$file     = $_FILES['photo'];
$maxSize  = 2 * 1024 * 1024; // 2 MB
$allowed  = ['image/jpeg', 'image/png', 'image/webp'];
$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

// Validate size
if ($file['size'] > $maxSize) {
    http_response_code(422);
    echo json_encode(['error' => 'Photo must be 2 MB or smaller.']);
    exit;
}

// Validate MIME type (read from file, never trust $_FILES['type'])
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Only JPEG, PNG, and WebP images are allowed.']);
    exit;
}

// Validate extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid file extension.']);
    exit;
}

// Sanitize filename — never use the original
$filename  = bin2hex(random_bytes(16)) . '.' . $ext;
$uploadDir = __DIR__ . '/../uploads/profile_photos/';
$destPath  = $uploadDir . $filename;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save photo. Please try again.']);
    exit;
}

// Delete the old photo if one exists
$oldStmt = $pdo->prepare("SELECT profile_photo FROM {$table} WHERE id = ?");
$oldStmt->execute([$userId]);
$oldPhoto = $oldStmt->fetchColumn();
if ($oldPhoto && file_exists($uploadDir . $oldPhoto)) {
    unlink($uploadDir . $oldPhoto);
}

// Save new filename to DB
$pdo->prepare("UPDATE {$table} SET profile_photo = ? WHERE id = ?")
    ->execute([$filename, $userId]);

echo json_encode([
    'success'   => true,
    'photo_url' => '/uploads/profile_photos/' . $filename,
]);
```

---

## 6. Frontend Implementation

### 6a. Profile section in each dashboard

Add a **"My Profile"** tab or sidebar link in each role's dashboard. The profile
panel has three sub-sections rendered as Bootstrap tabs:

1. **Personal Info** — editable fields (name, phone, photo)
2. **Email** — email change flow (current email shown read-only, separate form)
3. **Security** — password change form

```html
<!-- Profile panel — add to each dashboard PHP file -->
<div id="profileView" class="view-section d-none">
  <div class="d-flex align-items-center gap-3 mb-4">
    <div class="position-relative">
      <img id="profilePhotoPreview"
           src="/assets/img/default-avatar.png"
           alt="Profile photo"
           class="rounded-circle"
           style="width:80px;height:80px;object-fit:cover;">
      <label for="photoInput"
             class="position-absolute bottom-0 end-0 btn btn-sm btn-primary
                    rounded-circle p-1" style="width:28px;height:28px;line-height:1">
        <i class="fas fa-camera" style="font-size:11px"></i>
      </label>
      <input type="file" id="photoInput" accept="image/*"
             class="d-none" onchange="uploadProfilePhoto(this)">
    </div>
    <div>
      <h5 class="mb-0" id="profileDisplayName">Loading...</h5>
      <small class="text-muted" id="profileDisplayRole"></small>
    </div>
  </div>

  <!-- Sub-section tabs -->
  <ul class="nav nav-tabs mb-3" id="profileTabs">
    <li class="nav-item">
      <button class="nav-link active" onclick="switchProfileTab('info')">
        Personal Info
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" onclick="switchProfileTab('email')">
        Email
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" onclick="switchProfileTab('security')">
        Security
      </button>
    </li>
  </ul>

  <!-- Personal Info tab -->
  <div id="profileTabInfo">
    <div class="row g-3" style="max-width:480px">
      <div class="col-12">
        <label class="form-label">Full Name</label>
        <!-- Admins do not have full_name — hide this field for admin roles -->
        <input type="text" class="form-control" id="fieldFullName"
               placeholder="Full name">
      </div>
      <div class="col-12">
        <label class="form-label">Phone Number</label>
        <input type="tel" class="form-control" id="fieldPhone"
               placeholder="+254 7XX XXX XXX">
      </div>
      <!-- Read-only identity fields — shown for transparency, not editable -->
      <div class="col-12">
        <label class="form-label text-muted">
          National ID <span class="badge bg-secondary">Read-only</span>
        </label>
        <input type="text" class="form-control" id="fieldNationalId"
               disabled>
      </div>
      <div class="col-12">
        <button class="btn btn-primary" onclick="savePersonalInfo()">
          Save Changes
        </button>
      </div>
    </div>
  </div>

  <!-- Email tab -->
  <div id="profileTabEmail" class="d-none" style="max-width:480px">
    <div class="mb-3">
      <label class="form-label text-muted">Current Email</label>
      <input type="email" class="form-control" id="fieldCurrentEmail" disabled>
    </div>
    <div id="emailChangeStep1">
      <label class="form-label">New Email Address</label>
      <div class="input-group">
        <input type="email" class="form-control" id="fieldNewEmail"
               placeholder="Enter new email">
        <button class="btn btn-outline-primary" onclick="requestEmailChange()">
          Send Code
        </button>
      </div>
      <small class="text-muted mt-1 d-block">
        A verification code will be sent to the new address.
      </small>
    </div>
    <div id="emailChangeStep2" class="d-none mt-3">
      <label class="form-label">Verification Code</label>
      <div class="input-group">
        <input type="text" class="form-control" id="fieldEmailCode"
               maxlength="6" placeholder="6-digit code">
        <button class="btn btn-success" onclick="confirmEmailChange()">
          Confirm
        </button>
      </div>
      <button class="btn btn-link btn-sm p-0 mt-1" onclick="resetEmailChangeFlow()">
        Use a different email
      </button>
    </div>
  </div>

  <!-- Security tab -->
  <div id="profileTabSecurity" class="d-none" style="max-width:480px">
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label">Current Password</label>
        <input type="password" class="form-control" id="fieldCurrentPwd">
      </div>
      <div class="col-12">
        <label class="form-label">New Password</label>
        <input type="password" class="form-control" id="fieldNewPwd">
        <div id="pwdStrengthBar" class="progress mt-1" style="height:4px">
          <div id="pwdStrengthFill" class="progress-bar" style="width:0%"></div>
        </div>
        <small id="pwdStrengthLabel" class="text-muted"></small>
      </div>
      <div class="col-12">
        <label class="form-label">Confirm New Password</label>
        <input type="password" class="form-control" id="fieldConfirmPwd">
      </div>
      <div class="col-12">
        <button class="btn btn-primary" onclick="changePassword()">
          Update Password
        </button>
      </div>
    </div>
  </div>
</div>
```

---

### 6b. Profile JS — add to the relevant dashboard JS file

```javascript
// ─── Profile: load ─────────────────────────────────────────────────────────

async function loadProfile() {
    const data = await apiFetch('/api/get-profile.php');
    if (!data.profile) return;

    const p = data.profile;

    // Photo
    if (p.profile_photo) {
        document.getElementById('profilePhotoPreview').src =
            '/uploads/profile_photos/' + p.profile_photo;
    }

    // Display name and role
    document.getElementById('profileDisplayName').textContent =
        p.full_name || p.pf_number || '';
    document.getElementById('profileDisplayRole').textContent =
        p.role || 'Student';

    // Personal info tab
    if (document.getElementById('fieldFullName')) {
        document.getElementById('fieldFullName').value = p.full_name || '';
    }
    document.getElementById('fieldPhone').value          = p.phone || '';
    if (document.getElementById('fieldNationalId')) {
        document.getElementById('fieldNationalId').value = p.national_id || '';
    }

    // Email tab
    document.getElementById('fieldCurrentEmail').value = p.email || '';

    // If there's a pending email change, skip straight to step 2
    if (p.pending_email) {
        document.getElementById('fieldNewEmail').value = p.pending_email;
        document.getElementById('emailChangeStep1').classList.add('d-none');
        document.getElementById('emailChangeStep2').classList.remove('d-none');
    }
}


// ─── Profile: save personal info ───────────────────────────────────────────

async function savePersonalInfo() {
    const body = new FormData();

    const fullNameEl = document.getElementById('fieldFullName');
    if (fullNameEl) body.append('full_name', fullNameEl.value.trim());
    body.append('phone', document.getElementById('fieldPhone').value.trim());

    // Choose correct endpoint based on role stored in localStorage/session
    const endpoint = window.VUKA_ROLE === 'student'
        ? '/api/update-student-profile.php'
        : '/api/update-admin-profile.php';

    const res = await apiFetch(endpoint, { method: 'POST', body });
    if (res.success) {
        showToast(res.message, 'success');
        // Update display name in navbar if changed
        if (fullNameEl) {
            document.getElementById('profileDisplayName').textContent =
                fullNameEl.value.trim();
        }
    } else {
        showToast(res.error || 'Update failed', 'danger');
    }
}


// ─── Profile: email change ──────────────────────────────────────────────────

async function requestEmailChange() {
    const newEmail = document.getElementById('fieldNewEmail').value.trim();
    if (!newEmail) { showToast('Enter a new email address first', 'warning'); return; }

    const body = new FormData();
    body.append('new_email', newEmail);

    const res = await apiFetch('/api/request-email-change.php', { method: 'POST', body });
    if (res.success) {
        showToast(res.message, 'success');
        document.getElementById('emailChangeStep1').classList.add('d-none');
        document.getElementById('emailChangeStep2').classList.remove('d-none');
    } else {
        showToast(res.error || 'Failed to send code', 'danger');
    }
}

async function confirmEmailChange() {
    const code = document.getElementById('fieldEmailCode').value.trim();
    if (code.length !== 6) { showToast('Enter the 6-digit code', 'warning'); return; }

    const body = new FormData();
    body.append('code', code);

    const res = await apiFetch('/api/confirm-email-change.php', { method: 'POST', body });
    if (res.success) {
        showToast(res.message, 'success');
        resetEmailChangeFlow();
        loadProfile(); // refresh to show new email
    } else {
        showToast(res.error || 'Invalid code', 'danger');
    }
}

function resetEmailChangeFlow() {
    document.getElementById('fieldNewEmail').value  = '';
    document.getElementById('fieldEmailCode').value = '';
    document.getElementById('emailChangeStep1').classList.remove('d-none');
    document.getElementById('emailChangeStep2').classList.add('d-none');
}


// ─── Profile: password change ───────────────────────────────────────────────

async function changePassword() {
    const current  = document.getElementById('fieldCurrentPwd').value;
    const newPwd   = document.getElementById('fieldNewPwd').value;
    const confirm  = document.getElementById('fieldConfirmPwd').value;

    if (newPwd !== confirm) {
        showToast('New passwords do not match', 'danger');
        return;
    }

    const body = new FormData();
    body.append('current_password', current);
    body.append('new_password',     newPwd);
    body.append('confirm_password', confirm);

    const res = await apiFetch('/api/change-password.php', { method: 'POST', body });
    if (res.success) {
        showToast(res.message, 'success');
        document.getElementById('fieldCurrentPwd').value = '';
        document.getElementById('fieldNewPwd').value     = '';
        document.getElementById('fieldConfirmPwd').value = '';
    } else {
        showToast(res.error || 'Failed to change password', 'danger');
    }
}

// Password strength meter — attach to keyup on #fieldNewPwd
document.getElementById('fieldNewPwd')?.addEventListener('keyup', function () {
    const val   = this.value;
    const bar   = document.getElementById('pwdStrengthFill');
    const label = document.getElementById('pwdStrengthLabel');
    let score   = 0;

    if (val.length >= 8)                    score++;
    if (/[A-Z]/.test(val))                  score++;
    if (/[0-9]/.test(val))                  score++;
    if (/[^A-Za-z0-9]/.test(val))          score++;

    const levels = [
        { pct: '0%',   cls: '',          text: '' },
        { pct: '25%',  cls: 'bg-danger', text: 'Weak' },
        { pct: '50%',  cls: 'bg-warning',text: 'Fair' },
        { pct: '75%',  cls: 'bg-info',   text: 'Good' },
        { pct: '100%', cls: 'bg-success',text: 'Strong' },
    ];

    bar.style.width    = levels[score].pct;
    bar.className      = 'progress-bar ' + levels[score].cls;
    label.textContent  = levels[score].text;
});


// ─── Profile: photo upload ──────────────────────────────────────────────────

async function uploadProfilePhoto(input) {
    if (!input.files[0]) return;

    const body = new FormData();
    body.append('photo', input.files[0]);

    // Preview immediately (optimistic UI)
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('profilePhotoPreview').src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);

    const res = await apiFetch('/api/upload-profile-photo.php', { method: 'POST', body });
    if (res.success) {
        showToast('Profile photo updated', 'success');
        // Update any other avatar instances in the navbar
        document.querySelectorAll('.nav-avatar').forEach(el => {
            el.src = res.photo_url;
        });
    } else {
        showToast(res.error || 'Upload failed', 'danger');
        // Revert preview on failure
        loadProfile();
    }
}


// ─── Profile: tab switcher ──────────────────────────────────────────────────

function switchProfileTab(tab) {
    ['info', 'email', 'security'].forEach(t => {
        const el = document.getElementById('profileTab' + t.charAt(0).toUpperCase() + t.slice(1));
        if (el) el.classList.toggle('d-none', t !== tab);
    });
    document.querySelectorAll('#profileTabs .nav-link').forEach((btn, i) => {
        btn.classList.toggle('active', ['info','email','security'][i] === tab);
    });
}
```

---

## 7. Password Change Flow

Sequence diagram for the full password change operation:

```
User                     Frontend              API: change-password.php     DB
 │                          │                           │                    │
 │── enters current pwd ───>│                           │                    │
 │── enters new pwd ───────>│                           │                    │
 │── enters confirm pwd ───>│                           │                    │
 │                          │── POST /change-password ─>│                    │
 │                          │                           │── SELECT pwd ─────>│
 │                          │                           │<─ hashed pwd ──────│
 │                          │                           │                    │
 │                          │                           │ password_verify()  │
 │                          │                           │ (fails → 401)      │
 │                          │                           │ (passes → continue)│
 │                          │                           │                    │
 │                          │                           │── UPDATE pwd ─────>│
 │                          │                           │── DELETE other     │
 │                          │                           │   sessions ───────>│
 │                          │<─ { success: true } ──────│                    │
 │<── toast: "Password      │                           │                    │
 │    changed" ─────────────│                           │                    │
```

The **"delete other sessions"** step is critical — it forces any attacker who may
have had access to re-authenticate, now locked out by the new password.

---

## 8. Profile Photo Upload

### Security checklist for file uploads

Every profile photo upload must pass all five checks before saving:

| Check | Implementation |
|-------|----------------|
| File size ≤ 2 MB | `$_FILES['photo']['size'] > 2097152` |
| MIME type from file content | `finfo::file()` — never `$_FILES['type']` |
| Extension whitelist | `jpg`, `jpeg`, `png`, `webp` only |
| Randomized filename | `bin2hex(random_bytes(16))` — never original name |
| Old file cleanup | `unlink()` previous photo after successful upload |

### Serving photos safely

Never serve the `uploads/` folder directly without protection. Add an `.htaccess`
inside `uploads/profile_photos/` to prevent PHP execution inside the folder:

```apache
# /uploads/profile_photos/.htaccess
php_flag engine off
Options -ExecCGI
AddHandler cgi-script .php .pl .py .sh
```

This ensures an attacker who somehow uploads a `.php` file disguised as an image
cannot execute it through the browser.

---

## Summary — Fields by Role

| Field | Student | Supervisor | HR Coordinator | Super Admin |
|-------|---------|------------|----------------|-------------|
| Full name | ✅ | — | — | — |
| Phone | ✅ | ✅ | ✅ | ✅ |
| Email | ✅ (verify) | ✅ (verify) | ✅ (verify) | ✅ (verify) |
| Password | ✅ (gate) | ✅ (gate) | ✅ (gate) | ✅ (gate) |
| Profile photo | ✅ | ✅ | ✅ | ✅ |
| National ID | ❌ | — | — | — |
| PF Number | — | ❌ | ❌ | ❌ |
| Department | — | ❌ | ❌ | ❌ |
| Role | ❌ | ❌ | ❌ | ❌ |
| Account status | ❌ | ❌ | ❌ | ❌ |

✅ = editable by the user themselves
❌ = immutable / system-managed
— = field does not exist for this role
