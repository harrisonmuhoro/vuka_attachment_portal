/**
 * Vuka - Attachment Portal
 * Main Application Logic v3.1
 * Fixed: API paths (www/api/), ID validation (6-8 digits), email domain whitelist
 */


// ============ API BASE URL ============
const API_BASE = (window.location.pathname.includes('/pages/') || window.location.pathname.includes('\\pages\\')) ? '../api' : 'api';


// ============ DARK MODE (Feature #8) ============
// Apply saved theme ASAP.
(function applySavedTheme() {
    try {
        const match = document.cookie.match(/(?:^|;)\s*vuka_theme=([^;]*)/);
        const saved = match ? match[1] : 'light';
        document.documentElement.setAttribute('data-theme', saved);
    } catch (e) { /* cookies unavailable */ }
})();

function toggleDarkMode() {
    const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { 
        document.cookie = `vuka_theme=${next}; path=/; max-age=31536000`; 
    } catch (e) {}
    document.querySelectorAll('.theme-toggle-icon').forEach(icon => {
        icon.className = 'theme-toggle-icon fas ' + (next === 'dark' ? 'fa-sun' : 'fa-moon');
    });
}

function initThemeToggleIcon() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    document.querySelectorAll('.theme-toggle-icon').forEach(icon => {
        icon.className = 'theme-toggle-icon fas ' + (isDark ? 'fa-sun' : 'fa-moon');
    });
}
document.addEventListener('DOMContentLoaded', initThemeToggleIcon);


// ============ ALLOWED EMAIL DOMAINS ============
const ALLOWED_EMAIL_DOMAINS = ['@gmail.com', '@outlook.com', '@hotmail.com', '@yahoo.com', '@ymail.com', '@aol.com', '@icloud.com'];

function isValidEmail(email) {
    if (!email || !email.includes('@')) return false;
    const domain = email.substring(email.lastIndexOf('@')).toLowerCase();
    return ALLOWED_EMAIL_DOMAINS.includes(domain);
}

function isValidIdNumber(id) {
    return /^\d{6,10}$/.test(id);
}


// ============ Initialize EmailJS ============
try { if (typeof emailjs !== 'undefined') emailjs.init('MBLFEPb0p8Uh5gS_V'); }
catch (e) { console.warn('EmailJS init failed:', e); }


// ============ TOAST NOTIFICATION SYSTEM ============
function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i><span>${message}</span>`;
    toast.addEventListener('click', () => dismissToast(toast));
    container.appendChild(toast);
    setTimeout(() => dismissToast(toast), duration);
}

function dismissToast(el) {
    if (!el || el.classList.contains('toast-exit')) return;
    el.classList.add('toast-exit');
    setTimeout(() => el.remove(), 300);
}


// ============ LOADING OVERLAY ============
function showLoading(text = 'Processing...') {
    const overlay = document.getElementById('loadingOverlay');
    const textEl = document.getElementById('loadingText');
    if (overlay) overlay.classList.add('active');
    if (textEl) textEl.textContent = text;
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.remove('active');
}


// ============ ANIMATED COUNTER ============
function animateCounter(element, target) {
    if (!element) return;
    const current = parseInt(element.textContent) || 0;
    if (current === target) return;
    const duration = 600;
    const start = performance.now();
    function update(now) {
        const elapsed = now - start;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        element.textContent = Math.round(current + (target - current) * eased);
        if (progress < 1) requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
}


// ============ RIPPLE EFFECT ============
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn');
    if (!btn) return;
    const ripple = document.createElement('span');
    ripple.className = 'ripple';
    const rect = btn.getBoundingClientRect();
    ripple.style.left = (e.clientX - rect.left) + 'px';
    ripple.style.top = (e.clientY - rect.top) + 'px';
    btn.appendChild(ripple);
    setTimeout(() => ripple.remove(), 600);
});


// ============ STICKY HEADER SCROLL ============
window.addEventListener('scroll', function () {
    const header = document.getElementById('mainHeader');
    if (header) header.classList.toggle('scrolled', window.scrollY > 30);
});


// ============ PASSWORD TOGGLE ============
document.querySelectorAll('.password-toggle').forEach(btn => {
    btn.addEventListener('click', function () {
        const input = document.getElementById(this.dataset.target);
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        this.innerHTML = `<i class="fas fa-eye${isPassword ? '-slash' : ''}"></i>`;
    });
});


// ============ ID NUMBER INPUT RESTRICTION (digits only) ============
function restrictToDigits(inputEl) {
    if (!inputEl) return;
    inputEl.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
    });
    inputEl.addEventListener('paste', function (e) {
        e.preventDefault();
        const paste = (e.clipboardData || window.clipboardData).getData('text');
        this.value = paste.replace(/\D/g, '').slice(0, 10);
    });
}

restrictToDigits(document.getElementById('loginId'));
restrictToDigits(document.getElementById('regIdNumber'));


// ============ DOM REFS ============
const views = {
    login: document.getElementById('login-view'),
    dashboard: document.getElementById('dashboard-view'),
    blocked: document.getElementById('blocked-view'),
    success: document.getElementById('success-view'),
    admin: document.getElementById('admin-view')
};

const loginForm = document.getElementById('loginForm');
const registrationForm = document.getElementById('registrationForm');
const adminLoginForm = document.getElementById('adminLoginForm');
const uploadForm = document.getElementById('uploadForm');
const logoutBtn = document.getElementById('logoutBtn') || document.getElementById('btnLogout');
const searchInput = document.getElementById('searchSubmissions');
const filterSelect = document.getElementById('filterStatus');
const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
const updateStatusBtn = document.getElementById('updateStatusBtn');
const saveReviewNotesBtn = document.getElementById('saveReviewNotesBtn');
const deleteSubmissionBtn = document.getElementById('deleteSubmissionBtn');
const adminLogoutBtn = document.getElementById('adminLogout');
const createAdminForm = document.getElementById('createAdminForm');


// ============ MODALS ============

// ============ MODALS (Initialized on DOM Content Loaded) ============
let confirmModal, emailVerifyModal, detailsModal, docPreviewModal;

document.addEventListener('DOMContentLoaded', function () {
    const confirmModalEl = document.getElementById('confirmModal');
    const emailVerifyModalEl = document.getElementById('emailVerifyModal');
    const detailsModalEl = document.getElementById('submissionDetailsModal');
    const docPreviewModalEl = document.getElementById('docPreviewModal');
    confirmModal = confirmModalEl ? new bootstrap.Modal(confirmModalEl) : null;
    emailVerifyModal = emailVerifyModalEl ? new bootstrap.Modal(emailVerifyModalEl) : null;
    detailsModal = detailsModalEl ? new bootstrap.Modal(detailsModalEl) : null;
    docPreviewModal = docPreviewModalEl ? new bootstrap.Modal(docPreviewModalEl) : null;

    if (docPreviewModalEl) {
        docPreviewModalEl.addEventListener('hidden.bs.modal', function () {
            if (currentPreviewBlobUrl) { window.URL.revokeObjectURL(currentPreviewBlobUrl); currentPreviewBlobUrl = null; }
            const iframe = document.getElementById('docPreviewIframe');
            const img = document.getElementById('docPreviewImg');
            if (iframe) iframe.src = '';
            if (img) img.src = '';
        });
    }
});

let currentSubmissions = [];
let currentViewingSubmissionId = null;
let currentPreviewBlobUrl = null;
let adminDeptCounts = {};


// ============ VIEW SWITCHING ============
function switchView(viewName) {
    Object.values(views).forEach(el => { if (el) el.classList.remove('active'); });
    if (views[viewName]) views[viewName].classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}


// ============ SENIOR ADMIN TAB SWITCHING ============
function switchAdminTab(tabName) {
    const tabs = ['submissions', 'manage-admins', 'create-admin'];
    tabs.forEach(t => {
        const el = document.getElementById('admin-tab-' + t);
        if (el) el.style.display = (t === tabName) ? 'block' : 'none';
    });
    document.querySelectorAll('.senior-admin-nav .nav-btn').forEach(btn => btn.classList.remove('active'));
    const clickedBtn = document.querySelector(`.senior-admin-nav .nav-btn[onclick*="${tabName}"]`);
    if (clickedBtn) clickedBtn.classList.add('active');

    if (tabName === 'manage-admins') loadAdminAccounts();
    if (tabName === 'create-admin') loadDeptCapacity();
}


// ============ LOGOUT ============
if (logoutBtn) logoutBtn.addEventListener('click', function () {
    if (confirm("Are you sure you want to logout?")) {
        if (uploadForm) uploadForm.reset();
        sessionStorage.clear();
        window.location.href = window.location.pathname.includes('/pages/') ? '../index.php' : 'index.php';
    }
});


// ============ ADMIN LOGOUT ============
if (adminLogoutBtn) {
    adminLogoutBtn.addEventListener('click', function () {
        if (confirm("Are you sure you want to logout?")) {
            sessionStorage.clear();
            switchView('login');
            showToast('Admin logged out.', 'info');
        }
    });
}


// ============ NEW DASHBOARD LOGIC (MULTI-PORTAL) ============

// --- SUPERVISOR DASHBOARD ---
async function initSupervisorDashboard() {
    const token = sessionStorage.getItem('adminToken');
    const dept = sessionStorage.getItem('adminDept');
    const name = sessionStorage.getItem('adminName');

    if (!token || !dept) { window.location.href = 'index.php'; return; }

    const nameDisplay = document.getElementById('supervisorNameDisplay');
    const deptDisplay = document.getElementById('supervisorDeptDisplay');
    if (nameDisplay) nameDisplay.textContent = name;
    if (deptDisplay) deptDisplay.textContent = dept;

    // Load Vacancies
    loadSupervisorVacancies(dept, token);
    // Load Applicants
    loadSupervisorApplicants(dept, token);

    // Filter listeners
    const filterStatus = document.getElementById('filterApplicantStatus');
    const filterType = document.getElementById('filterApplicantType');
    if (filterStatus) filterStatus.addEventListener('change', () => loadSupervisorApplicants(dept, token));
    if (filterType) filterType.addEventListener('change', () => loadSupervisorApplicants(dept, token));

    // Handle Vacancy Creation
    const vacancyForm = document.getElementById('createVacancyForm');
    if (vacancyForm) {
        vacancyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = vacancyForm.querySelector('button[type="submit"]');
            btn.disabled = true;
            try {
                const response = await fetch(`${API_BASE}/vacancies.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
                    body: JSON.stringify({
                        action: 'create',
                        title: document.getElementById('vacancyTitle').value,
                        description: document.getElementById('vacancyDesc').value,
                        skills: document.getElementById('vacancySkills').value,
                        positions_count: document.getElementById('vacancyCount').value,
                        vacancy_type: document.getElementById('vacancyType') ? document.getElementById('vacancyType').value : 'attachment',
                        deadline_at: document.getElementById('vacancyDeadline') && document.getElementById('vacancyDeadline').value
                            ? document.getElementById('vacancyDeadline').value.replace('T', ' ') + ':00'
                            : null
                    })
                });
                const data = await response.json();
                if (data.success) {
                    showToast('Vacancy requested successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('createVacancyModal')).hide();
                    vacancyForm.reset();
                    loadSupervisorVacancies(dept, token);
                } else { showToast(data.message, 'error'); }
            } catch (e) { showToast('Error: ' + e.message, 'error'); }
            finally { btn.disabled = false; }
        });
    }
}

async function loadSupervisorVacancies(dept, token) {
    try {
        const res = await fetch(`${API_BASE}/vacancies.php?department=${encodeURIComponent(dept)}&status=all`, {
            headers: { 'Authorization': `Bearer ${token}` } // Although GET usually public, for 'all' status we might need auth or just filter
        });
        const data = await res.json();
        const list = document.getElementById('vacanciesList');
        document.getElementById('statVacancies').textContent = data.vacancies ? data.vacancies.length : 0;

        let html = '<table class="table table-hover"><thead><tr><th>Title</th><th>Positions</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead><tbody>';
        (data.vacancies || []).forEach(v => {
            const badge = v.status === 'approved' ? 'bg-success' : (v.status === 'pending' ? 'bg-warning' : 'bg-secondary');
            let actions = '';
            if (v.status !== 'closed' && v.status !== 'rejected') {
                actions += `<button class="btn btn-sm btn-outline-warning me-1" onclick="endSupervisorVacancy(${v.id}, '${v.title.replace(/'/g, "\\'")}')" title="Mark Over"><i class="fas fa-power-off"></i></button>`;
            }
            actions += `<button class="btn btn-sm btn-outline-danger" onclick="deleteSupervisorVacancy(${v.id}, '${v.title.replace(/'/g, "\\'")}')" title="Delete Vacancy"><i class="fas fa-trash"></i></button>`;
            
            html += `<tr>
                <td>${v.title}</td>
                <td>${v.positions_count}</td>
                <td><span class="badge ${badge}">${v.status}</span></td>
                <td>${new Date(v.created_at).toLocaleDateString()}</td>
                <td>${actions}</td>
            </tr>`;
        });
        html += '</tbody></table>';
        list.innerHTML = html;
    } catch (e) { console.error(e); }
}

async function endSupervisorVacancy(id, title) {
    if (!confirm(`Are you sure you want to mark the vacancy "${title}" as over?`)) return;
    const token = sessionStorage.getItem('adminToken');
    const dept = sessionStorage.getItem('adminDept');
    try {
        const res = await fetch(`${API_BASE}/vacancies.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({ action: 'end', vacancy_id: id })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            loadSupervisorVacancies(dept, token);
        } else {
            showToast(data.error || 'Failed to end vacancy', 'danger');
        }
    } catch (e) { console.error(e); showToast('Network error', 'danger'); }
}

async function deleteSupervisorVacancy(id, title) {
    if (!confirm(`Are you sure you want to completely delete the vacancy "${title}"?`)) return;
    const token = sessionStorage.getItem('adminToken');
    const dept = sessionStorage.getItem('adminDept');
    try {
        const res = await fetch(`${API_BASE}/vacancies.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({ action: 'delete', vacancy_id: id })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            loadSupervisorVacancies(dept, token);
        } else {
            showToast(data.error || 'Failed to delete vacancy', 'danger');
        }
    } catch (e) { console.error(e); showToast('Network error', 'danger'); }
}

// Current supervisor applicants page state (Feature #11 pagination)
let _supervisorCurrentPage = 1;
window.supervisorApplicantsPage = function(p) {
    _supervisorCurrentPage = p;
    const dept = sessionStorage.getItem('adminDept');
    const token = sessionStorage.getItem('adminToken');
    loadSupervisorApplicants(dept, token);
};

async function loadSupervisorApplicants(dept, token) {
    try {
        const statusFilter = document.getElementById('filterApplicantStatus')?.value || 'all';
        const typeFilter = document.getElementById('filterApplicantType')?.value || 'all';

        // Build query — send status/type as server-side filters when set, otherwise all and filter client-side for stat counts
        let url = `${API_BASE}/get-submissions.php?department=${encodeURIComponent(dept)}&page=${_supervisorCurrentPage}&per_page=20`;
        const res = await fetch(url, { headers: { 'Authorization': `Bearer ${token}` } });
        const data = await res.json();
        let submissions = data.submissions || [];
        const list = document.getElementById('applicantsList');

        // Client-side filter for type (server doesn't support it yet)
        if (statusFilter !== 'all') submissions = submissions.filter(s => s.status === statusFilter);
        if (typeFilter !== 'all') submissions = submissions.filter(s => s.application_type === typeFilter);

        const pendingEl = document.getElementById('statPending');
        const selectedEl = document.getElementById('statSelected');
        if (pendingEl) pendingEl.textContent = submissions.filter(s => ['applied', 'pending'].includes(s.status)).length;
        if (selectedEl) selectedEl.textContent = submissions.filter(s => ['accepted', 'deployed', 'ongoing'].includes(s.status)).length;

        if (!list) return;
        if (submissions.length === 0) {
            list.innerHTML = '<div class="text-center text-muted py-3">No applications found matching the filters.</div>';
            renderPagination('supervisorPaginationContainer', null, 'supervisorApplicantsPage');
            return;
        }

        let html = '<table id="supervisorApplicantsTable" class="table table-hover align-middle"><thead class="table-light"><tr><th style="width:32px;"><input type="checkbox" class="form-check-input bulk-select-all" onchange="bulkToggleAll(this)"></th><th>Name</th><th>Vacancy</th><th>Institution</th><th>Status</th><th>Assignment</th><th>Action</th></tr></thead><tbody>';
        submissions.forEach(s => {
            const assignInfo = s.assigned_role ? `<small>${s.assigned_role}<br>${s.assigned_station || ''}</small>` : '<small class="text-muted">—</small>';
            const selectable = ['applied', 'pending', 'accepted', 'deployed'].includes(s.status);
            let actions = `<button class="btn btn-sm btn-outline-primary" onclick="viewApplicantDetails(${s.id})">View</button> `;
            if (s.status === 'accepted') {
                actions += `<button class="btn btn-sm btn-success" onclick="openAssignModal(${s.id}, '${s.full_name.replace(/'/g, "\'")}')" ><i class="fas fa-user-check me-1"></i>Assign &amp; Deploy</button>`;
            }
            if (s.status === 'deployed') {
                actions += `<button class="btn btn-sm btn-dark" onclick="supervisorUpdateStatus(${s.id}, 'ongoing', '${token}')"><i class="fas fa-play me-1"></i>Start</button>`;
            }
            if (s.status === 'ongoing') {
                actions += `<button class="btn btn-sm btn-success mt-1" onclick="supervisorUpdateStatus(${s.id}, 'completed', '${token}')"><i class="fas fa-check-circle me-1"></i>Complete</button>`;
            }
            html += `<tr>
                <td>${selectable ? `<input type="checkbox" class="form-check-input bulk-cb" value="${s.id}" onchange="bulkHandleRow(this)">` : ''}</td>
                <td>${s.full_name}<br><small class="text-muted">${s.national_id}</small></td>
                <td>${s.vacancy_title || '-'}<br><small class="badge bg-light text-dark">${s.application_type || 'attachment'}</small></td>
                <td>${s.institution_name || '-'}</td>
                <td>${getStatusBadge(s.status)}</td>
                <td>${assignInfo}</td>
                <td>${actions}</td>
            </tr>`;
        });
        html += '</tbody></table>';
        list.innerHTML = html;

        // Render pagination controls (#11)
        if (typeof renderPagination === 'function') {
            renderPagination('supervisorPaginationContainer', data.pagination || null, 'supervisorApplicantsPage');
        }
    } catch (e) { console.error(e); }
}


// ============ SUPERVISOR STATUS UPDATE ============
window.supervisorUpdateStatus = async function(id, newStatus, token) {
    let msg = `Are you sure you want to change this applicant's status to '${newStatus}'?`;
    if (newStatus === 'completed') {
        msg = `Are you sure you want to mark this internship/attachment as completed (over)?`;
    }
    if (!confirm(msg)) return;
    
    try {
        const res = await fetch(`${API_BASE}/update-submission.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({ submission_id: id, status: newStatus })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            const dept = sessionStorage.getItem('adminDept');
            loadSupervisorApplicants(dept, token);
        } else {
            showToast(data.error || 'Update failed', 'danger');
        }
    } catch (e) {
        console.error(e);
        showToast('Network error', 'danger');
    }
};

// ============ VIEW APPLICANT DETAILS (shared by Supervisor + HR) ============
async function viewApplicantDetails(submissionId) {
    const body = document.getElementById('applicantDetailBody');
    if (!body) { alert('Detail modal not found on this page.'); return; }

    body.innerHTML = '<div class="text-center py-4"><span class="spinner-border text-primary"></span><p class="mt-2">Loading details...</p></div>';
    new bootstrap.Modal(document.getElementById('applicantDetailModal')).show();

    const token = sessionStorage.getItem('adminToken');
    try {
        const res = await fetch(`${API_BASE}/get-submissions.php?id=${submissionId}`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();

        if (!data.success || !data.submission) {
            body.innerHTML = '<div class="alert alert-danger">Failed to load submission details.</div>';
            return;
        }

        const s = data.submission;
        const docs = data.documents || [];
        const history = data.review_history || [];

        let html = `
        <div class="print-header">
            <div>
                <strong>Vuka Attachment &amp; Internship Portal</strong><br>
                <small>Applicant Detail — Printed ${new Date().toLocaleDateString()}</small>
            </div>
            <div><small>Ref: VKP/${(s.department_applied || 'GEN').toUpperCase()}/${s.id}</small></div>
        </div>
        <div class="d-flex justify-content-end mb-2 no-print">
            <button class="btn btn-outline-secondary btn-sm" onclick="printApplicantDetail()">
                <i class="fas fa-print me-1"></i>Print / Save as PDF
            </button>
        </div>
        <div class="row">
            <div class="col-md-6">
                <h6 class="fw-bold text-muted text-uppercase small">Personal Info</h6>
                <table class="table table-sm">
                    <tr><td class="fw-bold">Full Name</td><td>${s.full_name}</td></tr>
                    <tr><td class="fw-bold">National ID</td><td>${s.national_id}</td></tr>
                    <tr><td class="fw-bold">Email</td><td>${s.email}</td></tr>
                    <tr><td class="fw-bold">Status</td><td>${getStatusBadge(s.status)}</td></tr>
                    ${s.intern_pf_number ? `<tr><td class="fw-bold">PF Number</td><td><code>${s.intern_pf_number}</code></td></tr>` : ''}
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold text-muted text-uppercase small">Application Info</h6>
                <table class="table table-sm">
                    <tr><td class="fw-bold">Institution</td><td>${s.institution_name || '—'}</td></tr>
                    <tr><td class="fw-bold">Course</td><td>${s.course_applying || '—'}</td></tr>
                    <tr><td class="fw-bold">Department</td><td>${s.department_applied || '—'}</td></tr>
                    <tr><td class="fw-bold">Duration</td><td>${s.duration || '—'}</td></tr>
                    <tr><td class="fw-bold">Insurance</td><td>${s.insurance_cover || '—'}</td></tr>
                    <tr><td class="fw-bold">Submitted</td><td>${s.submitted_at ? new Date(s.submitted_at).toLocaleString() : '—'}</td></tr>
                </table>
            </div>
        </div>`;

        if (docs.length > 0) {
            html += '<hr><h6 class="fw-bold text-muted text-uppercase small">Uploaded Documents</h6>';
            html += '<div class="list-group mb-3">';
            docs.forEach(d => {
                const icon = d.mime_type && d.mime_type.includes('pdf') ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary';
                html += `<a href="${API_BASE}/../uploads/${d.file_path || ''}" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="fas ${icon} me-2"></i>
                    <span>${d.original_filename || d.document_type}</span>
                </a>`;
            });
            html += '</div>';
        }

        if (s.rejection_reason) {
            html += `<div class="alert alert-danger"><strong>Rejection Reason:</strong> ${s.rejection_reason}</div>`;
        }

        if (history.length > 0) {
            html += '<hr><h6 class="fw-bold text-muted text-uppercase small">Review History</h6>';
            html += '<ul class="list-group list-group-flush">';
            history.forEach(h => {
                html += `<li class="list-group-item">
                    <div class="d-flex justify-content-between">
                        <span>${getStatusBadge(h.status)} <small class="text-muted ms-2">by ${h.reviewer_pf || 'System'}</small></span>
                        <small class="text-muted">${new Date(h.reviewed_at).toLocaleString()}</small>
                    </div>
                    ${h.notes ? `<small class="text-muted d-block mt-1">${h.notes}</small>` : ''}
                </li>`;
            });
            html += '</ul>';
        }

        // ---- Tier 5 action buttons: PDF letters (#2) / interview (#3) / evaluation (#4) ----
        const _role = sessionStorage.getItem('adminRole') || '';
        const _nameArg = (s.full_name || '').replace(/'/g, "\\'");
        const _letter = { accepted: ['offer', 'Offer Letter'], rejected: ['rejection', 'Rejection Letter'], deployed: ['deployment', 'Deployment Certificate'] }[s.status];
        let _actions = '';
        if (_letter) {
            _actions += `<button class="btn btn-outline-primary btn-sm" onclick="downloadLetter(${s.id}, '${_letter[0]}')"><i class="fas fa-file-pdf me-1"></i>${_letter[1]}</button>`;
        }
        if ((s.status === 'accepted' || s.status === 'interview') && typeof openScheduleInterviewModal === 'function') {
            _actions += `<button class="btn btn-primary btn-sm" onclick="openScheduleInterviewModal(${s.id}, '${_nameArg}')"><i class="fas fa-calendar-plus me-1"></i>Schedule Interview</button>`;
        }
        if ((s.status === 'ongoing' || s.status === 'completed') && _role === 'department_supervisor' && typeof openEvaluationModal === 'function') {
            _actions += `<button class="btn btn-success btn-sm" onclick="openEvaluationModal(${s.id}, '${_nameArg}')"><i class="fas fa-clipboard-check me-1"></i>Evaluate Intern</button>`;
        }
        if (s.status === 'completed' && ['super_admin', 'hr_coordinator', 'department_supervisor'].includes(_role) && typeof viewEvaluation === 'function') {
            _actions += `<button class="btn btn-outline-primary btn-sm" onclick="viewEvaluation(${s.id})"><i class="fas fa-eye me-1"></i>View Evaluation</button>`;
        }
        if (_actions) {
            html += `<hr><div class="d-flex flex-wrap gap-2 no-print">${_actions}</div>`;
        }

        body.innerHTML = html;
    } catch (e) {
        body.innerHTML = `<div class="alert alert-danger">Error: ${e.message}</div>`;
    }
}
window.viewApplicantDetails = viewApplicantDetails;

// ============ PRINT APPLICANT DETAIL (Feature #14) ============
function printApplicantDetail() {
    window.print();
}
window.printApplicantDetail = printApplicantDetail;

// ============ PDF LETTER DOWNLOAD (Feature #2) ============
// Called from the applicant detail modal action buttons.
function downloadLetter(submissionId, type) {
    const token = sessionStorage.getItem('adminToken');
    if (!token) { showToast('Session expired. Please log in again.', 'error'); return; }
    const url = `${API_BASE}/generate-letter.php?submission_id=${encodeURIComponent(submissionId)}&type=${encodeURIComponent(type)}`;
    // Fetch as blob so the auth header is included.
    fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
        .then(res => {
            if (!res.ok) throw new Error('Server returned ' + res.status);
            return res.blob();
        })
        .then(blob => {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = type + '_letter_' + submissionId + '.pdf';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        })
        .catch(err => showToast('Failed to download letter: ' + err.message, 'error'));
}
window.downloadLetter = downloadLetter;

// --- HR DASHBOARD ---
async function initHrDashboard() {
    const token = sessionStorage.getItem('adminToken');
    if (!token) { window.location.href = 'index.php'; return; }

    document.getElementById('hrNameDisplay').textContent = sessionStorage.getItem('adminName');

    loadHrVacancies(token);
    loadHrApplicants(token);
}

async function loadHrVacancies(token) {
    const res = await fetch(`${API_BASE}/vacancies.php?status=pending`, { headers: { 'Authorization': `Bearer ${token}` } });
    const data = await res.json();
    const list = document.getElementById('pendingVacanciesList');
    document.getElementById('statPendingVacancies').textContent = data.vacancies ? data.vacancies.length : 0;

    let html = '<table class="table"><thead><tr><th>Dept</th><th>Title</th><th>Seats</th><th>Action</th></tr></thead><tbody>';
    (data.vacancies || []).forEach(v => {
        html += `<tr>
            <td>${v.department_name}</td>
            <td>${v.title}</td>
            <td>${v.positions_count}</td>
            <td>
                <button class="btn btn-sm btn-success" onclick="approveVacancy(${v.id}, '${token}')">Approve</button>
            </td>
        </tr>`;
    });
    html += '</tbody></table>';
    list.innerHTML = html;
}

async function approveVacancy(id, token) {
    if (!confirm('Approve this vacancy?')) return;
    await fetch(`${API_BASE}/vacancies.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
        body: JSON.stringify({ action: 'update_status', vacancy_id: id, status: 'approved' })
    });
    loadHrVacancies(token);
    showToast('Vacancy approved', 'success');
}

// Current HR applicants page state (Feature #11 pagination)
let _hrCurrentPage = 1;
window.hrApplicantsPage = function(p) { _hrCurrentPage = p; loadHrApplicants(sessionStorage.getItem('adminToken')); };

async function loadHrApplicants(token, page) {
    if (page) _hrCurrentPage = page;
    try {
        const url = `${API_BASE}/get-submissions.php?page=${_hrCurrentPage}&per_page=20`;
        const res = await fetch(url, { headers: { 'Authorization': `Bearer ${token}` } });
        const data = await res.json();
        const subs = data.submissions || [];

        const statTotal = document.getElementById('statTotalApplicants');
        const statPlaced = document.getElementById('statPlaced');
        if (statTotal) statTotal.textContent = data.pagination ? data.pagination.total : subs.length;
        if (statPlaced) statPlaced.textContent = subs.filter(s => ['deployed', 'ongoing'].includes(s.status)).length;

        const list = document.getElementById('applicantsList') || document.getElementById('allApplicantsList');
        if (!list) return;

        if (subs.length === 0) {
            list.innerHTML = '<div class="text-center text-muted py-3">No applications received yet.</div>';
            renderPagination('hrPaginationContainer', null, 'hrApplicantsPage');
            return;
        }

        let html = '<table id="hrApplicantsTable" class="table table-hover align-middle"><thead class="table-light"><tr><th style="width:32px;"><input type="checkbox" class="form-check-input bulk-select-all" onchange="bulkToggleAll(this)"></th><th>Name</th><th>National ID</th><th>Institution</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        subs.forEach(s => {
            const selectable = ['applied', 'pending', 'accepted', 'deployed'].includes(s.status);
            html += `<tr>
                <td>${selectable ? `<input type="checkbox" class="form-check-input bulk-cb" value="${s.id}" onchange="bulkHandleRow(this)">` : ''}</td>
                <td><strong>${s.full_name}</strong><br><small class="text-muted">${s.email}</small></td>
                <td>${s.national_id}</td>
                <td>${s.institution_name || '—'}</td>
                <td>${s.department_applied || '—'}</td>
                <td>${getStatusBadge(s.status)}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="viewApplicantDetails(${s.id})"><i class="fas fa-eye"></i></button>
                        ${['applied', 'pending'].includes(s.status) ? `<button class="btn btn-outline-success" onclick="hrUpdateStatus(${s.id}, 'accepted', '${token}')"><i class="fas fa-check"></i> Accept</button>` : ''}
                        ${['applied', 'pending'].includes(s.status) ? `<button class="btn btn-outline-danger" onclick="hrUpdateStatus(${s.id}, 'rejected', '${token}')"><i class="fas fa-times"></i> Reject</button>` : ''}
                        ${s.status === 'accepted' ? `<button class="btn btn-outline-primary" onclick="hrUpdateStatus(${s.id}, 'deployed', '${token}')"><i class="fas fa-building"></i> Deploy</button>` : ''}
                        ${s.status === 'deployed' ? `<button class="btn btn-outline-dark" onclick="hrUpdateStatus(${s.id}, 'ongoing', '${token}')"><i class="fas fa-play"></i> Start</button>` : ''}
                    </div>
                </td>
            </tr>`;
        });
        html += '</tbody></table>';
        list.innerHTML = html;

        // Render pagination controls
        if (typeof renderPagination === 'function') {
            renderPagination('hrPaginationContainer', data.pagination || null, 'hrApplicantsPage');
        }
    } catch (e) {
        console.error('Error loading HR applicants:', e);
    }
}

async function hrUpdateStatus(submissionId, newStatus, token) {
    let reason = '';
    if (newStatus === 'rejected') {
        reason = prompt('Please enter the rejection reason:');
        if (!reason) return;
    }
    if (!confirm(`Are you sure you want to change status to "${newStatus}"?`)) return;

    try {
        const res = await fetch(`${API_BASE}/update-submission.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({ submission_id: submissionId, status: newStatus, rejection_reason: reason })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || `Status updated to ${newStatus}`, 'success');
            loadHrApplicants(token);
        } else {
            showToast(data.message || data.error || 'Update failed', 'error');
        }
    } catch (e) { showToast('Error: ' + e.message, 'error'); }
}


// --- STUDENT DASHBOARD ---
function getStatusBadge(status) {
    const map = {
        'not_applied': { label: 'Not Applied', cls: 'bg-secondary' },
        'applied': { label: 'Applied', cls: 'bg-info text-dark' },
        'pending': { label: 'Pending', cls: 'bg-warning text-dark' },
        'pending_review': { label: 'Pending', cls: 'bg-warning text-dark' },
        'accepted': { label: 'Accepted', cls: 'bg-success' },
        'approved': { label: 'Accepted', cls: 'bg-success' },
        'rejected': { label: 'Rejected', cls: 'bg-danger' },
        'deployed': { label: 'Deployed', cls: 'bg-primary' },
        'ongoing': { label: 'Ongoing', cls: 'bg-dark' },
        'interview': { label: 'Interview', cls: 'bg-info text-dark' },
        'completed': { label: 'Completed', cls: 'bg-success' },
        'withdrawn': { label: 'Withdrawn', cls: 'bg-secondary' }
    };
    const info = map[status] || map['not_applied'];
    return `<span class="badge ${info.cls}">${info.label}</span>`;
}

async function initStudentDashboard() {
    const token = sessionStorage.getItem('userToken');
    const userId = sessionStorage.getItem('userId');
    const name = sessionStorage.getItem('userName');
    const nationalId = sessionStorage.getItem('currentUser');

    if (!token) { window.location.href = '../index.php'; return; }

    // Populate profile sidebar
    document.getElementById('studentNameDisplay').textContent = name;
    document.getElementById('profileName').textContent = name;
    if (nationalId) {
        document.getElementById('profileId').textContent = 'ID: ' + nationalId;
        const nidEl = document.getElementById('profileNationalId');
        if (nidEl) nidEl.textContent = nationalId;
    }

    // Load Opportunities
    try {
        const vRes = await fetch(`${API_BASE}/vacancies.php?status=approved`);
        const vData = await vRes.json();
        const vList = document.getElementById('vacanciesListContainer');

        if (!vData.vacancies || vData.vacancies.length === 0) {
            vList.innerHTML = `<div class="empty-state text-center py-5">
                <i class="fas fa-inbox" style="font-size:3rem; color: var(--c-border);"></i>
                <h6 class="mt-3" style="color: var(--c-slate);">No vacancies yet</h6>
                <p class="small" style="color: var(--c-slate);">Check back later or contact HR.</p>
            </div>`;
        } else {
            let vHtml = '<div class="list-group">';
            (vData.vacancies || []).forEach(v => {
                const typeBadge = v.vacancy_type === 'internship' ? '<span class="badge bg-info text-dark ms-2">Internship</span>' : '<span class="badge bg-secondary ms-2">Attachment</span>';
                const deadlineHtml = v.deadline_at
                    ? `<div class="small fw-semibold mt-1 vacancy-countdown" data-deadline="${v.deadline_at}"></div>`
                    : '';
                vHtml += `<div class="list-group-item list-group-item-action flex-column align-items-start vacancy-card">
                    <div class="d-flex w-100 justify-content-between">
                        <h5 class="mb-1">${v.title}${typeBadge}</h5>
                        <small class="text-muted">${v.positions_count} Positions</small>
                    </div>
                    <p class="mb-1">${v.description}</p>
                    <small class="text-primary">${v.department_name} Dept</small>
                    ${deadlineHtml}
                    <button class="btn btn-sm btn-primary float-end mt-n3" onclick="openApplyModal(${v.id}, '${v.title.replace(/'/g, "\\'")}', '${(v.department_name || '').replace(/'/g, "\\'")}', '${v.vacancy_type || 'attachment'}')">Apply Now</button>
                </div>`;
            });
            vHtml += '</div>';
            vList.innerHTML = vHtml;
            // Initialise search now that items are rendered
            if (typeof initVacancySearch === 'function') initVacancySearch();
            if (typeof initVacancyCountdowns === 'function') initVacancyCountdowns();
        }
    } catch (e) {
        console.error('Error loading vacancies:', e);
    }

    // Load History & determine status
    let overallStatus = 'not_applied';
    let institution = '—';
    let pfNumber = null;

    if (userId) {
        try {
            const hRes = await fetch(`${API_BASE}/get-submissions.php?user_id=${userId}`, { headers: { 'Authorization': `Bearer ${token}` } });
            const hData = await hRes.json();
            const hList = document.getElementById('applicationHistoryList');
            const submissions = hData.submissions || [];

            if (submissions.length > 0) {
                // Use the most recent submission's status as overall status
                const latest = submissions[0]; // already sorted by submitted_at DESC
                overallStatus = latest.status || latest.placement_status || 'applied';
                institution = latest.institution_name || '—';
                pfNumber = latest.intern_pf_number || null;

                let hHtml = '<ul class="list-group">';
                submissions.forEach(s => {
                    const canWithdraw = ['applied', 'pending'].includes(s.status);
                    const withdrawBtn = canWithdraw
                        ? `<button class="btn btn-outline-danger btn-sm ms-2" onclick="withdrawApplication(${s.id})"><i class="fas fa-undo me-1"></i>Withdraw</button>`
                        : '';
                    hHtml += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${s.vacancy_title || s.department_applied}</strong>
                            <br><small class="text-muted">${new Date(s.submitted_at).toLocaleDateString()}</small>
                        </div>
                        <div class="d-flex align-items-center">
                            ${getStatusBadge(s.status || s.placement_status)}
                            ${withdrawBtn}
                        </div>
                    </li>`;
                });
                hHtml += '</ul>';
                hList.innerHTML = hHtml;

                // Feature #4 — load evaluation for the latest submission
                if (typeof loadStudentEvaluation === 'function') {
                    loadStudentEvaluation(latest.id);
                }

                // Feature #3 — load interview section
                if (typeof loadStudentInterview === 'function') {
                    loadStudentInterview();
                }
            } else {
                hList.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-folder-open fa-2x mb-2 d-block opacity-25"></i>No applications yet.</div>';
            }
        } catch (e) {
            console.error('Error loading history:', e);
        }
    }

    // Update sidebar status
    const statusEl = document.getElementById('overallStatus');
    if (statusEl) statusEl.outerHTML = getStatusBadge(overallStatus);

    // Update visual status timeline
    if (typeof updateStatusTimeline === 'function') {
        updateStatusTimeline(overallStatus);
    }

    const instEl = document.getElementById('profileInstitution');
    if (instEl) instEl.textContent = institution;

    // Show PF number if assigned
    if (pfNumber) {
        const pfRow = document.getElementById('pfNumberRow');
        const pfEl = document.getElementById('profilePfNumber');
        if (pfRow) pfRow.style.display = 'block';
        if (pfEl) pfEl.textContent = pfNumber;
    }
}

let currentApplyDept = '';
let currentApplyType = 'attachment';
function openApplyModal(id, title, deptName, vacancyType) {
    document.getElementById('vacancyId').value = id;
    document.getElementById('applyVacancyTitle').textContent = title;
    currentApplyDept = deptName || '';
    currentApplyType = vacancyType || 'attachment';
    // Auto-fill user info
    const name = sessionStorage.getItem('userName') || '...';
    const nid = sessionStorage.getItem('currentUser') || '...';
    const nameEl = document.getElementById('applyAsName');
    const idEl = document.getElementById('applyAsId');
    if (nameEl) nameEl.textContent = name;
    if (idEl) idEl.textContent = nid;
    new bootstrap.Modal(document.getElementById('applyModal')).show();
}


// ============ APPLICATION FORM SUBMIT (Student Dashboard) ============
const applicationForm = document.getElementById('applicationForm');
if (applicationForm) {
    applicationForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const token = sessionStorage.getItem('userToken');
        const nationalId = sessionStorage.getItem('currentUser');
        const fullName = sessionStorage.getItem('userName');
        const email = sessionStorage.getItem('userEmail') || '';

        if (!token || !nationalId) {
            showToast('Session expired. Please log in again.', 'error');
            return;
        }

        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

        try {
            const formData = new FormData();
            formData.append('national_id', nationalId);
            formData.append('full_name', fullName);
            formData.append('email', email);
            formData.append('vacancy_id', document.getElementById('vacancyId').value);
            formData.append('course_applying', document.getElementById('courseName').value.trim());
            formData.append('institution_name', document.getElementById('institutionName').value.trim());
            formData.append('duration', document.getElementById('duration').value.trim());
            formData.append('insurance_cover', document.getElementById('insurance').value.trim());
            formData.append('department_applied', currentApplyDept || '');
            formData.append('application_type', currentApplyType || 'attachment');

            // Map file inputs to API field names
            const fileAppLetter = document.getElementById('fileAppLetter');
            const fileSchoolLetter = document.getElementById('fileSchoolLetter');
            const fileInsurance = document.getElementById('fileInsurance');
            const fileID = document.getElementById('fileID');

            if (fileAppLetter && fileAppLetter.files[0]) formData.append('application_letter', fileAppLetter.files[0]);
            if (fileSchoolLetter && fileSchoolLetter.files[0]) formData.append('campus_letter', fileSchoolLetter.files[0]);
            if (fileInsurance && fileInsurance.files[0]) formData.append('insurance_cert', fileInsurance.files[0]);
            if (fileID && fileID.files[0]) formData.append('national_id_copy', fileID.files[0]);

            const response = await fetch(`${API_BASE}/submit-application.php`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}` },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showToast(data.message || 'Application submitted successfully!', 'success');
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('applyModal'));
                if (modal) modal.hide();
                applicationForm.reset();
                // Refresh dashboard to show new status
                initStudentDashboard();
            } else {
                showToast(data.message || data.error || 'Submission failed.', 'error');
            }
        } catch (error) {
            showToast('Error: ' + error.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Submit Application';
        }
    });
}

// Global scope helper for placement
window.updatePlacementStatus = async function (id, status, token) {
    // TODO: Add API endpoint for this or reuse vacancies endpoint with different action
    // For now, assume a placeholder endpoint or logic
    alert('Feature pending backend endpoint for placement status update.');
};

if (searchInput) searchInput.addEventListener('keyup', filterSubmissions);
if (filterSelect) filterSelect.addEventListener('change', filterSubmissions);

function filterSubmissions() {
    const term = (searchInput ? searchInput.value : '').toLowerCase();
    const status = filterSelect ? filterSelect.value : '';
    let filtered = currentSubmissions.filter(s => {
        const matchSearch = !term ||
            (s.full_name || '').toLowerCase().includes(term) ||
            (s.national_id || '').toLowerCase().includes(term) ||
            (s.email || '').toLowerCase().includes(term);
        const matchStatus = !status || s.status === status;
        return matchSearch && matchStatus;
    });
    displaySubmissions(filtered);
}


// ============ REVIEW SUBMISSION ============
function reviewSubmission(id) { viewSubmissionDetails(id); loadReviewHistory(id); }

function viewSubmissionDetails(submissionId) {
    const s = currentSubmissions.find(x => x.id == submissionId);
    if (!s) { showToast('Submission not found', 'error'); return; }
    currentViewingSubmissionId = submissionId;

    document.getElementById('modalApplicantName').textContent = s.full_name;
    document.getElementById('modalFullName').textContent = s.full_name;
    document.getElementById('modalIdNumber').textContent = s.national_id;
    document.getElementById('modalEmail').textContent = s.email;
    document.getElementById('modalRegDate').textContent = new Date(s.submitted_at).toLocaleDateString();
    document.getElementById('modalDuration').textContent = s.duration || '-';
    document.getElementById('modalInsurance').textContent = s.insurance_cover || '-';
    document.getElementById('modalCourse').textContent = s.course_applying || '-';
    document.getElementById('modalInstitution').textContent = s.institution_name || '-';
    document.getElementById('modalDepartment').textContent = s.department_applied || '-';
    document.getElementById('modalSubmitDate').textContent = new Date(s.submitted_at).toLocaleString();
    document.getElementById('statusDropdown').value = s.status;

    const docsC = document.getElementById('modalDocuments');
    if (docsC && s.documents && s.documents.length > 0) {
        let dh = '';
        s.documents.forEach(doc => {
            dh += `<div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                <span><i class="fas fa-file me-2"></i>${doc.original_filename || doc.document_type}</span>
                <div>
                    <button class="btn btn-sm btn-outline-info me-1" onclick="viewDocument(${doc.id})"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-outline-primary" onclick="downloadDocument(${doc.id})"><i class="fas fa-download"></i></button>
                </div>
            </div>`;
        });
        docsC.innerHTML = dh;
    } else if (docsC) { docsC.innerHTML = '<p class="text-muted">No documents available</p>'; }

    toggleRejectionReason();
    detailsModal.show();
}

function toggleRejectionReason() {
    const status = document.getElementById('statusDropdown').value;
    const rc = document.getElementById('rejectionReasonContainer');
    const dr = document.getElementById('displayRejectionReason');
    if (rc) rc.style.display = status === 'rejected' ? 'block' : 'none';
    if (dr) dr.style.display = 'none';
}

const statusDropdown = document.getElementById('statusDropdown');
if (statusDropdown) statusDropdown.addEventListener('change', toggleRejectionReason);

async function loadReviewHistory(submissionId) {
    const token = sessionStorage.getItem('adminToken');
    const hc = document.getElementById('reviewHistoryContainer');
    const hl = document.getElementById('reviewHistoryList');
    if (!hc) return;
    try {
        const response = await fetch(`${API_BASE}/get-submissions.php?id=${submissionId}`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await response.json();
        if (!data.success || !data.review_history || data.review_history.length === 0) { hc.style.display = 'none'; return; }
        hc.style.display = 'block';
        let html = '';
        data.review_history.forEach(r => {
            html += `<div class="mb-2 pb-2 border-bottom">
                <small class="text-muted d-block"><strong>Reviewer:</strong> ${r.reviewer_pf || '-'}</small>
                <small class="text-muted d-block"><strong>Date:</strong> ${new Date(r.reviewed_at).toLocaleString()}</small>
                <small class="text-muted d-block"><strong>Status:</strong> ${r.status || '-'}</small>
                <div style="background:#f8f9fa;padding:8px;border-left:3px solid var(--county-green);margin-top:5px;">
                    <small>${r.notes || '-'}</small>
                </div>
            </div>`;
        });
        hl.innerHTML = html;
    } catch (e) { hc.style.display = 'none'; }
}


// ============ SAVE REVIEW NOTES ============
if (saveReviewNotesBtn) {
    saveReviewNotesBtn.addEventListener('click', async function () {
        if (!currentViewingSubmissionId) return;
        const notes = document.getElementById('reviewNotes').value.trim();
        if (!notes) { showToast('Please enter review notes.', 'warning'); return; }
        const token = sessionStorage.getItem('adminToken');
        try {
            const response = await fetch(`${API_BASE}/update-submission.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
                body: JSON.stringify({ submission_id: currentViewingSubmissionId, review_notes: notes })
            });
            const data = await response.json();
            if (data.success) {
                showToast('Review notes saved!', 'success');
                document.getElementById('reviewNotes').value = '';
                loadReviewHistory(currentViewingSubmissionId);
            } else { showToast(data.message || data.error || 'Error saving notes', 'error'); }
        } catch (error) { showToast('Error: ' + error.message, 'error'); }
    });
}


// ============ UPDATE STATUS ============
if (updateStatusBtn) {
    updateStatusBtn.addEventListener('click', async function () {
        if (!currentViewingSubmissionId) return;
        const newStatus = document.getElementById('statusDropdown').value;
        const rejReason = document.getElementById('rejectionReason').value.trim();
        const token = sessionStorage.getItem('adminToken');
        const pf = sessionStorage.getItem('adminPF');
        if (newStatus === 'rejected' && !rejReason) { showToast('Please provide a rejection reason.', 'warning'); return; }

        try {
            const response = await fetch(`${API_BASE}/update-submission.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
                body: JSON.stringify({ submission_id: currentViewingSubmissionId, status: newStatus, rejection_reason: rejReason, pf_number: pf, department: sessionStorage.getItem('adminDept') })
            });
            const data = await response.json();
            if (data.success) {
                showToast(`Status updated to "${newStatus}"`, 'success');
                detailsModal.hide();
                loadAdminDashboard();
            } else { showToast(data.message || data.error || 'Error updating', 'error'); }
        } catch (error) { showToast('Error: ' + error.message, 'error'); }
    });
}


// ============ DELETE SUBMISSION ============
if (deleteSubmissionBtn) {
    deleteSubmissionBtn.addEventListener('click', async function () {
        if (!currentViewingSubmissionId) return;
        if (!confirm('Permanently delete this submission?')) return;
        const token = sessionStorage.getItem('adminToken');
        try {
            const response = await fetch(`${API_BASE}/delete-submission.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
                body: JSON.stringify({ submission_id: currentViewingSubmissionId })
            });
            const data = await response.json();
            if (data.success) { showToast('Submission deleted.', 'success'); detailsModal.hide(); loadAdminDashboard(); }
            else { showToast(data.message || data.error || 'Error deleting', 'error'); }
        } catch (error) { showToast('Error: ' + error.message, 'error'); }
    });
}


// ============ DOCUMENT DOWNLOAD/VIEW ============
async function downloadDocument(documentId) {
    const token = sessionStorage.getItem('adminToken');
    try {
        const response = await fetch(`${API_BASE}/download-document.php?id=${documentId}`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        if (response.ok) {
            const blob = await response.blob();
            const cd = response.headers.get('content-disposition');
            let filename = 'document';
            if (cd) { const m = cd.match(/filename="?([^"]+)"?/); if (m) filename = m[1]; }
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = filename;
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            showToast('Document downloaded.', 'success');
        } else { showToast('Error downloading file', 'error'); }
    } catch (error) { showToast('Download error: ' + error.message, 'error'); }
}

async function viewDocument(documentId) {
    const token = sessionStorage.getItem('adminToken');
    try {
        const response = await fetch(`${API_BASE}/download-document.php?id=${documentId}`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        if (response.ok) {
            const blob = await response.blob();
            if (currentPreviewBlobUrl) window.URL.revokeObjectURL(currentPreviewBlobUrl);
            const url = window.URL.createObjectURL(blob);
            currentPreviewBlobUrl = url;
            const iframe = document.getElementById('docPreviewIframe');
            const img = document.getElementById('docPreviewImg');
            const fallback = document.getElementById('docPreviewFallback');
            const dlBtn = document.getElementById('docPreviewDownloadBtn');
            if (dlBtn) dlBtn.onclick = () => downloadDocument(documentId);

            if (blob.type === 'application/pdf') {
                iframe.style.display = 'block'; img.style.display = 'none'; fallback.style.display = 'none';
                iframe.src = url;
            } else if (blob.type.startsWith('image/')) {
                iframe.style.display = 'none'; fallback.style.display = 'none';
                img.style.display = 'block'; img.src = url;
            } else {
                iframe.style.display = 'none'; img.style.display = 'none';
                fallback.style.display = 'block';
            }
            if (docPreviewModal) docPreviewModal.show();
        } else { showToast('Error loading document', 'error'); }
    } catch (error) { showToast('View error: ' + error.message, 'error'); }
}




// ============ "OTHER" FIELD HANDLERS ============
function setupOtherField(selectId, inputId, storageKey) {
    const sel = document.getElementById(selectId);
    const inp = document.getElementById(inputId);
    if (!sel || !inp) return;
    function toggle() {
        if (sel.value === 'Other' || sel.value === 'other') { inp.style.display = 'block'; inp.focus(); }
        else { inp.style.display = 'none'; }
    }
    const saved = sessionStorage.getItem(storageKey);
    if (saved) {
        const opts = Array.from(sel.options).map(o => o.value);
        if (opts.includes(saved)) sel.value = saved;
        else { sel.value = 'Other'; inp.style.display = 'block'; inp.value = saved; }
    }
    sel.addEventListener('change', function () {
        if (this.value !== 'Other' && this.value !== 'other') sessionStorage.setItem(storageKey, this.value);
        toggle();
    });
    inp.addEventListener('input', function () { sessionStorage.setItem(storageKey, this.value.trim()); });
}

setupOtherField('courseApplying', 'courseOtherInput', 'currentCourseName');
setupOtherField('institutionName', 'institutionOtherInput', 'currentInstitutionName');
setupOtherField('departmentApplying', 'departmentOtherInput', 'currentDepartmentName');

function getCourseValue() {
    const s = document.getElementById('courseApplying');
    const o = document.getElementById('courseOtherInput');
    return (o && o.style.display !== 'none' && o.value.trim()) ? o.value.trim() : s.value;
}
function getInstitutionValue() {
    const s = document.getElementById('institutionName');
    const o = document.getElementById('institutionOtherInput');
    return (o && o.style.display !== 'none' && o.value.trim()) ? o.value.trim() : s.value;
}
function getDepartmentValue() {
    const s = document.getElementById('departmentApplying');
    const o = document.getElementById('departmentOtherInput');
    return (o && o.style.display !== 'none' && o.value.trim()) ? o.value.trim() : (s ? s.value : '');
}
function getSelectText(elementId) {
    const el = document.getElementById(elementId);
    return el ? el.options[el.selectedIndex].text : '';
}


// ============ UPLOAD FORM SUBMIT ============
if (uploadForm) uploadForm.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!uploadForm.checkValidity()) { uploadForm.classList.add('was-validated'); return; }
    if (!document.getElementById('termsCheckbox').checked) { showToast('You must accept the terms and conditions.', 'warning'); return; }

    document.getElementById('confirmFullName').textContent = document.getElementById('displayFullName').value;
    document.getElementById('confirmId').textContent = document.getElementById('displayId').value;
    document.getElementById('confirmEmail').textContent = document.getElementById('displayEmail').value;
    document.getElementById('confirmRegDate').textContent = document.getElementById('displayRegDate').value;
    document.getElementById('confirmDuration').textContent = document.getElementById('attachmentDuration').value;
    document.getElementById('confirmInsurance').textContent = getSelectText('insuranceCover');
    document.getElementById('confirmCourse').textContent = getCourseValue();
    document.getElementById('confirmInstitution').textContent = getInstitutionValue();
    document.getElementById('confirmDepartment').textContent = getDepartmentValue();
    confirmModal.show();
});

if (confirmSubmitBtn) confirmSubmitBtn.addEventListener('click', async function (e) {
    e.preventDefault();
    const btn = this;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    showLoading('Uploading your application...');

    try {
        const formData = new FormData();
        formData.append('national_id', document.getElementById('displayId').value);
        formData.append('full_name', document.getElementById('displayFullName').value);
        formData.append('email', document.getElementById('displayEmail').value);
        formData.append('duration', document.getElementById('attachmentDuration').value);
        formData.append('insurance_cover', document.getElementById('insuranceCover').value);
        formData.append('course_applying', getCourseValue());
        formData.append('institution_name', getInstitutionValue());
        formData.append('department_applied', getDepartmentValue());

        const fileIds = ['fileApplicationLetter', 'fileCampusLetter', 'fileInsuranceCert', 'fileAcademic', 'fileId'];
        const fileNames = ['application_letter', 'campus_letter', 'insurance_cert', 'academic_certs', 'national_id_copy'];
        for (let i = 0; i < fileIds.length; i++) {
            const fi = document.getElementById(fileIds[i]);
            if (fi && fi.files && fi.files[0]) formData.append(fileNames[i], fi.files[0]);
        }

        const userToken = sessionStorage.getItem('userToken');
        console.log('Submitting application...');

        const response = await fetch(`${API_BASE}/submit-application.php`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${userToken}` },
            body: formData
        });

        console.log('Response status:', response.status);
        const text = await response.text();
        console.log('Response text:', text);

        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('JSON Parse Error:', e);
            throw new Error('Server returned invalid JSON. Check console for details.');
        }

        if (data.success) {
            confirmModal.hide();
            uploadForm.reset();
            // sessionStorage.clear(); // DEBUG: Don't clear session yet
            switchView('success');
            showToast('Application submitted successfully!', 'success');
        } else { showToast(data.message || data.error || 'Submission error', 'error'); }
    } catch (error) {
        console.error('Submission Catch Error:', error);
        showToast('Error: ' + error.message, 'error');
    }
    finally { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Confirm & Submit'; hideLoading(); }
});


// ============  ====================================================

// ============ ========== SENIOR ADMIN PANEL ======================

// ============  ====================================================

async function toggleAdminStatus(adminId, newStatus) {
    const label = newStatus === 'active' ? 'activate' : 'deactivate';
    if (!confirm(`Are you sure you want to ${label} this admin?`)) return;

    const token = sessionStorage.getItem('adminToken');
    try {
        showLoading(`${label.charAt(0).toUpperCase() + label.slice(1)}ing admin...`);
        const response = await fetch(`${API_BASE}/toggle-admin-status.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({ admin_id: adminId, status: newStatus })
        });
        const data = await response.json();
        hideLoading();
        if (data.success) {
            showToast(data.message || `Admin ${label}d.`, 'success');
            loadAdminAccounts();
        } else { showToast(data.error || data.message || `Failed to ${label}`, 'error'); }
    } catch (error) { hideLoading(); showToast('Error: ' + error.message, 'error'); }
}

function loadDeptCapacity() {
    const hint = document.getElementById('deptCapacityHint');
    const deptSelect = document.getElementById('newAdminDept');
    if (!deptSelect || !hint) return;

    deptSelect.addEventListener('change', function () {
        const dept = this.value;
        if (!dept) { hint.textContent = ''; return; }
        const count = adminDeptCounts[dept] || 0;
        const remaining = 2 - count;
        if (remaining <= 0) {
            hint.textContent = `⚠ ${dept} already has 2 admins (full capacity)`;
            hint.className = 'form-text text-danger';
        } else {
            hint.textContent = `✓ ${dept}: ${count}/2 admins — ${remaining} slot${remaining > 1 ? 's' : ''} available`;
            hint.className = 'form-text text-success';
        }
    });

    if (Object.keys(adminDeptCounts).length === 0) {
        const token = sessionStorage.getItem('adminToken');
        fetch(`${API_BASE}/get-admin-accounts.php`, { headers: { 'Authorization': `Bearer ${token}` } })
            .then(r => r.json())
            .then(data => { if (data.success) adminDeptCounts = data.department_counts || {}; })
            .catch(() => { });
    }
}

// (Duplicate create admin form handler removed — handled at line ~405)


// ============ INITIALIZE ============
function initializeAdminIfNeeded() {
    if (sessionStorage.getItem('adminLoggedIn') === 'true') {
        const pf = sessionStorage.getItem('adminPF') || '';
        const role = sessionStorage.getItem('adminRole') || 'department_admin';
        const isSenior = (role === 'super_admin');

        const pfEl = document.getElementById('adminPFDisplay');
        if (pfEl) pfEl.textContent = pf;
        const nameEl = document.getElementById('adminNameDisplay');
        const deptEl = document.getElementById('adminDeptDisplay');
        if (nameEl) nameEl.textContent = sessionStorage.getItem('adminName') || '';
        if (deptEl) deptEl.textContent = sessionStorage.getItem('adminDept') || '';

        const seniorNav = document.getElementById('seniorAdminNav');
        const roleBadge = document.getElementById('adminRoleBadge');
        const panelTitle = document.getElementById('adminPanelTitle');
        if (seniorNav) seniorNav.style.display = isSenior ? 'flex' : 'none';
        if (roleBadge) roleBadge.textContent = isSenior ? 'SENIOR ADMIN' : 'DEPT ADMIN';
        if (panelTitle) panelTitle.textContent = isSenior ? 'Senior Admin Control Panel' : 'Admin Control Panel';

        switchView('admin');
        loadAdminDashboard();
        return;
    }
}


// ============ CSV EXPORT ============
function exportCSV(tableId = null, filename = 'vuka-export.csv') {
    let table;
    if (tableId) {
        table = document.getElementById(tableId);
    }
    if (!table) {
        // Fallback to first table if no ID provided or found
        table = document.querySelector('table');
    }
    if (!table) {
        showToast('No data available to export.', 'warning');
        return;
    }
    
    let csv = [];
    table.querySelectorAll('tr').forEach(row => {
        const cols = [...row.querySelectorAll('th, td')].map(c =>
            '"' + c.innerText.replace(/"/g, '""') + '"'
        );
        csv.push(cols.join(','));
    });
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
}


// ============ NOTIFICATION BELL (Feature #5) ============
function notifGetToken() {
    return sessionStorage.getItem('adminToken') || sessionStorage.getItem('userToken') || '';
}

function notifEscape(str) {
    return String(str == null ? '' : str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function notifTimeAgo(dateStr) {
    const then = new Date(String(dateStr || '').replace(' ', 'T'));
    const secs = Math.floor((Date.now() - then.getTime()) / 1000);
    if (isNaN(secs)) return '';
    if (secs < 60) return 'just now';
    const mins = Math.floor(secs / 60); if (mins < 60) return mins + 'm ago';
    const hrs = Math.floor(mins / 60); if (hrs < 24) return hrs + 'h ago';
    const days = Math.floor(hrs / 24); if (days < 7) return days + 'd ago';
    return then.toLocaleDateString();
}

async function pollNotifications() {
    const badge = document.getElementById('notifBadge');
    const list = document.getElementById('notifList');
    if (!badge || !list) return;
    const token = notifGetToken();
    if (!token) return;
    try {
        const res = await fetch(`${API_BASE}/notifications.php`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        if (!data.success) return;
        badge.textContent = data.unread_count;
        badge.classList.toggle('d-none', !data.unread_count);
        list.innerHTML = (data.notifications && data.notifications.length)
            ? data.notifications.map(n => `
                <a href="${n.link || '#'}" class="d-block px-3 py-2 border-bottom text-decoration-none ${Number(n.is_read) === 0 ? 'bg-light' : ''}" style="color:inherit;">
                    <div class="fw-semibold small">${notifEscape(n.title)}</div>
                    <div class="text-muted small">${notifEscape(n.body)}</div>
                    <div class="text-muted" style="font-size:11px;">${notifTimeAgo(n.created_at)}</div>
                </a>`).join('')
            : '<div class="p-3 text-muted small text-center">No notifications yet</div>';
    } catch (e) { /* silent — polling is best-effort */ }
}

function toggleNotifDropdown() {
    const dd = document.getElementById('notifDropdown');
    if (!dd) return;
    dd.classList.toggle('d-none');
    if (!dd.classList.contains('d-none')) {
        const token = notifGetToken();
        if (token) {
            fetch(`${API_BASE}/notifications.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
                body: JSON.stringify({ action: 'mark_read' })
            }).then(() => {
                const b = document.getElementById('notifBadge');
                if (b) b.classList.add('d-none');
            }).catch(() => {});
        }
    }
}

function initNotificationBell() {
    if (!document.getElementById('notifBell')) return;
    pollNotifications();
    setInterval(pollNotifications, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) pollNotifications(); });
    document.addEventListener('click', (e) => {
        const wrap = document.getElementById('notifBellWrapper');
        const dd = document.getElementById('notifDropdown');
        if (wrap && dd && !wrap.contains(e.target)) dd.classList.add('d-none');
    });
}
document.addEventListener('DOMContentLoaded', initNotificationBell);


// ============ BULK ACTIONS (Feature #6) ============
let bulkSelectedIds = new Set();

function bulkHandleRow(cb) {
    const id = parseInt(cb.value, 10);
    if (cb.checked) bulkSelectedIds.add(id); else bulkSelectedIds.delete(id);
    bulkUpdateBar();
}

function bulkToggleAll(masterCb) {
    document.querySelectorAll('.bulk-cb').forEach(cb => {
        cb.checked = masterCb.checked;
        const id = parseInt(cb.value, 10);
        if (masterCb.checked) bulkSelectedIds.add(id); else bulkSelectedIds.delete(id);
    });
    bulkUpdateBar();
}

function bulkClearSelection() {
    bulkSelectedIds.clear();
    document.querySelectorAll('.bulk-cb, .bulk-select-all').forEach(cb => { cb.checked = false; });
    bulkUpdateBar();
}

function bulkUpdateBar() {
    let bar = document.getElementById('bulkActionBar');
    if (!bar) {
        bar = document.createElement('div');
        bar.id = 'bulkActionBar';
        bar.className = 'd-none position-fixed bottom-0 start-0 end-0 bg-dark text-white p-3 d-flex align-items-center justify-content-between';
        bar.style.zIndex = '1055';
        bar.innerHTML = `
            <span id="bulkCount" class="fw-semibold"></span>
            <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm" onclick="bulkAction('accepted')"><i class="fas fa-check me-1"></i>Accept</button>
                <button class="btn btn-danger btn-sm" onclick="bulkAction('rejected')"><i class="fas fa-times me-1"></i>Reject</button>
                <button class="btn btn-outline-light btn-sm" onclick="bulkExportSelected()"><i class="fas fa-download me-1"></i>Export CSV</button>
                <button class="btn btn-secondary btn-sm" onclick="bulkClearSelection()">Cancel</button>
            </div>`;
        document.body.appendChild(bar);
    }
    const count = document.getElementById('bulkCount');
    if (bulkSelectedIds.size > 0) {
        bar.classList.remove('d-none');
        if (count) count.textContent = `${bulkSelectedIds.size} selected`;
    } else {
        bar.classList.add('d-none');
    }
}

async function bulkAction(status) {
    if (bulkSelectedIds.size === 0) return;
    let rejectionReason = '';
    if (status === 'rejected') {
        rejectionReason = prompt('Reason for rejecting the selected application(s):') || '';
        if (!rejectionReason.trim()) { showToast('Rejection reason is required.', 'warning'); return; }
    }
    if (!confirm(`${status.toUpperCase()} ${bulkSelectedIds.size} application(s)?`)) return;

    const token = sessionStorage.getItem('adminToken');
    try {
        const res = await fetch(`${API_BASE}/bulk-update-submissions.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({ ids: [...bulkSelectedIds], status, rejection_reason: rejectionReason })
        });
        const data = await res.json();
        if (data.success) {
            showToast(`${data.updated} updated${data.skipped ? `, ${data.skipped} skipped` : ''}.`, 'success');
            bulkClearSelection();
            // Refresh whichever applicant list is present.
            const t = sessionStorage.getItem('adminToken');
            if (typeof loadHrApplicants === 'function' && document.getElementById('hrApplicantsTable')) loadHrApplicants(t);
            if (typeof loadSupervisorApplicants === 'function' && document.getElementById('supervisorApplicantsTable')) {
                loadSupervisorApplicants(sessionStorage.getItem('adminDept'), t);
            }
        } else {
            showToast(data.error || data.message || 'Bulk update failed.', 'error');
        }
    } catch (e) {
        showToast('Bulk update error: ' + e.message, 'error');
    }
}

function bulkExportSelected() {
    // Export only the selected rows from whichever applicant table is visible.
    const table = document.getElementById('hrApplicantsTable') || document.getElementById('supervisorApplicantsTable');
    if (!table) return;
    let csv = [];
    table.querySelectorAll('tr').forEach(row => {
        const cb = row.querySelector('.bulk-cb');
        const isHeader = row.querySelector('th');
        if (!isHeader && (!cb || !cb.checked)) return; // keep header + selected rows
        const cols = [...row.querySelectorAll('th, td')]
            .filter((_, i) => i !== 0) // drop the checkbox column
            .map(c => '"' + c.innerText.replace(/"/g, '""').replace(/\s+/g, ' ').trim() + '"');
        csv.push(cols.join(','));
    });
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'selected-applicants.csv';
    a.click();
}


// ============ REUSABLE PAGINATION (Feature #11) ============
// meta = { page, per_page, total, total_pages }
// onPageChange: a GLOBAL function name (string) taking a page number.
function renderPagination(containerId, meta, onPageChangeName) {
    const el = document.getElementById(containerId);
    if (!el) return;
    if (!meta || meta.total_pages <= 1) { el.innerHTML = ''; return; }

    const page = meta.page;
    const totalPages = meta.total_pages;
    let html = '<nav><ul class="pagination pagination-sm mb-0 justify-content-center">';

    html += `<li class="page-item ${page === 1 ? 'disabled' : ''}">
                <button class="page-link" onclick="${onPageChangeName}(${page - 1})">&lsaquo;</button>
             </li>`;

    for (let p = 1; p <= totalPages; p++) {
        if (p === 1 || p === totalPages || Math.abs(p - page) <= 1) {
            html += `<li class="page-item ${p === page ? 'active' : ''}">
                        <button class="page-link" onclick="${onPageChangeName}(${p})">${p}</button>
                     </li>`;
        } else if (Math.abs(p - page) === 2) {
            html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }

    html += `<li class="page-item ${page === totalPages ? 'disabled' : ''}">
                <button class="page-link" onclick="${onPageChangeName}(${page + 1})">&rsaquo;</button>
             </li>`;
    html += '</ul></nav>';
    el.innerHTML = html;
}
window.renderPagination = renderPagination;
