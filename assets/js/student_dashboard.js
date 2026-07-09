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

// Initialise when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    initVacancySearch();
});
