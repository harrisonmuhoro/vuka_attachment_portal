// ============ HR ANALYTICS (Bar Chart) ============
async function loadHrAnalytics() {
    const token = sessionStorage.getItem('adminToken');
    try {
        const res = await fetch(`${API_BASE}/get-submissions.php`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        const subs = data.submissions || [];

        // Group by department
        const deptCounts = {};
        subs.forEach(s => {
            const dept = s.department_applied || s.department || 'Unknown';
            deptCounts[dept] = (deptCounts[dept] || 0) + 1;
        });

        const labels = Object.keys(deptCounts);
        const values = Object.values(deptCounts);
        const colors = labels.map((_, i) => {
            const palette = ['#0F7A45', '#C5401A', '#1E40AF', '#D4960A', '#1A8F52', '#6B7A72'];
            return palette[i % palette.length];
        });

        const ctx = document.getElementById('hrChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Applicants',
                        data: values,
                        backgroundColor: colors,
                        borderRadius: 6,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
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
