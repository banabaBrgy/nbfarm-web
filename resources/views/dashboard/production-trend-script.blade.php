<script>
    (function () {
        const canvas = document.getElementById('eggChart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        const green = '#2d7d46';
        const greenFill = 'rgba(61, 150, 80, 0.22)';
        const forecastOrange = '#f0a030';
        const axisGray = '#9ca3af';
        const gridGray = 'rgba(148, 163, 184, 0.35)';

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
                        borderColor: row.color || green,
                        backgroundColor: greenFill,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: row.color || green,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 1.5,
                        fill: true,
                        tension: 0.15,
                        spanGaps: false,
                    };
                }),
            }
            : {
                labels: @json($eggChartLabels ?? []),
                datasets: [
                    {
                        type: 'line',
                        label: 'Actual',
                        data: @json($eggChartData ?? []),
                        borderColor: green,
                        backgroundColor: greenFill,
                        borderWidth: 2.5,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: green,
                        pointBorderColor: green,
                        fill: true,
                        tension: 0.12,
                        spanGaps: true,
                        order: 2,
                    },
                    {
                        type: 'line',
                        label: 'Forecast',
                        data: @json($eggForecastData ?? []),
                        borderColor: forecastOrange,
                        backgroundColor: 'transparent',
                        borderDash: [7, 5],
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointHoverRadius: 4,
                        pointBackgroundColor: forecastOrange,
                        pointBorderColor: forecastOrange,
                        fill: false,
                        tension: 0.12,
                        spanGaps: true,
                        order: 1,
                    },
                ],
            };

        const chart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: !monthly,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: monthly,
                        position: 'top',
                        align: 'center',
                        labels: {
                            color: '#54595e',
                            boxWidth: 14,
                            boxHeight: 14,
                            padding: 18,
                            usePointStyle: false,
                            font: {
                                size: 13,
                                weight: '700',
                                family: "'Poppins', 'Nunito', sans-serif",
                            },
                        },
                    },
                    tooltip: {
                        backgroundColor: '#1f2937',
                        titleFont: { size: 12, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 10,
                        cornerRadius: 8,
                    },
                },
                scales: {
                    y: {
                        display: true,
                        beginAtZero: true,
                        max: 12000,
                        border: { display: false },
                        grid: {
                            color: gridGray,
                            drawTicks: false,
                        },
                        ticks: {
                            stepSize: 3000,
                            color: axisGray,
                            padding: 8,
                            font: { size: 11 },
                            callback: function (value) {
                                return Number(value).toLocaleString();
                            },
                        },
                    },
                    x: {
                        border: { display: false },
                        grid: {
                            display: true,
                            color: gridGray,
                            drawTicks: false,
                        },
                        ticks: {
                            color: axisGray,
                            padding: 6,
                            font: { size: 11, weight: '500' },
                        },
                    },
                },
            },
        });

        if (!monthly) {
            const legendButtons = document.querySelectorAll('[data-egg-chart-toggle]');

            legendButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const label = button.getAttribute('data-egg-chart-toggle');
                    const index = chart.data.datasets.findIndex(function (dataset) {
                        return dataset.label === label;
                    });

                    if (index < 0) {
                        return;
                    }

                    const visible = chart.isDatasetVisible(index);
                    chart.setDatasetVisibility(index, !visible);
                    chart.update();

                    button.classList.toggle('is-hidden', visible);
                    button.setAttribute('aria-pressed', visible ? 'false' : 'true');
                });
            });
        }
    })();
</script>
