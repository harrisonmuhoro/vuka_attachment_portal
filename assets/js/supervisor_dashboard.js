// ============ SUPERVISOR ANALYTICS (Line Chart — Feature #10 server-side aggregation) ============
const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Turn a 'YYYY-MM' bucket into a friendly 'Mon YYYY' label without Date parsing.
function formatMonthLabel(ym) {
    const parts = String(ym).split('-');
    const monthIdx = parseInt(parts[1], 10) - 1;
    const name = MONTH_NAMES[monthIdx] || parts[1];
    return `${name} ${parts[0]}`;
}

async function loadSupervisorAnalytics() {
    const token = sessionStorage.getItem('adminToken');
    try {
        const res = await fetch(`${API_BASE}/get-analytics.php`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        const monthly = (data.success && data.data && data.data.monthly) ? data.data.monthly : [];

        const labels = monthly.map(r => formatMonthLabel(r.month));
        const values = monthly.map(r => Number(r.count) || 0);

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
