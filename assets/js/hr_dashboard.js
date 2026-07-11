// ============ HR ANALYTICS (Bar Chart — Feature #10 server-side aggregation) ============
async function loadHrAnalytics() {
    const token = sessionStorage.getItem('adminToken');
    try {
        const res = await fetch(`${API_BASE}/get-analytics.php`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        const acceptance = (data.success && data.data && data.data.acceptance) ? data.data.acceptance : [];

        const labels = acceptance.map(r => r.department || 'Unknown');
        const totals = acceptance.map(r => Number(r.total) || 0);
        const accepted = acceptance.map(r => Number(r.accepted) || 0);

        const ctx = document.getElementById('hrChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Total applicants',
                            data: totals,
                            backgroundColor: '#0F7A45',
                            borderRadius: 6,
                            borderWidth: 0
                        },
                        {
                            label: 'Placed',
                            data: accepted,
                            backgroundColor: '#D4960A',
                            borderRadius: 6,
                            borderWidth: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 },
                            grid: { color: '#f0f7f0' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    } catch (e) { console.error('HR chart error:', e); }
}

// Hook into initHrDashboard after it loads
document.addEventListener('DOMContentLoaded', () => {
    if (typeof initHrDashboard === 'function') {
        const originalInit = initHrDashboard;
        window.initHrDashboard = async function() {
            await originalInit();
            loadHrAnalytics();
        };
    }
});
