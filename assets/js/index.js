// ============ VERIFICATION MODAL ============
function openVerificationModal(email) {
    document.getElementById('verifyEmailDisplay').textContent = email || '';
    document.getElementById('verifyCodeInput').value = '';
    const fb = document.getElementById('verifyFeedback');
    if (fb) fb.style.display = 'none';
    emailVerifyModal.show();
}


// ============ LOGIN ============
if (loginForm) loginForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const identifier = document.getElementById('loginId').value.trim();
    const password = document.getElementById('loginPassword').value;

    if (!identifier || !password) {
        showToast('Please fill in all fields.', 'warning');
        return;
    }

    // Universal ID Validation (6-10 Digits)
    if (!/^\d{6,10}$/.test(identifier)) {
        showToast('Invalid National ID format. Use 6-10 digits.', 'warning');
        return;
    }

    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';

    try {
        const response = await fetch(`${API_BASE}/login.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ identifier: identifier, password: password })
        });
        const data = await response.json();

        if (data.success) {
            // Check Role & Redirect
            if (data.redirect) {
                // Store tokens
                if (data.role === 'admin' || data.role === 'super_admin') {
                    sessionStorage.setItem('adminLoggedIn', 'true');
                    sessionStorage.setItem('adminToken', data.token);
                    sessionStorage.setItem('adminPF', data.pf_number);
                    sessionStorage.setItem('adminDept', data.department || 'ALL');
                    sessionStorage.setItem('adminName', data.full_name || data.pf_number);
                    sessionStorage.setItem('currentUserRole', data.role_id); // 1, 2, or 3
                } else {
                    sessionStorage.setItem('currentUser', data.user.national_id);
                    sessionStorage.setItem('userToken', data.token);
                    sessionStorage.setItem('userId', data.user.userId);
                    sessionStorage.setItem('userName', data.user.full_name);
                    sessionStorage.setItem('userEmail', data.user.email || '');
                }

                showToast(`Login successful! Redirecting...`, 'success');
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1000);
            } else {
                // Fallback for old behavior (should not happen with new login.php)
                showToast('Login successful but no redirect found.', 'warning');
            }

            loginForm.reset();
            loginForm.classList.remove('was-validated');

        } else {
            // Handle errors
            if (data.needsVerification) {
                sessionStorage.setItem('pendingVerifyId', data.national_id);
                openVerificationModal('');
            } else if (data.alreadySubmitted) {
                if (document.getElementById('blockedIdDisplay')) document.getElementById('blockedIdDisplay').textContent = data.national_id;
                switchView('blocked');
            } else {
                showToast(data.message || 'Login failed.', 'error');
            }
        }
    } catch (error) { showToast('Login error: ' + error.message, 'error'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Login to Portal'; }
});


// ============ REGISTRATION ============
if (registrationForm) registrationForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const fullName = document.getElementById('regFullName').value.trim();
    const idNumber = document.getElementById('regIdNumber').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;
    const regDate = document.getElementById('regLoginDate').value;

    if (!fullName || !idNumber || !email || !password || !confirmPassword || !regDate) {
        showToast('Please fill in all fields.', 'warning');
        return;
    }

    if (password !== confirmPassword) {
        showToast('Passwords do not match.', 'warning');
        return;
    }

    if (!isValidIdNumber(idNumber)) {
        showToast('National ID must be 6 to 9 digits (numbers only).', 'warning');
        return;
    }

    if (!isValidEmail(email)) {
        showToast('Email must end with: ' + ALLOWED_EMAIL_DOMAINS.join(', '), 'warning', 6000);
        return;
    }

    if (password.length < 6) {
        showToast('Password must be at least 6 characters.', 'warning');
        return;
    }

    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registering...';

    try {
        const response = await fetch(`${API_BASE}/register.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                national_id: idNumber, full_name: fullName,
                email: email, password: password, registration_date: regDate
            })
        });
        const data = await response.json();
        if (data.success) {
            sessionStorage.setItem('pendingVerifyEmail', email);
            sessionStorage.setItem('pendingVerifyEmail', email);
            sessionStorage.setItem('pendingVerifyId', idNumber);
            openVerificationModal(email);
            registrationForm.reset(); registrationForm.classList.remove('was-validated');

            // SHOW DEBUG CODE FOR LOCALHOST
            if (data.debug_code) {
                alert(`[LOCALHOST DEBUG] Your verification code is: ${data.debug_code}`);
            }

            showToast('Registration successful! Check your email for verification code.', 'success');
        } else { showToast(data.message || data.error || 'Registration failed.', 'error'); }
    } catch (error) { showToast('Registration error: ' + error.message, 'error'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="fas fa-user-plus me-2"></i>Complete Registration'; }
});


// ============ EMAIL VERIFICATION ============
if (document.getElementById('verifyCodeBtn')) document.getElementById('verifyCodeBtn').addEventListener('click', async function () {
    const codeInput = document.getElementById('verifyCodeInput').value.trim();
    const idNumber = sessionStorage.getItem('pendingVerifyId');
    if (!idNumber) { showToast('No verification in progress.', 'warning'); return; }

    try {
        const response = await fetch(`${API_BASE}/verify-email.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ national_id: idNumber, code: codeInput })
        });
        const data = await response.json();
        if (data.success) {
            sessionStorage.removeItem('pendingVerifyEmail');
            sessionStorage.removeItem('pendingVerifyId');
            emailVerifyModal.hide();
            showToast('Email verified! You can now login.', 'success');
            document.getElementById('login-tab').click();
        } else {
            const fb = document.getElementById('verifyFeedback');
            fb.style.display = 'block';
            fb.textContent = data.message || data.error || 'Invalid code.';
        }
    } catch (error) {
        const fb = document.getElementById('verifyFeedback');
        fb.style.display = 'block';
        fb.textContent = 'Error: ' + error.message;
    }
});

if (document.getElementById('resendVerifyLink')) document.getElementById('resendVerifyLink').addEventListener('click', async function (e) {
    e.preventDefault();
    const idNumber = sessionStorage.getItem('pendingVerifyId');
    if (!idNumber) { showToast('No verification in progress.', 'warning'); return; }
    try {
        const response = await fetch(`${API_BASE}/register.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ national_id: idNumber, resend_code: true })
        });
        const data = await response.json();
        if (data.success) {
            if (data.debug_code) alert(`[LOCALHOST DEBUG] New code: ${data.debug_code}`);
            showToast('Verification code resent.', 'info');
            const fb = document.getElementById('verifyFeedback');
            if (fb) fb.style.display = 'none';
        } else { showToast(data.message || data.error || 'Error resending code.', 'error'); }
    } catch (error) { showToast('Error: ' + error.message, 'error'); }
});


// ============ ADMIN LOGIN ============
if (adminLoginForm) adminLoginForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const pfNumber = document.getElementById('adminPFNumber').value.trim();
    const password = document.getElementById('adminPassword').value;

    if (!pfNumber || !password) {
        showToast('Please enter PF Number and Password.', 'warning');
        return;
    }

    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Authenticating...';

    try {
        const response = await fetch(`${API_BASE}/admin-login.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pf_number: pfNumber, password: password })
        });
        const data = await response.json();

        if (data.success) {
            const role = data.role || 'department_admin';
            sessionStorage.setItem('adminLoggedIn', 'true');
            sessionStorage.setItem('adminToken', data.token);
            sessionStorage.setItem('adminPF', data.pf_number || pfNumber);
            sessionStorage.setItem('adminRole', role);
            sessionStorage.setItem('adminDept', data.department || 'ALL');
            sessionStorage.setItem('adminName', data.full_name || pfNumber);

            document.getElementById('adminPFDisplay').textContent = pfNumber;
            const nameEl = document.getElementById('adminNameDisplay');
            const deptEl = document.getElementById('adminDeptDisplay');
            if (nameEl) nameEl.textContent = data.full_name || '';
            if (deptEl) deptEl.textContent = data.department || 'ALL';

            const isSenior = (role === 'super_admin');
            const seniorNav = document.getElementById('seniorAdminNav');
            const roleBadge = document.getElementById('adminRoleBadge');
            const panelTitle = document.getElementById('adminPanelTitle');
            if (seniorNav) seniorNav.style.display = isSenior ? 'flex' : 'none';
            if (roleBadge) roleBadge.textContent = isSenior ? 'SENIOR ADMIN' : 'DEPT ADMIN';
            if (panelTitle) panelTitle.textContent = isSenior ? 'Senior Admin Control Panel' : 'Admin Control Panel';

            switchView('admin');
            loadAdminDashboard();
            adminLoginForm.reset();
            showToast(`Welcome, ${data.full_name || pfNumber}!`, 'success');
        } else { showToast(data.message || data.error || 'Admin login failed.', 'error'); }
    } catch (error) { showToast('Admin login error: ' + error.message, 'error'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Admin Login'; }
});


// ============ FORGOT PASSWORD ============
let forgotPasswordModal = null;
const forgotPasswordLink = document.getElementById('forgotPasswordLink');
if (forgotPasswordLink) {
    forgotPasswordLink.addEventListener('click', function (e) {
        e.preventDefault();
        const el = document.getElementById('forgotPasswordModal');
        if (!forgotPasswordModal && el) forgotPasswordModal = new bootstrap.Modal(el);
        // Pre-fill with whatever is typed in the login field if it looks like an email.
        const loginVal = (document.getElementById('loginId')?.value || '').trim();
        const emailInput = document.getElementById('forgotEmail');
        if (emailInput && loginVal.includes('@')) emailInput.value = loginVal;
        if (forgotPasswordModal) forgotPasswordModal.show();
    });
}

const forgotPasswordForm = document.getElementById('forgotPasswordForm');
if (forgotPasswordForm) forgotPasswordForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const email = document.getElementById('forgotEmail').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showToast('Please enter a valid email address.', 'warning');
        return;
    }
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
    try {
        const response = await fetch(`${API_BASE}/forgot-password.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        const data = await response.json();
        showToast(data.message || 'If that email is registered, a reset link has been sent.', 'success');
        if (forgotPasswordModal) forgotPasswordModal.hide();
        forgotPasswordForm.reset();
    } catch (error) {
        showToast('Request failed: ' + error.message, 'error');
    } finally {
        btn.disabled = false; btn.innerHTML = original;
    }
});

