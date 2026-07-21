@php
  $icons = [
    'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    'list'   => '<path d="M3 6h18M3 12h18M3 18h18"/>',
    'arrow'  => '<path d="M5 12h14M12 5l7 7-7 7"/>',
    'pesos'  => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
    'bars'   => '<path d="M3 3h18v4H3zM3 11h18v2H3zM3 17h18v4H3z"/>',
    'report' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
    'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>',
  ];
@endphp
<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">{!! $icons[$name] ?? '' !!}</svg>
