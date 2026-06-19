@php
    $dashboardConfig = config("dashboards.{$dashboardKey}");
@endphp

@extends('layouts.app')

@section('viewport', 'width=device-width, initial-scale=1.0, viewport-fit=cover')
@section('title', ($pageTitle ?? $dashboardConfig['title']) . ' — مركز الأطراف الصناعية')
@section('body-attributes'){!! $dashboardConfig['body_attributes'] !!} data-dashboard="{{ $dashboardKey }}" data-active-page="{{ $activePage ?? '' }}"@endsection

@push('styles')
    @foreach ($dashboardConfig['styles'] as $style)
        <link rel="stylesheet" href="{{ asset($style) }}">
    @endforeach
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
            <div class="user-chip">
                <div class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
                <span>{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="logout-btn" title="تسجيل الخروج">↩</button>
                </form>
            </div>
        </div>

        @yield('page-content')
    </main>

    @include("{$dashboardKey}.partials.modals")
@endsection

@push('scripts')
    @foreach ($dashboardConfig['scripts'] as $script)
        <script src="{{ asset($script) }}"></script>
    @endforeach
@endpush
