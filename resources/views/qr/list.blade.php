@extends('layouts.sidebar')

@section('title','QR Codes')

@section('content')

<div class="container-fluid">

    <h2 class="mb-4">Generated QR Codes</h2>

    <table class="table table-bordered">

        <thead>

            <tr>

                <th>QR</th>
                <th>QR Code</th>
                <th>Batch</th>
                <th>Egg Size</th>
                <th>Total Eggs</th>
                <th>Status</th>

            </tr>

        </thead>

        <tbody>

        @foreach($records as $record)

            <tr>

                <td>

                    {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(100)->generate($record->qr_code) !!}

                </td>

                <td>{{ $record->qr_code }}</td>

                <td>{{ $record->batch_id }}</td>

                <td>{{ $record->egg_size }}</td>

                <td>{{ $record->tray_count * $record->eggs_per_tray }}</td>

                <td>{{ $record->status }}</td>

            </tr>

        @endforeach

        </tbody>

    </table>

</div>

@endsection