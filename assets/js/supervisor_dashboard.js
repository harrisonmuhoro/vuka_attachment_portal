// ============ SUPERVISOR ANALYTICS (Line Chart) ============
async function loadSupervisorAnalytics() {
    const token = sessionStorage.getItem('adminToken');
    const dept = sessionStorage.getItem('adminDept');
    try {
        const res = await fetch(`${API_BASE}/get-submissions.php?department=${encodeURIComponent(dept)}`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        const subs = data.submissions || [];

        // Group by date (last 7 days)
        const dateMap = {};
        const today = new Date();
        for (let i = 6; i >= 0; i--) {
            const d = new Date(today);
            d.setDate(today.getDate() - i);
            const key = d.toLocaleDateString('en-KE', { month: 'short', day: 'numeric' });
            dateMap[key] = 0;
        }

        subs.forEach(s => {
            const d = new Date(s.submitted_at || s.created_at);
            const key = d.toLocaleDateString('en-KE', { month: 'short', day: 'numeric' });
            if (key in dateMap) dateMap[key]++;
        });

        const labels = Object.keys(dateMap);
        const values = Object.values(dateMap);

        const ctx = document.getElementById('supervisorChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Applications',
                        data: values,
                        borderColor: '#0F7A45',
                        backgroundColor: 'rgba(15, 122, 69, 0.08)',
                        tension: 0.4,
                        pointBackgroundColor: '#0F7A45',
                        pointRadius: 5,
                        fill: true,
                        borderWidth: 2
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
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    } catch (e) { console.error('Supervisor chart error:', e); }
}

document.addEventListener('DOMContentLoaded', () => {
    // Hook into supervisor init after it runs
    const origInit = window.initSupervisorDashboard;
    if (origInit) {
        window.initSupervisorDashboard = async function() {
            await origInit();
            loadSupervisorAnalytics();
        };
    }
});
