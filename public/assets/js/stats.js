/**
 * Stats 圖表渲染（Chart.js 4）
 * 從 <script type="application/json" id="data-N"> 讀取資料，渲染至 #chart-N
 */
(function () {
    'use strict';
    if (typeof Chart === 'undefined') return;

    document.querySelectorAll('script[id^="data-"]').forEach((tag) => {
        const idx = tag.id.replace('data-', '');
        const canvas = document.getElementById('chart-' + idx);
        if (!canvas) return;
        let data;
        try { data = JSON.parse(tag.textContent || '{}'); } catch (e) { return; }

        if (data.kind === 'date') {
            const labels = (data.series || []).map(r => r.day || r.DAY);
            const counts = (data.series || []).map(r => Number(r.cnt ?? r.CNT ?? 0));
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{ label: 'Count', data: counts, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.2)', tension: 0.2, fill: true }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
            return;
        }

        // options 預設
        const labels = (data || []).map(r => r.label || r.LABEL || '—');
        const counts = (data || []).map(r => Number(r.cnt ?? r.CNT ?? 0));
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Count',
                    data: counts,
                    backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#20c997','#fd7e14','#0dcaf0']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    });
})();
