// ============ CREATE ADMIN (Senior Admin) ============
if (createAdminForm) {
    createAdminForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fullName = document.getElementById('newAdminFullName').value.trim();
        const pfNumber = document.getElementById('newAdminPF').value.trim();
        const nationalId = document.getElementById('newAdminId').value.trim();
        const email = document.getElementById('newAdminEmail').value.trim();
        const dept = document.getElementById('newAdminDept').value;
        const role = document.getElementById('newAdminRole').value;
        const password = document.getElementById('newAdminPassword').value;

        if (!fullName || !pfNumber || !nationalId || !email || !dept || !role || !password) {
            showToast('Please fill in all fields.', 'warning');
            return;
        }

        if (!isValidIdNumber(nationalId)) {
            showToast('National ID must be 6 to 9 digits.', 'warning');
            return;
        }

        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';

        try {
            const token = sessionStorage.getItem('adminToken');
            const response = await fetch(`${API_BASE}/create-admin-account.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    full_name: fullName,
                    pf_number: pfNumber,
                    national_id: nationalId,
                    email: email,
                    department: dept,
                    role_id: role,
                    password: password
                })
            });
            const data = await response.json();

            if (data.success) {
                showToast(data.message, 'success');
                createAdminForm.reset();
                if (typeof loadDeptCapacity === 'function') loadDeptCapacity();
                loadAdminAccounts(); // Refresh the staff list
            } else {
                showToast(data.message || data.error || 'Failed to create admin.', 'error');
            }
        } catch (error) { showToast('Error: ' + error.message, 'error'); }
        finally { btn.disabled = false; btn.innerHTML = '<i class="fas fa-user-plus me-2"></i>Create Admin Account'; }
    });
}


// ============ ADMIN DASHBOARD ============
async function loadAdminDashboard() {
    const token = sessionStorage.getItem('adminToken');
    const department = sessionStorage.getItem('adminDept') || 'ALL';
    try {
        const response = await fetch(`${API_BASE}/get-submissions.php?department=${encodeURIComponent(department)}`, {
            method: 'GET', headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await response.json();
        if (data.success) {
            currentSubmissions = data.submissions || [];
            updateStatCards(currentSubmissions);
            displaySubmissions(currentSubmissions);
        } else { console.error('Dashboard error:', data.message || data.error); }
    } catch (error) { console.error('Dashboard fetch error:', error); }
}

function updateStatCards(submissions) {
    animateCounter(document.getElementById('totalSubmissions'), submissions.length);
    animateCounter(document.getElementById('pendingCount'), submissions.filter(s => s.status === 'pending_review').length);
    animateCounter(document.getElementById('approvedCount'), submissions.filter(s => s.status === 'approved').length);
    animateCounter(document.getElementById('rejectedCount'), submissions.filter(s => s.status === 'rejected').length);
}

function displaySubmissions(submissions) {
    const list = document.getElementById('submissionsList');
    if (!list) return; // Current admin layout shows submissions as a chart, not a table
    if (!submissions || submissions.length === 0) {
        list.innerHTML = '<div class="no-submissions"><i class="fas fa-inbox d-block"></i><p class="mt-2">No submissions found</p></div>';
        return;
    }
    let html = `<div class="table-responsive"><table class="table table-striped align-middle">
        <thead><tr>
            <th>Applicant Name</th><th>National ID</th><th class="d-none d-md-table-cell">Email</th>
            <th>Status</th><th class="d-none d-sm-table-cell">Date</th><th>Actions</th>
        </tr></thead><tbody>`;

    submissions.forEach(s => {
        const cls = { 'pending_review': 'status-pending-review', 'approved': 'status-approved', 'rejected': 'status-rejected' }[s.status] || 'status-pending-review';
        const txt = { 'pending_review': 'Pending', 'approved': 'Approved', 'rejected': 'Rejected' }[s.status] || 'Pending';
        const dt = new Date(s.submitted_at).toLocaleDateString();
        html += `<tr>
            <td><strong>${s.full_name}</strong></td><td>${s.national_id}</td>
            <td class="d-none d-md-table-cell">${s.email}</td>
            <td><span class="submission-status ${cls}">${txt}</span></td>
            <td class="d-none d-sm-table-cell">${dt}</td>
            <td><button class="btn btn-sm btn-outline-primary" onclick="reviewSubmission(${s.id})"><i class="fas fa-file-contract me-1"></i><span class="d-none d-md-inline">Review</span></button></td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    list.innerHTML = html;
}


// ============ LOAD ADMIN ACCOUNTS ============
async function loadAdminAccounts() {
    const list = document.getElementById('adminAccountsList');
    if (!list) return;

    // Show spinner
    list.innerHTML = '<div class="text-center p-3"><span class="spinner-border text-primary"></span><p class="mt-2">Loading admins...</p></div>';

    const token = sessionStorage.getItem('adminToken');
    try {
        const response = await fetch(`${API_BASE}/get-admins.php`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await response.json();

        if (data.success) {

            // Update Stat
            if (document.getElementById('statAdmins')) {
                animateCounter(document.getElementById('statAdmins'), data.admins.length);
            }

            if (!data.admins || data.admins.length === 0) {
                list.innerHTML = '<div class="alert alert-info">No admin accounts found.</div>';
                return;
            }

            let html = `<div class="table-responsive"><table id="adminAccountsTable" class="table table-hover align-middle">
                <thead class="table-light"><tr>
                    <th>Name</th><th>Role</th><th>Dept</th><th>PF Number</th><th>Email</th><th>Actions</th>
                </tr></thead><tbody>`;

            data.admins.forEach(a => {
                let roleBadge = 'bg-secondary';
                if (a.role_name === 'super_admin') roleBadge = 'bg-danger';
                else if (a.role_name === 'hr_coordinator') roleBadge = 'bg-info text-dark';
                else if (a.role_name === 'department_supervisor') roleBadge = 'bg-success';

                // Format Role Name
                const displayRole = a.role_name.replace(/_/g, ' ').toUpperCase();

                html += `<tr>
                    <td><strong>${a.full_name}</strong></td>
                    <td><span class="badge ${roleBadge}">${displayRole}</span></td>
                    <td>${a.department || 'GLOBAL'}</td>
                    <td><code>${a.pf_number}</code></td>
                    <td>${a.email}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAdminAccount(${a.id})" ${a.role_name === 'super_admin' ? 'disabled' : ''}>
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            list.innerHTML = html;

        } else {
            list.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
        }
    } catch (e) {
        list.innerHTML = `<div class="alert alert-danger">Fetch Error: ${e.message}</div>`;
    }
}



// ============ LOAD STUDENT ACCOUNTS ============
async function loadStudentAccounts() {
    const list = document.getElementById('studentAccountsList');
    if (!list) return; // Not on tab

    list.innerHTML = '<div class="text-center p-3"><span class="spinner-border text-primary"></span><p class="mt-2">Loading students...</p></div>';

    const token = sessionStorage.getItem('adminToken');
    try {
        const response = await fetch(`${API_BASE}/get-users.php`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await response.json();

        if (data.success) {
            // Update Stat
            if (document.getElementById('statUsers')) {
                animateCounter(document.getElementById('statUsers'), data.students.length);
            }

            if (!data.students || data.students.length === 0) {
                list.innerHTML = '<div class="alert alert-info">No student accounts found.</div>';
                return;
            }

            let html = `<div class="table-responsive"><table id="studentAccountsTable" class="table table-hover align-middle">
                <thead class="table-light"><tr>
                    <th>Name</th><th>National ID</th><th>Institution</th><th>App. Status</th><th>PF Number</th><th>Registered</th>
                </tr></thead><tbody>`;

            data.students.forEach(s => {
                const appStatus = s.application_status || 'not_applied';
                html += `<tr>
                    <td><strong>${s.full_name}</strong><br><small class="text-muted">${s.email}</small></td>
                    <td>${s.national_id}</td>
                    <td>${s.institution_name || '—'}</td>
                    <td>${getStatusBadge(appStatus)}</td>
                    <td>${s.intern_pf_number || '—'}</td>
                    <td>${new Date(s.created_at).toLocaleDateString()}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            list.innerHTML = html;
        } else {
            list.innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
        }
    } catch (e) {
        list.innerHTML = `<div class="alert alert-danger">Fetch Error: ${e.message}</div>`;
    }
}

// Global helper for deleting admin
window.deleteAdminAccount = async function (id) {
    if (!confirm('Are you sure you want to permanently delete this admin account?')) return;
    // TODO: Implement delete logic
    alert('Delete functionality is pending implementation.');
};


// ============ ADMIN DASHBOARD INIT ============
function initAdminDashboard() {
    loadAdminAccounts();
    loadStudentAccounts();

    // Tab Event Listeners
    const triggerTabList = [].slice.call(document.querySelectorAll('#adminTabs button'))
    triggerTabList.forEach(function (triggerEl) {
        triggerEl.addEventListener('shown.bs.tab', function (event) {
            if (event.target.id === 'staff-tab') loadAdminAccounts();
            if (event.target.id === 'students-tab') loadStudentAccounts();
        })
    });
}
window.initAdminDashboard = initAdminDashboard;

initializeAdminIfNeeded();
if (!sessionStorage.getItem('adminLoggedIn')) switchView('login');

// ============ ADMIN ANALYTICS ============
async function loadAdminAnalytics() {
    const token = sessionStorage.getItem('adminToken');
    try {
        const response = await fetch(`${API_BASE}/get-submissions.php`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await response.json();
        if (data.success && data.submissions) {
            const counts = { pending: 0, approved: 0, rejected: 0 };
            data.submissions.forEach(s => {
                if (s.status === 'pending' || s.status === 'pending_review') counts.pending++;
                else if (s.status === 'approved' || s.status === 'accepted' || s.status === 'deployed') counts.approved++;
                else if (s.status === 'rejected') counts.rejected++;
            });
            
            const ctx = document.getElementById('adminChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Pending', 'Approved', 'Rejected'],
                        datasets: [{
                            data: [counts.pending, counts.approved, counts.rejected],
                            backgroundColor: ['#D4960A', '#0F7A45', '#C5401A'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }
        }
    } catch (e) { console.error('Error loading analytics:', e); }
}

// Override initAdminDashboard to include analytics
const oldInitAdmin = window.initAdminDashboard;
window.initAdminDashboard = function() {
    if(oldInitAdmin) oldInitAdmin();
    loadAdminAnalytics();
};
