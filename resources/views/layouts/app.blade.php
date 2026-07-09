<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="@yield('viewport', 'width=device-width, initial-scale=1.0')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">

    @stack('styles')
    @stack('styles-late')
    @auth
        @if (auth()->user()->isAdmin())
            <link rel="stylesheet" href="{{ asset('assets/css/dev-role-switcher.css') }}?v={{ filemtime(public_path('assets/css/dev-role-switcher.css')) }}">
        @endif
    @endauth
</head>
<body @yield('body-attributes')>
    @yield('content')

    @stack('scripts')

    @include('partials.app-tools')

    @auth
        @if (auth()->user()->isAdmin())
            @include('partials.dev-role-switcher')
            <script src="{{ asset('assets/js/shared/dev-role-switcher.js') }}?v={{ filemtime(public_path('assets/js/shared/dev-role-switcher.js')) }}"></script>
        @endif
    @endauth
</body>
</html>
