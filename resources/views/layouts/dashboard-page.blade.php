@php
    $dashboardConfig = config("dashboards.{$dashboardKey}");
@endphp

@extends('layouts.app')

@section('viewport', 'width=device-width, initial-scale=1.0, viewport-fit=cover')
@section('title', ($pageTitle ?? $dashboardConfig['title']) . ' — مركز الأطراف الصناعية')
@section('body-attributes'){!! $dashboardConfig['body_attributes'] ?? '' !!} data-dashboard="{{ $dashboardKey }}" data-active-page="{{ $activePage ?? '' }}"@endsection

@push('styles')
    @foreach ($dashboardConfig['styles'] as $style)
        @php
            $styleSrc = str_starts_with($style, 'http')
                ? $style
                : asset($style) . (is_file(public_path($style)) ? '?v=' . filemtime(public_path($style)) : '');
        @endphp
        <link rel="stylesheet" href="{{ $styleSrc }}">
    @endforeach
@endpush

@push('styles-late')
    <link rel="stylesheet" href="{{ asset('assets/css/sidebar-logout.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard-header-notifications.css') }}?v={{ filemtime(public_path('assets/css/dashboard-header-notifications.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard-toast.css') }}?v={{ filemtime(public_path('assets/css/dashboard-toast.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/entity-badges.css') }}?v={{ filemtime(public_path('assets/css/entity-badges.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard-table-search.css') }}?v={{ filemtime(public_path('assets/css/dashboard-table-search.css')) }}">
    @include('partials.dashboard-date-filters-assets')
@endpush

@section('content')
    @include('partials.dashboard-sidebar', [
        'dashboardKey' => $dashboardKey,
        'activePage' => $activePage ?? '',
    ])

    <main class="main">
        <div class="page-header">
            <div>
                <h1>{{ $pageTitle ?? $dashboardConfig['title'] }}</h1>
                <p>{{ $dashboardConfig['sidebar']['subtitle'] ?? '' }}</p>
            </div>
            @include('partials.dashboard-header-actions', [
                'dashboardKey' => $dashboardKey,
                'activePage' => $activePage ?? '',
            ])
        </div>

        @include('partials.flash-messages')

        @yield('page-content')
    </main>

    @include("{$dashboardKey}.partials.modals")
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/shared/toast.js') }}?v={{ filemtime(public_path('assets/js/shared/toast.js')) }}"></script>
    <script src="{{ asset('assets/js/shared/form-validation.js') }}"></script>
    <script src="{{ asset('assets/js/shared/table-pagination.js') }}?v={{ filemtime(public_path('assets/js/shared/table-pagination.js')) }}"></script>
    <script src="{{ asset('assets/js/shared/entity-badges.js') }}?v={{ filemtime(public_path('assets/js/shared/entity-badges.js')) }}"></script>
    <script src="{{ asset('assets/js/shared/tech-notes-modal.js') }}"></script>
    @if (! empty($dashboardConfig['nav_groups']))
        <script src="{{ asset('assets/js/shared/sidebar-nav-groups.js') }}?v={{ filemtime(public_path('assets/js/shared/sidebar-nav-groups.js')) }}"></script>
    @endif
    @include('partials.firebase-web')
    <script src="{{ asset('assets/js/shared/dashboard-notifications.js') }}"></script>
    @foreach ($dashboardConfig['scripts'] as $script)
        @php
            $scriptSrc = str_starts_with($script, 'http')
                ? $script
                : asset($script) . (is_file(public_path($script)) ? '?v=' . filemtime(public_path($script)) : '');
        @endphp
        <script src="{{ $scriptSrc }}"></script>
    @endforeach
@endpush
