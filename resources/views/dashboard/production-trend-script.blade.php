    <script>
        (function () {
            const canvas = document.getElementById('eggChart');
            const chartView = @json($eggProductionChart ?? ['labels' => [], 'datasets' => []]);

            if (!canvas || typeof Chart === 'undefined') {
                return;
            }

            const labels = chartView.labels || [];
            const sourceSets = chartView.datasets || [];

            if (!labels.length || !sourceSets.length) {
                return;
            }

            const green = '#2d7d46';
            const greenFill = 'rgba(61, 150, 80, 0.22)';
            const forecastOrange = '#f0a030';
            const axisGray = '#9ca3af';
            const gridGray = 'rgba(148, 163, 184, 0.35)';
            const compactQuery = window.matchMedia('(max-width: 768px)');
            const compact = compactQuery.matches;
            const several = labels.length > 14;

            const data = {
                labels: labels,
                datasets: sourceSets.map(function (row) {
                    const isForecast = !!row.forecast || row.label === 'Forecast';

                    return {
                        type: 'line',
                        label: row.label,
                        data: row.data,
                        borderColor: isForecast ? forecastOrange : green,
                        backgroundColor: isForecast ? 'transparent' : greenFill,
                        borderDash: [],
                        borderWidth: isForecast ? (compact ? 2.5 : 3) : (compact ? 2 : 2.5),
                        pointRadius: compact ? 3.5 : 4,
                        pointHoverRadius: compact ? 5 : (isForecast ? 4 : 6),
                        pointBackgroundColor: isForecast ? forecastOrange : green,
                        pointBorderColor: isForecast ? forecastOrange : '#fff',
                        pointBorderWidth: 1.5,
                        fill: !isForecast,
                        tension: isForecast ? 0 : 0.15,
                        spanGaps: isForecast,
                        order: isForecast ? 1 : 2,
                    };
                }),
            };

            const chartValues = data.datasets
                .flatMap(function (dataset) {
                    return dataset.data;
                })
                .filter(function (value) {
                    return value !== null &&
                        value !== undefined &&
                        Number.isFinite(Number(value));
                })
                .map(Number);

            const dataMin = chartValues.length ? Math.min(...chartValues) : 0;
            const dataMax = chartValues.length ? Math.max(...chartValues) : 12000;
            const midpoint = chartValues.length ? (dataMin + dataMax) / 2 : 0;
            const minSpan = Math.max(1000, Math.abs(midpoint) * 0.12);
            let plotMin = dataMin;
            let plotMax = dataMax;

            if ((plotMax - plotMin) < minSpan) {
                const extra = (minSpan - (plotMax - plotMin)) / 2;
                plotMin -= extra;
                plotMax += extra;
            }

            if (plotMin < 0 && dataMin >= 0) {
                plotMax += -plotMin;
                plotMin = 0;
            }

            const dataRange = Math.max(plotMax - plotMin, 1);
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

            const axisMin = Math.floor(plotMin / stepSize) * stepSize;
            const axisMax = Math.ceil(plotMax / stepSize) * stepSize;

            const hasForecastLine = data.datasets.some(function (dataset) {
                return dataset.label === 'Forecast';
            });

            const chart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: labels.length <= 14,

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
                            display: hasForecastLine || data.datasets.length > 1,
                            position: 'top',
                            align: 'center',
                            labels: {
                                color: '#54595e',
                                boxWidth: 14,
                                boxHeight: 14,
                                padding: compact ? 10 : 18,
                                font: {
                                    size: compact ? 11 : 13,
                                    weight: '700',
                                    family: "'Poppins', 'Nunito', sans-serif",
                                },
                            },
                        },

                        tooltip: {
                            backgroundColor: '#1f2937',
                            titleFont: {
                                size: 12,
                                weight: '600'
                            },
                            bodyFont: {
                                size: 12
                            },
                            padding: 10,
                            cornerRadius: 8,
                            callbacks: {
                                label: function (context) {
                                    const value = context.parsed.y;
                                    if (value === null || value === undefined || Number.isNaN(value)) {
                                        return (context.dataset.label || 'Production') + ': —';
                                    }

                                    return (context.dataset.label || 'Production') + ': ' + Number(value).toLocaleString();
                                }
                            },
                        },
                    },

                    scales: {
                        y: {
                            display: true,
                            beginAtZero: false,
                            min: axisMin === axisMax
                                ? axisMin - stepSize
                                : axisMin,
                            max: axisMin === axisMax
                                ? axisMax + stepSize
                                : axisMax,
                            border: {
                                display: false
                            },
                            grid: {
                                color: gridGray,
                                drawTicks: false,
                            },
                            ticks: {
                                stepSize: stepSize,
                                color: axisGray,
                                padding: compact ? 4 : 8,
                                font: {
                                    size: compact ? 10 : 11
                                },
                                callback: function (value) {
                                    return Number(value).toLocaleString();
                                },
                            },
                        },

                        x: {
                            border: {
                                display: false
                            },
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
                                maxTicksLimit: several ? (compact ? 6 : 8) : undefined,
                                font: {
                                    size: compact ? 10 : 11,
                                    weight: '500'
                                },
                            },
                        },
                    },
                },
            });

            function applyCompactChart(isCompact) {
                chart.data.datasets.forEach(function (dataset) {
                    const isForecast = dataset.label === 'Forecast';

                    dataset.borderWidth = isForecast
                        ? (isCompact ? 2.5 : 3)
                        : (isCompact ? 2 : 2.5);
                    dataset.pointRadius = isCompact ? 3.5 : 4;
                    dataset.pointHoverRadius = isCompact ? 5 : (isForecast ? 4 : 6);
                });

                chart.options.scales.x.ticks.font.size = isCompact ? 10 : 11;
                chart.options.scales.y.ticks.font.size = isCompact ? 10 : 11;
                chart.options.scales.y.ticks.padding = isCompact ? 4 : 8;
                chart.options.layout.padding.right = isCompact ? 4 : 8;
                chart.options.plugins.legend.labels.padding = isCompact ? 10 : 18;
                chart.options.plugins.legend.labels.font.size = isCompact ? 11 : 13;
                chart.options.scales.x.ticks.maxTicksLimit = several
                    ? (isCompact ? 6 : 8)
                    : undefined;
            }

            if (typeof compactQuery.addEventListener === 'function') {
                compactQuery.addEventListener('change', function (event) {
                    applyCompactChart(event.matches);
                    chart.update('none');
                });
            }
        })();
    </script>
