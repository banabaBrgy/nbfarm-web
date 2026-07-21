@extends('layouts.dashboard')

@section('title','Batch '.$production->batch_id)

@section('content')

<div class="container-fluid">

    {{-- Summary Cards --}}
    <div class="row mb-4">

        <div class="col-md-3">

            <div class="summary-card">

                <h6>Batch</h6>

                <h3>Batch {{ $production->batch_id }}</h3>

            </div>

        </div>

        <div class="col-md-3">

            <div class="summary-card">

                <h6>Total Eggs</h6>

                <h3>{{ number_format($production->eggs_produced) }}</h3>

            </div>

        </div>

        <div class="col-md-3">

            <div class="summary-card">

                <h6>QR Codes</h6>

                <h3>{{ $qrRecords->count() }}</h3>

            </div>

        </div>

        <div class="col-md-3">

            <div class="summary-card">

                <h6>Status</h6>

                <h3>

                    {{ $qrRecords->where('status','Available')->count() }}

                    Available

                </h3>

            </div>

        </div>

    </div>

    {{-- Toolbar --}}
    <div class="card shadow-sm">

        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <h4>

                    QR Records

                </h4>

                <div class="d-flex gap-2">

                    <form
                    method="POST"
                    action="{{ route('generate.qr',$production->batch_id) }}">

                    @csrf

                    <button class="btn btn-success">

                    <i class="fas fa-plus-circle"></i>

                    Generate QR

                    </button>

                    </form>

                    <button class="btn btn-primary">

                    <i class="fas fa-camera"></i>

                    Scan QR

                    </button>

                </div>

            </div>

            <div class="row mb-3">

                <div class="col-md-4">

                    <input
                        type="text"
                        class="form-control"
                        placeholder="Search QR Code...">

                </div>

                <div class="col-md-3 ms-auto">

                    <input
                        type="date"
                        class="form-control"
                        value="{{ now()->toDateString() }}">

                </div>

            </div>

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-success">

                    <tr>

                        <th>QR Code</th>

                        <th>Egg Size</th>

                        <th>Tray</th>

                        <th>Total Eggs</th>

                        <th>Status</th>

                        <th width="170">

                            Action

                        </th>

                    </tr>

                </thead>

                <tbody>

                @forelse($qrRecords as $record)

                    <tr>

                        <td>

                            {{ $record->qr_code }}

                        </td>

                        <td>

                            {{ $record->egg_size }}

                        </td>

                        <td>

                            {{ $record->tray_count }}

                        </td>

                        <td>

                            {{ number_format($record->total_eggs) }}

                        </td>

                        <td>

                            <span class="badge bg-success">

                                {{ $record->status }}

                            </span>

                        </td>

                        <td>

                            <button class="btn btn-primary btn-sm">

                                Edit

                            </button>

                            <button class="btn btn-danger btn-sm">

                                Delete

                            </button>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="6" class="text-center py-5">

                            <i class="fas fa-qrcode fa-3x text-secondary mb-3"></i>

                            <br>

                            No QR Records Yet

                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection