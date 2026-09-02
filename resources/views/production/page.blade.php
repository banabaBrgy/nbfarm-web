@php
    $rolePrefix = \App\Models\User::routeRole();
    $indexRoute = $rolePrefix . '.production';
    $scanRoute = $rolePrefix . '.production.scan';
    $generateRoute = $rolePrefix . '.qr.generate';
    $detailsRoute = $rolePrefix . '.production.details';
    $canManageQr = $rolePrefix === 'staff';
    $statCol = $canManageQr ? 'col-lg-3 col-md-6' : 'col-lg-4 col-md-6';
    $date = $selectedDate instanceof \Carbon\Carbon
        ? $selectedDate
        : \Carbon\Carbon::parse($selectedDate);
@endphp

<div class="production-page">

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="row g-4 mb-4">

    <div class="{{ $statCol }}">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-muted text-uppercase fw-semibold">
                            Today's Production
                        </small>
                        <h2 class="fw-bold mt-2 text-success">
                            {{ number_format($totalEggs) }}
                        </h2>
                        <small class="text-muted">
                            Eggs Produced
                        </small>
                    </div>
                    <div class="icon-circle bg-success-subtle">
                        <i class="fas fa-egg text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="{{ $statCol }}">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-muted text-uppercase fw-semibold">
                            Active Batches
                        </small>
                        <h2 class="fw-bold mt-2 text-success">
                            {{ $productions->count() }}
                        </h2>
                        <small class="text-muted">
                            Available today
                        </small>
                    </div>
                    <div class="icon-circle bg-primary-subtle">
                        <i class="fas fa-layer-group text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="{{ $statCol }}">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-muted text-uppercase fw-semibold">
                            Predicted Eggs
                        </small>
                        <h2 class="fw-bold mt-2 text-success">
                            {{ number_format($predictedEggs) }}
                        </h2>
                        <small class="text-muted">
                            Forecast
                        </small>
                    </div>
                    <div class="icon-circle bg-warning-subtle">
                        <i class="fas fa-chart-line text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($canManageQr)
    <div class="col-lg-3 col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <small class="text-muted text-uppercase fw-semibold">
                    QR Management
                </small>
                <form action="{{ route($generateRoute) }}" method="POST" class="mt-3">
                    @csrf
                    <button class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-qrcode"></i>
                        Generate QR
                    </button>
                </form>
                <a href="{{ route($scanRoute) }}" class="btn btn-success w-100">
                    <i class="fas fa-camera"></i>
                    Scan QR
                </a>
            </div>
        </div>
    </div>
    @endif

</div>

<div class="table-container">

    <div class="table-title d-flex justify-content-between align-items-center">
        <h2>Today's Egg Production</h2>
        <form method="GET" action="{{ route($indexRoute) }}" class="d-flex gap-2">
            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search Batch"
                value="{{ request('search') }}">
            <input
                type="date"
                name="date"
                class="form-control"
                min="{{ \App\Models\Production::sampleStartDate() }}"
                max="{{ now()->toDateString() }}"
                value="{{ $date->toDateString() }}"
                onchange="this.form.submit()">
        </form>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Batch</th>
                <th>Small</th>
                <th>Medium</th>
                <th>Large</th>
                <th>Extra Large</th>
                <th>Cracked</th>
                <th>Total</th>
                <th width="150">Action</th>
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

            @forelse($productions as $production)
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
                        <strong>Batch {{ $production->batch_id }}</strong>
                    </td>
                    <td><x-egg-count :value="$production->small_eggs" /></td>
                    <td><x-egg-count :value="$production->medium_eggs" /></td>
                    <td><x-egg-count :value="$production->large_eggs" /></td>
                    <td><x-egg-count :value="$production->extra_large_eggs" /></td>
                    <td><x-egg-count :value="$production->cracked_eggs" /></td>
                    <td>
                        <strong>
                            <x-egg-count :value="$production->totalEggCount()" />
                        </strong>
                    </td>
                    <td>
                        <a href="{{ route($detailsRoute, $production->production_id) }}"
                            class="btn btn-success btn-sm">
                            <i class="fas fa-eye"></i>
                            View / Edit
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-muted text-center py-4">
                        No harvest records for this date.
                    </td>
                </tr>
            @endforelse

            @if($productions->isNotEmpty())
                <tr class="table-success">
                    <th>TOTAL</th>
                    <th><x-egg-count :value="$small" /></th>
                    <th><x-egg-count :value="$medium" /></th>
                    <th><x-egg-count :value="$large" /></th>
                    <th><x-egg-count :value="$xl" /></th>
                    <th><x-egg-count :value="$cracked" /></th>
                    <th><x-egg-count :value="$total" /></th>
                    <th>-</th>
                </tr>
            @endif
        </tbody>
    </table>

</div>

</div>
