@php
    $rolePrefix = \App\Models\User::routeRole();
    $indexRoute = $rolePrefix . '.production';
    $scanRoute = $rolePrefix . '.production.scan';
    $generateRoute = $rolePrefix . '.qr.generate';
    $detailsRoute = $rolePrefix . '.production.details';
    $date = $selectedDate
        ? ($selectedDate instanceof \Carbon\Carbon ? $selectedDate : \Carbon\Carbon::parse($selectedDate))
        : null;
    $isToday = $date?->isToday() ?? false;
    $rangeStart = $productions->min('production_date');
    $rangeEnd = $productions->max('production_date');
    $rangeLabel = $date
        ? $date->format('M j, Y')
        : (($rangeStart && $rangeEnd)
            ? \Carbon\Carbon::parse($rangeStart)->format('M j').' – '.\Carbon\Carbon::parse($rangeEnd)->format('M j, Y')
            : 'All dates');
    $activeBatches = $activeBatches ?? 3;
@endphp

<div class="production-page">

    @if(session('success'))
        <div class="prd-alert">{{ session('success') }}</div>
    @endif

    <div class="prd-header">
        <div class="prd-header-copy">
            <h1>Production</h1>
            <p>Harvest by batch, size, and date — scan or generate QR codes here.</p>
        </div>

        <div class="prd-header-actions">
            <a href="{{ route($scanRoute) }}" class="prd-btn prd-btn-scan">
                <i class="fas fa-camera"></i>
                Scan QR
            </a>
            <form action="{{ route($generateRoute) }}" method="POST">
                @csrf
                <button type="submit" class="prd-btn prd-btn-generate">
                    <i class="fas fa-qrcode"></i>
                    Generate QR
                </button>
            </form>
        </div>
    </div>

    <div class="prd-stats">
        <div class="prd-stat">
            <div class="prd-stat-icon prd-stat-icon-eggs">
                <i class="fas fa-egg"></i>
            </div>
            <div>
                <div class="prd-stat-label">{{ $isToday ? "Today's production" : 'Production' }}</div>
                <div class="prd-stat-value">{{ number_format($totalEggs) }}</div>
                <div class="prd-stat-hint">Eggs harvested</div>
            </div>
        </div>

        <div class="prd-stat">
            <div class="prd-stat-icon prd-stat-icon-batches">
                <i class="fas fa-layer-group"></i>
            </div>
            <div>
                <div class="prd-stat-label">Active batches</div>
                <div class="prd-stat-value">{{ $activeBatches }}</div>
                <div class="prd-stat-hint">{{ $isToday ? 'Harvesting today' : 'On this date' }}</div>
            </div>
        </div>

        <div class="prd-stat">
            <div class="prd-stat-icon prd-stat-icon-forecast">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <div class="prd-stat-label">Predicted eggs</div>
                <div class="prd-stat-value">{{ number_format($predictedEggs) }}</div>
                <div class="prd-stat-hint">Next-day forecast</div>
            </div>
        </div>
    </div>

    <div class="prd-card">
        <div class="prd-toolbar">
            <div class="prd-toolbar-copy">
                <h2>{{ $date ? ($isToday ? "Today's egg production" : 'Egg production') : 'Egg production' }}</h2>
                <span>{{ $rangeLabel }}</span>
            </div>

            <form method="GET" action="{{ route($indexRoute) }}" class="prd-filters">
                <div class="prd-search">
                    <i class="fas fa-search"></i>
                    <input
                        type="text"
                        name="search"
                        placeholder="Search date..."
                        value="{{ request('search') }}">
                </div>
                    <input
                        type="date"
                        name="date"
                        min="{{ now()->startOfYear()->toDateString() }}"
                        max="{{ now()->endOfYear()->toDateString() }}"
                        value="{{ $date?->toDateString() }}"
                        onchange="this.form.submit()">
            </form>
        </div>

        @if($productions->isEmpty())
            <div class="prd-empty">
                <i class="fas fa-egg"></i>
                <p>No harvest records{{ $date ? ' for this date' : '' }}.</p>
            </div>
        @else
            <div class="prd-table-wrap">
                <table class="prd-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Small</th>
                            <th>Medium</th>
                            <th>Large</th>
                            <th>Extra Large</th>
                            <th>Cracked</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $small = 0;
                            $medium = 0;
                            $large = 0;
                            $xl = 0;
                            $cracked = 0;
                            $total = 0;
                        @endphp

                        @foreach($productions as $production)
                            @php
                                $small += $production->small_eggs;
                                $medium += $production->medium_eggs;
                                $large += $production->large_eggs;
                                $xl += $production->extra_large_eggs;
                                $cracked += $production->cracked_eggs;
                                $total += $production->totalEggCount();
                            @endphp
                            <tr>
                                <td>
                                    <span class="prd-batch">{{ \Carbon\Carbon::parse($production->production_date)->format('M j, Y') }}</span>
                                </td>
                                <td><x-egg-count :value="$production->small_eggs" /></td>
                                <td><x-egg-count :value="$production->medium_eggs" /></td>
                                <td><x-egg-count :value="$production->large_eggs" /></td>
                                <td><x-egg-count :value="$production->extra_large_eggs" /></td>
                                <td><x-egg-count :value="$production->cracked_eggs" /></td>
                                <td class="prd-total-cell">
                                    <x-egg-count :value="$production->totalEggCount()" />
                                </td>
                                <td>
                                    <a href="{{ route($detailsRoute, $production->production_id) }}" class="prd-view">
                                        <i class="fas fa-eye"></i>
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach

                        <tr class="prd-foot">
                            <td>Total</td>
                            <td><x-egg-count :value="$small" /></td>
                            <td><x-egg-count :value="$medium" /></td>
                            <td><x-egg-count :value="$large" /></td>
                            <td><x-egg-count :value="$xl" /></td>
                            <td><x-egg-count :value="$cracked" /></td>
                            <td><x-egg-count :value="$total" /></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
