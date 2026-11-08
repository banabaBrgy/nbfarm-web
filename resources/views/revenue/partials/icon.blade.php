@php
  $icons = [
    'grid' => 'fa-table-cells',
    'clock' => 'fa-clock',
    'list' => 'fa-list',
    'arrow' => 'fa-arrow-right',
    'pesos' => 'fa-peso-sign',
    'bars' => 'fa-chart-column',
    'report' => 'fa-file-lines',
    'user' => 'fa-user',
  ];
  $icon = $icons[$name] ?? null;
@endphp
@if ($icon)
  <i class="fas {{ $icon }}" aria-hidden="true"></i>
@endif
