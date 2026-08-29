@props(['value' => 0])

@php
    $amount = (int) $value;
@endphp

{{ number_format($amount) }}
