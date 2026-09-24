<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="prosthetics-assets" content="{{ config('assets.use_local') ? 'local' : 'cdn' }}">
    <title>تسجيل الدخول — {{ $branding['center_name'] }}</title>
    @include('partials.web-fonts')
    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}?v={{ filemtime(public_path('assets/css/auth.css')) }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="auth-dashboard auth-home">

<div class="auth-bg"></div>

<div class="auth-wrapper">
    <div class="auth-card auth-card--home">

        <div class="auth-brand auth-brand--home">
            @include('partials.org-brand-mark', ['branding' => $branding, 'size' => 'lg', 'showLines' => true])
            <p class="auth-home-tagline">سجّل دخولك — سيتم توجيهك تلقائياً إلى لوحة عملك</p>
        </div>

        <form method="POST" action="{{ route('login.submit') }}" novalidate id="dashboardLoginForm">
            @csrf

            @if (session('success'))
                <div class="auth-alert auth-alert--success" role="status">{{ session('success') }}</div>
            @endif
            @if (session('error') && ! $errors->any())
                <div class="auth-alert" role="alert">
                    <span class="auth-alert__icon" aria-hidden="true">⚠️</span>
                    <div class="auth-alert__body">{{ session('error') }}</div>
                </div>
            @endif

            <input type="hidden" name="device_id" id="device_id" value="">
            <input type="hidden" name="device_type" id="device_type" value="web">

            @include('auth.partials.login-fields')

            <button type="submit" class="btn-login" id="loginSubmitBtn">دخول</button>
        </form>

        @include('partials.firebase-web')
        <script>
            (function () {
                if (typeof window.getFcmToken !== 'function') return;
                window.getFcmToken().then(function (token) {
                    if (token) document.getElementById('device_id').value = token;
                }).catch(function () { /* صامت */ });
            })();
        </script>
        <script src="{{ asset('assets/js/shared/auth-login.js') }}?v={{ filemtime(public_path('assets/js/shared/auth-login.js')) }}"></script>

        <div class="auth-footer auth-footer--home">
            <span>للمساعدة تواصل مع مسؤول النظام</span>
        </div>

    </div>
</div>

</body>
</html>
