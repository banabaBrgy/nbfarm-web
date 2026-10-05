@extends('layouts.sidebar')

@section('title', 'Dispatch')
@section('page-label', 'Dispatch')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('css/dispatch.css') }}">
@endpush

@section('content')
    @include('dispatch.page', ['canManage' => false])
@endsection
