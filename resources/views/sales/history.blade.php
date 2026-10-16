@extends('layouts.sidebar')

@section('title', 'Revenue History')
@section('page-label', 'Revenue History')

@push('styles')
    <link rel="stylesheet" href="{{ versioned_asset('css/sales-history.css') }}">
@endpush

@section('content')
<div class="sales-history-page">
    <div class="page-header">
        <div>
            <h2>Revenue History</h2>
            <p>Review all recorded sales and filter revenue by today, this week, this month, previous months, or a custom date range.</p>
        </div>
    </div>

    @include('sales.partials.history-content')
</div>
@endsection
