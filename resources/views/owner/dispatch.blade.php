@extends('layouts.sidebar')

@section('title', 'Dispatch')
@section('page-label', 'Dispatch')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/dispatch.css') }}">
@endpush

@section('content')
    @include('dispatch.page', ['canManage' => false])
@endsection
