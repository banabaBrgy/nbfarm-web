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
        const compactQuery = window.matchMedia('(max-width: 768px)');

        const monthlyYear = @json($productionTrend['selectedYear'] ?? null);
        const monthly = monthlyYear !== null && monthlyYear !== '';
        const months = @json($productionTrend['months'] ?? []);
        const series = @json($productionTrend['series'] ?? []);
        const compact = compactQuery.matches;

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
                        borderWidth: compact ? 2 : 2.5,
                        pointRadius: compact ? 3.5 : 4,
                        pointHoverRadius: compact ? 5 : 6,
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
                        borderWidth: compact ? 2 : 2.5,
                        pointRadius: compact ? 3.5 : 5,
                        pointHoverRadius: compact ? 5 : 7,
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
                        borderWidth: compact ? 2 : 2.5,
                        pointRadius: compact ? 2.5 : 3,
                        pointHoverRadius: compact ? 5 : 4,
                        pointBackgroundColor: forecastOrange,
                        pointBorderColor: forecastOrange,
                        fill: false,
                        tension: 0.12,
                        spanGaps: true,
                        order: 1,
                    },
                ],
            };

        const chartValues = data.datasets
            .flatMap(function (dataset) {
                return dataset.data;
            })
            .filter(function (value) {
                return value !== null && value !== undefined && Number.isFinite(Number(value));
            })
            .map(Number)
            .filter(function (value) {
                return Number.isFinite(value);
            });

        const dataMin = chartValues.length ? Math.min(...chartValues) : 0;
        const dataMax = chartValues.length ? Math.max(...chartValues) : 12000;
        const dataRange = Math.max(dataMax - dataMin, 1);
        const roughStep = dataRange / 5;
        const stepMagnitude = Math.pow(10, Math.floor(Math.log10(roughStep)));
        const normalizedStep = roughStep / stepMagnitude;
        const stepSize = normalizedStep <= 1
            ? stepMagnitude
            : normalizedStep <= 2
                ? 2 * stepMagnitude
                : normalizedStep <= 5
                    ? 5 * stepMagnitude
                    : 10 * stepMagnitude;
        const axisMin = Math.floor(dataMin / stepSize) * stepSize;
        const axisMax = Math.ceil(dataMax / stepSize) * stepSize;

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
                layout: {
                    padding: {
                        top: 8,
                        right: compact ? 4 : 8,
                        left: 0,
                        bottom: 0,
                    },
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
                            padding: compact ? 10 : 18,
                            usePointStyle: false,
                            font: {
                                size: compact ? 11 : 13,
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
                        beginAtZero: false,
                        min: axisMin === axisMax ? axisMin - stepSize : axisMin,
                        max: axisMin === axisMax ? axisMax + stepSize : axisMax,
                        border: { display: false },
                        grid: {
                            color: gridGray,
                            drawTicks: false,
                        },
                        ticks: {
                            stepSize: stepSize,
                            color: axisGray,
                            padding: compact ? 4 : 8,
                            font: { size: compact ? 10 : 11 },
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
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: compact && monthly ? 6 : undefined,
                            font: { size: compact ? 10 : 11, weight: '500' },
                        },
                    },
                },
            },
        });

        function applyCompactChart(compact) {
            chart.data.datasets.forEach(function (dataset) {
                const isForecast = dataset.label === 'Forecast';
                dataset.borderWidth = compact ? 2 : 2.5;
                dataset.pointRadius = compact
                    ? (isForecast ? 2.5 : 3.5)
                    : (isForecast ? 3 : (monthly ? 4 : 5));
                dataset.pointHoverRadius = compact
                    ? 5
                    : (isForecast ? 4 : (monthly ? 6 : 7));
            });
            chart.options.scales.x.ticks.font.size = compact ? 10 : 11;
            chart.options.scales.y.ticks.font.size = compact ? 10 : 11;
            chart.options.scales.y.ticks.padding = compact ? 4 : 8;
            chart.options.layout.padding.right = compact ? 4 : 8;
            chart.options.plugins.legend.labels.padding = compact ? 10 : 18;
            chart.options.plugins.legend.labels.font.size = compact ? 11 : 13;
            chart.options.scales.x.ticks.maxTicksLimit = compact && monthly ? 6 : undefined;
        }

        if (typeof compactQuery.addEventListener === 'function') {
            compactQuery.addEventListener('change', function (event) {
                applyCompactChart(event.matches);
                chart.update('none');
            });
        }

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
