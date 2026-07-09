// ============ SUPERVISOR ASSIGN MODAL ============
function openAssignModal(submissionId, applicantName) {
    document.getElementById('assignSubmissionId').value = submissionId;
    document.getElementById('assignApplicantName').textContent = applicantName;
    document.getElementById('assignRole').value = '';
    document.getElementById('assignStation').value = '';
    new bootstrap.Modal(document.getElementById('assignModal')).show();
}
window.openAssignModal = openAssignModal;

const assignForm = document.getElementById('assignForm');
if (assignForm) {
    assignForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const token = sessionStorage.getItem('adminToken');
        const dept = sessionStorage.getItem('adminDept');
        const submissionId = document.getElementById('assignSubmissionId').value;
        const role = document.getElementById('assignRole').value.trim();
        const station = document.getElementById('assignStation').value.trim();

        if (!role || !station) { showToast('Please fill both role and station.', 'warning'); return; }

        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deploying...';

        try {
            const res = await fetch(`${API_BASE}/update-submission.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
                body: JSON.stringify({
                    submission_id: submissionId,
                    status: 'deployed',
                    assigned_role: role,
                    assigned_station: station
                })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Applicant deployed and assigned!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('assignModal')).hide();
                loadSupervisorApplicants(dept, token);
            } else {
                showToast(data.message || data.error || 'Assignment failed', 'error');
            }
        } catch (err) { showToast('Error: ' + err.message, 'error'); }
        finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-user-check me-2"></i>Deploy & Assign';
        }
    });
}

async function supervisorUpdateStatus(submissionId, newStatus, token) {
    if (!confirm(`Change status to "${newStatus}"?`)) return;
    try {
        const res = await fetch(`${API_BASE}/update-submission.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({ submission_id: submissionId, status: newStatus })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || `Status updated to ${newStatus}`, 'success');
            loadSupervisorApplicants(sessionStorage.getItem('adminDept'), token);
        } else {
            showToast(data.message || 'Update failed', 'error');
        }
    } catch (e) { showToast('Error: ' + e.message, 'error'); }
}
window.supervisorUpdateStatus = supervisorUpdateStatus;

