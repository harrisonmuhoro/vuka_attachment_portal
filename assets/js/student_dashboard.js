// ============ STATUS TIMELINE ============
function updateStatusTimeline(status) {
    const timeline = document.getElementById('statusTimeline');
    if (!timeline) return;
    timeline.style.display = 'block';

    // Reset all steps
    ['step-applied', 'step-review', 'step-decision', 'step-deployed'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.classList.remove('done', 'active'); }
    });

    // Map status to step
    const statusMap = {
        'applied':       ['step-applied'],
        'pending':       ['step-applied'],
        'pending_review':['step-applied', 'step-review'],
        'accepted':      ['step-applied', 'step-review', 'step-decision'],
        'rejected':      ['step-applied', 'step-review', 'step-decision'],
        'deployed':      ['step-applied', 'step-review', 'step-decision', 'step-deployed'],
        'ongoing':       ['step-applied', 'step-review', 'step-decision', 'step-deployed']
    };

    const steps = statusMap[status] || [];
    steps.forEach((id, idx) => {
        const el = document.getElementById(id);
        if (!el) return;
        if (idx < steps.length - 1) {
            el.classList.add('done');
        } else {
            // Last step is active (unless rejected, show as danger-ish)
            el.classList.add(status === 'rejected' ? 'done' : 'active');
        }
    });
}

// ============ VACANCY SEARCH FILTER ============
function initVacancySearch() {
    const searchInput = document.getElementById('vacancySearch');
    if (!searchInput) return;

    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const cards = document.querySelectorAll('#vacanciesListContainer .vacancy-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const matches = !query || text.includes(query);
            card.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        // Show empty state if no results
        const emptyEl = document.getElementById('vacancySearchEmpty');
        if (emptyEl) {
            emptyEl.style.display = visibleCount === 0 && query ? 'block' : 'none';
        }
    });

    // Add the empty search state element after the container
    const container = document.getElementById('vacanciesListContainer');
    if (container && !document.getElementById('vacancySearchEmpty')) {
        const emptyDiv = document.createElement('div');
        emptyDiv.id = 'vacancySearchEmpty';
        emptyDiv.style.display = 'none';
        emptyDiv.className = 'empty-state text-center py-5';
        emptyDiv.innerHTML = `
            <i class="fas fa-search" style="font-size:3rem; color: var(--c-border);"></i>
            <h6 class="mt-3" style="color: var(--c-slate);">No results found</h6>
            <p class="small" style="color: var(--c-slate);">Try a different keyword or check back later.</p>
        `;
        container.parentNode.insertBefore(emptyDiv, container.nextSibling);
    }
}

// ============ WITHDRAW APPLICATION (Feature #12) ============
async function withdrawApplication(submissionId) {
    if (!confirm('Are you sure you want to withdraw this application? This cannot be undone.')) return;

    const token = sessionStorage.getItem('userToken');
    try {
        const res = await fetch(`${API_BASE}/withdraw-application.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({ submission_id: submissionId })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'Application withdrawn.', 'success');
            if (typeof initStudentDashboard === 'function') initStudentDashboard();
        } else {
            showToast(data.error || data.message || 'Failed to withdraw.', 'error');
        }
    } catch (e) {
        showToast('Withdraw error: ' + e.message, 'error');
    }
}

// ============ VACANCY DEADLINE COUNTDOWNS (Feature #7) ============
let vacancyCountdownTimer = null;
function initVacancyCountdowns() {
    const els = document.querySelectorAll('.vacancy-countdown');
    if (!els.length) return;

    const render = () => {
        document.querySelectorAll('.vacancy-countdown').forEach(el => {
            const deadline = new Date(String(el.dataset.deadline || '').replace(' ', 'T')).getTime();
            if (isNaN(deadline)) { el.textContent = ''; return; }
            const remaining = deadline - Date.now();
            if (remaining <= 0) {
                el.textContent = '⏳ Deadline passed';
                el.className = 'small fw-semibold mt-1 vacancy-countdown text-danger';
                return;
            }
            const d = Math.floor(remaining / 86400000);
            const h = Math.floor((remaining % 86400000) / 3600000);
            const m = Math.floor((remaining % 3600000) / 60000);
            el.textContent = `⏳ Closes in: ${d}d ${h}h ${m}m`;
            el.className = 'small fw-semibold mt-1 vacancy-countdown ' + (d < 2 ? 'text-danger' : 'text-warning');
        });
    };

    render();
    if (vacancyCountdownTimer) clearInterval(vacancyCountdownTimer);
    vacancyCountdownTimer = setInterval(render, 60000);
}

// Initialise when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    initVacancySearch();
});
