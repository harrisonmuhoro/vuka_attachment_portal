// ─── Profile: load ─────────────────────────────────────────────────────────

async function apiFetch(endpoint, options = {}) {
    const token = sessionStorage.getItem('userToken') || sessionStorage.getItem('adminToken');
    if (!options.headers) options.headers = {};
    if (token) options.headers['Authorization'] = `Bearer ${token}`;
    
    try {
        const response = await fetch(endpoint, options);
        return await response.json();
    } catch (e) {
        console.error('API Fetch Error:', e);
        return { success: false, error: 'Network error occurred.' };
    }
}

async function loadProfile() {
    const data = await apiFetch('/attachment/api/get-profile.php');
    if (!data.profile) return;

    const p = data.profile;

    // Photo
    if (p.profile_photo) {
        const token = sessionStorage.getItem('userToken') || sessionStorage.getItem('adminToken');
        const preview = document.getElementById('profilePhotoPreview');
        if(preview) {
            preview.src = '/attachment/api/serve-profile-photo.php?token=' + token + '&t=' + Date.now();
        }
        document.querySelectorAll('.nav-avatar').forEach(el => {
            el.src = '/attachment/api/serve-profile-photo.php?token=' + token + '&t=' + Date.now();
        });
    }

    // Display name and role
    const nameEl = document.getElementById('profileDisplayName');
    if(nameEl) nameEl.textContent = p.full_name || p.pf_number || '';
    
    const roleEl = document.getElementById('profileDisplayRole');
    if(roleEl) roleEl.textContent = p.role || 'Student';

    // Personal info tab
    if (document.getElementById('fieldFullName')) {
        document.getElementById('fieldFullName').value = p.full_name || '';
    }
    if (document.getElementById('fieldPhone')) {
        document.getElementById('fieldPhone').value = p.phone || '';
    }
    if (document.getElementById('fieldNationalId')) {
        document.getElementById('fieldNationalId').value = p.national_id || '';
    }

    // Email tab
    if (document.getElementById('fieldCurrentEmail')) {
        document.getElementById('fieldCurrentEmail').value = p.email || '';
    }

    // If there's a pending email change, skip straight to step 2
    if (p.pending_email) {
        const newEmailEl = document.getElementById('fieldNewEmail');
        if(newEmailEl) newEmailEl.value = p.pending_email;
        
        const step1 = document.getElementById('emailChangeStep1');
        const step2 = document.getElementById('emailChangeStep2');
        if(step1) step1.classList.add('d-none');
        if(step2) step2.classList.remove('d-none');
    }
}


// ─── Profile: save personal info ───────────────────────────────────────────

async function savePersonalInfo() {
    const body = new FormData();

    const fullNameEl = document.getElementById('fieldFullName');
    if (fullNameEl) body.append('full_name', fullNameEl.value.trim());
    
    const phoneEl = document.getElementById('fieldPhone');
    if(phoneEl) body.append('phone', phoneEl.value.trim());

    // Choose correct endpoint based on role
    // The role comes from the dashboard script, or we can infer it by checking if it's a student dashboard
    const isStudent = window.location.pathname.includes('student_dashboard');
    const endpoint = isStudent
        ? '/attachment/api/update-student-profile.php'
        : '/attachment/api/update-admin-profile.php';

    const res = await apiFetch(endpoint, { method: 'POST', body });
    if (res.success) {
        showToast(res.message, 'success');
        // Update display name in navbar if changed
        if (fullNameEl) {
            const dispName = document.getElementById('profileDisplayName');
            if(dispName) dispName.textContent = fullNameEl.value.trim();
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

    const res = await apiFetch('/attachment/api/request-email-change.php', { method: 'POST', body });
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

    const res = await apiFetch('/attachment/api/confirm-email-change.php', { method: 'POST', body });
    if (res.success) {
        showToast(res.message, 'success');
        resetEmailChangeFlow();
        loadProfile(); // refresh to show new email
    } else {
        showToast(res.error || 'Invalid code', 'danger');
    }
}

function resetEmailChangeFlow() {
    const newEmail = document.getElementById('fieldNewEmail');
    if(newEmail) newEmail.value  = '';
    const codeEl = document.getElementById('fieldEmailCode');
    if(codeEl) codeEl.value = '';
    const step1 = document.getElementById('emailChangeStep1');
    const step2 = document.getElementById('emailChangeStep2');
    if(step1) step1.classList.remove('d-none');
    if(step2) step2.classList.add('d-none');
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

    const res = await apiFetch('/attachment/api/change-password.php', { method: 'POST', body });
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
document.addEventListener('DOMContentLoaded', () => {
    const pwdInput = document.getElementById('fieldNewPwd');
    if(pwdInput) {
        pwdInput.addEventListener('keyup', function () {
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

            if(bar) {
                bar.style.width    = levels[score].pct;
                bar.className      = 'progress-bar ' + levels[score].cls;
            }
            if(label) {
                label.textContent  = levels[score].text;
            }
        });
    }
});


// ─── Profile: photo upload ──────────────────────────────────────────────────

async function uploadProfilePhoto(input) {
    if (!input.files[0]) return;

    const body = new FormData();
    body.append('photo', input.files[0]);

    // Preview immediately (optimistic UI)
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('profilePhotoPreview');
        if(preview) preview.src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);

    const res = await apiFetch('/attachment/api/upload-profile-photo.php', { method: 'POST', body });
    if (res.success) {
        showToast('Profile photo updated', 'success');
        loadProfile();
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
