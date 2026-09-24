<script>
    (function () {
        const canvas = document.getElementById('eggChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        const monthlyYear = @json($productionTrend['selectedYear'] ?? null);
        const monthly = monthlyYear !== null && monthlyYear !== '';
        const months = @json($productionTrend['months'] ?? []);
        const series = @json($productionTrend['series'] ?? []);

        const data = monthly
            ? {
                labels: months,
                datasets: series.map(function (row) {
                    return {
                        type: 'line',
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
            }
            : {
                labels: @json($eggChartLabels ?? []),
                datasets: [
                    {
                        type: 'bar',
                        label: 'Actual',
                        data: @json($eggChartData ?? []),
                        backgroundColor: '#3B8F47',
                        borderRadius: 10,
                        barThickness: 32,
                    },
                    {
                        type: 'line',
                        label: 'Forecast',
                        data: @json($eggForecastData ?? []),
                        borderColor: '#e07a3f',
                        borderDash: [6, 4],
                        borderWidth: 2,
                        pointBackgroundColor: '#e07a3f',
                        fill: false,
                        tension: 0.3,
                    },
                ],
            };

        new Chart(canvas.getContext('2d'), {
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: !monthly,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } },
                    },
                },
                scales: {
                    y: {
                        display: monthly,
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
