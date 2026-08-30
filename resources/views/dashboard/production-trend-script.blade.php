<script>
    (function () {
        const canvas = document.getElementById('productionTrendChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        const months = @json($productionTrend['months'] ?? []);
        const series = @json($productionTrend['series'] ?? []);

        new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: months,
                datasets: series.map(function (row) {
                    return {
                        label: row.label,
                        data: row.data,
                        borderColor: row.color,
                        backgroundColor: row.color,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        fill: false,
                        tension: 0.25,
                        spanGaps: false,
                    };
                }),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right',
                        labels: { boxWidth: 12, font: { size: 12 } },
                    },
                },
                scales: {
                    y: {
                        display: true,
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return Number(value).toLocaleString();
                            },
                        },
                    },
                    x: {
                        grid: { display: false },
                    },
                },
            },
        });
    })();
</script>
