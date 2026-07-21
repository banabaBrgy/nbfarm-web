
@extends('layouts.sidebar')

@section('title', 'Revenue')
@section('page-label', 'Revenue')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/revenue.css') }}">
@endpush

@section('content')

 <!-- Header -->
<div class="section-header">
    <h2>Revenue Overview</h2>
    @include('revenue.partials.form')
</div>

@include('revenue.partials.stats', ['stats' => $stats])

<!-- Chart + Top Customers side by side -->
<div class="content-grid">
    @include('revenue.partials.chart', ['chartData' => $chartData])
    @include('revenue.partials.top-customers', ['topCustomers' => $topCustomers])
</div>

  @include('revenue.partials.transactions', ['transactions' => $transactions, 'filters' => $filters])

  <div class="toast" id="toast"></div>
<script>
(function () {
    const wrap = document.getElementById('chartWrap');
    const data = JSON.parse(wrap.dataset.chart);

    const width = wrap.clientWidth || 600;
    const height = 220;
    const padding = { top: 20, right: 20, bottom: 30, left: 45 };
    const chartW = width - padding.left - padding.right;
    const chartH = height - padding.top - padding.bottom;

    const maxVal = Math.max(...data.map(d => d.amount), 1);
    const step = chartW / (data.length - 1);

    const points = data.map((d, i) => {
        const x = padding.left + i * step;
        const y = padding.top + chartH - (d.amount / maxVal) * chartH;
        return { x, y, ...d };
    });

    const linePath = points.map((p, i) => (i === 0 ? 'M' : 'L') + p.x + ',' + p.y).join(' ');
    const areaPath = linePath + ` L${points[points.length - 1].x},${padding.top + chartH} L${points[0].x},${padding.top + chartH} Z`;

    // Gridlines (horizontal, 4 divisions)
    let gridlines = '';
    for (let i = 0; i <= 4; i++) {
        const y = padding.top + (chartH / 4) * i;
        gridlines += `<line x1="${padding.left}" y1="${y}" x2="${width - padding.right}" y2="${y}" class="chart-gridline" />`;
    }

    // X-axis labels
    let xLabels = '';
    points.forEach(p => {
        xLabels += `<text x="${p.x}" y="${height - 6}" class="chart-bar-label">${p.day}</text>`;
    });

    // Data points + value labels
    let dots = '';
    points.forEach(p => {
        dots += `
            <g class="chart-bar-group">
                <circle cx="${p.x}" cy="${p.y}" r="4" fill="var(--green-main)" stroke="#fff" stroke-width="2" />
                <text x="${p.x}" y="${p.y - 12}" class="chart-value-label">₱${p.amount.toLocaleString()}</text>
            </g>
        `;
    });

    wrap.innerHTML = `
        <svg class="chart-svg" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none">
            <defs>
                <linearGradient id="areaGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="var(--green-main)" stop-opacity="0.25" />
                    <stop offset="100%" stop-color="var(--green-main)" stop-opacity="0" />
                </linearGradient>
            </defs>
            ${gridlines}
            <path d="${areaPath}" fill="url(#areaGradient)" stroke="none" />
            <path d="${linePath}" fill="none" stroke="var(--green-main)" stroke-width="2.5" />
            ${dots}
            ${xLabels}
        </svg>
    `;
})();
</script>
@endsection

@push('scripts')
  <script>
    window.NB_REVENUE = {
      chartData: @json($chartData),
      routes: {
        store: "{{ route('owner.revenue.store') }}",
      },
      csrfToken: document.querySelector('meta[name="csrf-token"]').content,
    };
  </script>
  <script src="{{ asset('js/revenue.js') }}"></script>
@endpush