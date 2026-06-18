@php
    $dashboardConfig = config("dashboards.{$dashboardKey}");
@endphp

@extends('layouts.app')

@section('viewport', 'width=device-width, initial-scale=1.0, viewport-fit=cover')
@section('title', $dashboardConfig['title'])
@section('body-attributes'){!! $dashboardConfig['body_attributes'] !!}@endsection

@push('styles')
    @foreach ($dashboardConfig['styles'] as $style)
        <link rel="stylesheet" href="{{ asset($style) }}">
    @endforeach
@endpush

@section('content')
    @include("{$dashboardKey}.partials.content")
@endsection

@push('scripts')
    @foreach ($dashboardConfig['scripts'] as $script)
        <script src="{{ asset($script) }}"></script>
    @endforeach
@endpush
