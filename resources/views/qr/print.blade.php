@extends('layouts.sidebar')

@section('title','Print QR')

@section('content')

<style>

.qr-card{

    border:2px solid #2E7D32;

    border-radius:10px;

    overflow:hidden;

    background:white;

}

.qr-header{

    background:#2E7D32;

    color:white;

    font-size:20px;

    font-weight:bold;

    text-align:center;

    padding:10px;

}

.qr-body{

    padding:20px;

}

.qr-image{

    display:flex;

    justify-content:center;

    align-items:center;

}

@media print{

    .btn{

        display:none;

    }

}

</style>

<div class="container">

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <a href="{{ route(\App\Models\User::routeRole() . '.production') }}" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i>
        Back
    </a>

    <div class="d-flex justify-content-between mb-4">

        <h2>Print QR Labels</h2>

        <button onclick="window.print()" class="btn btn-success">

            <i class="fas fa-print"></i>

            Print All

        </button>

    </div>

    <div class="row">

        @foreach($records as $record)

        <div class="col-md-4 mb-4">

    <div class="qr-card">

        <div class="qr-header">
            NB Poultry Farm
        </div>

        <div class="qr-body">

            <div class="qr-image">

                {!! QrCode::size(180)->generate($record->qr_code) !!}
            </div>

            <table class="table table-borderless table-sm mt-3">

                <tr>
                    <td><strong>QR Code</strong></td>
                    <td>{{ $record->qr_code }}</td>
                </tr>

                <tr>
                    <td><strong>Batch</strong></td>
                    <td>{{ $record->batch_id }}</td>
                </tr>

                <tr>
                    <td><strong>Egg Size</strong></td>
                    <td>{{ $record->egg_size }}</td>
                </tr>

                <tr>
                    <td><strong>Tray Count</strong></td>
                    <td>{{ $record->tray_count }}</td>
                </tr>

                <tr>
                    <td><strong>Total Eggs</strong></td>
                    <td>{{ number_format($record->tray_count * $record->eggs_per_tray) }}</td>
                </tr>

            </table>

        </div>

    </div>

</div>

        @endforeach

    </div>

</div>

@endsection