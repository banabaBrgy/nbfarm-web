@extends('layouts.sidebar')

@section('title', 'Production')

@section('content')

    <!-- ===========================
    SUMMARY CARDS
    =========================== -->
    @if(session('success'))

        <div class="alert alert-success">

            {{ session('success') }}

        </div>

    @endif
    <div class="row g-4 mb-4">

        <div class="col-lg-3 col-md-6">

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

        <div class="col-lg-3 col-md-6">

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
                                Available Today
                            </small>

                        </div>

                        <div class="icon-circle bg-primary-subtle">

                            <i class="fas fa-layer-group text-primary"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="col-lg-3 col-md-6">

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

        <div class="col-lg-3 col-md-6">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body">

                    <small class="text-muted text-uppercase fw-semibold">
                        QR Management
                    </small>

                    <form action="{{ route('staff.qr.generate') }}" method="POST" class="mt-3">

                        @csrf

                        <button class="btn btn-primary w-100 mb-2">

                            <i class="fas fa-qrcode"></i>

                            Generate QR

                        </button>

                    </form>

                    <a href="{{ route('staff.production.scan') }}" class="btn btn-success w-100">

                        <i class="fas fa-camera"></i>

                        Scan QR

                    </a>

                </div>

            </div>

        </div>

    </div>


    <!-- ===========================
    PRODUCTION TABLE
    =========================== -->

    <div class="table-container">

        <div class="table-title d-flex justify-content-between align-items-center">

            <h2>Today's Egg Production</h2>

            <form method="GET" action="{{ route('staff.production') }}" class="d-flex gap-2">

                <input type="text" name="search" class="form-control" placeholder="Search Batch">

                <input type="date" name="date" class="form-control" value="{{ $selectedDate->toDateString() }}"
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

                @foreach($productions as $production)

                    @php

                        $small += $production->small_eggs;
                        $medium += $production->medium_eggs;
                        $large += $production->large_eggs;
                        $xl += $production->extra_large_eggs;
                        $cracked += $production->cracked_eggs;
                        $total += $production->eggs_produced;

                    @endphp

                    <tr>

                        <td>

                            <strong>

                                Batch {{ $production->batch_id }}

                            </strong>

                        </td>

                        <td>{{ number_format($production->small_eggs) }}</td>

                        <td>{{ number_format($production->medium_eggs) }}</td>

                        <td>{{ number_format($production->large_eggs) }}</td>

                        <td>{{ number_format($production->extra_large_eggs) }}</td>

                        <td>{{ number_format($production->cracked_eggs) }}</td>

                        <td>

                            <strong>

                                {{ number_format($production->eggs_produced) }}

                            </strong>

                        </td>

                        <td>

                            <a href="{{ route('staff.production.details', $production->production_id) }}"
                                class="btn btn-success btn-sm">

                                <i class="fas fa-eye"></i>
                                View / Edit

                            </a>

                        </td>

                    </tr>

                @endforeach

                <tr class="table-success">

                    <th>TOTAL</th>

                    <th>{{ number_format($small) }}</th>

                    <th>{{ number_format($medium) }}</th>

                    <th>{{ number_format($large) }}</th>

                    <th>{{ number_format($xl) }}</th>

                    <th>{{ number_format($cracked) }}</th>

                    <th>{{ number_format($total) }}</th>

                    <th>-</th>

                </tr>

            </tbody>

        </table>

    </div>




@endsection