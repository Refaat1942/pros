<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول — {{ $branding['center_name'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
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

            @include('partials.flash-messages')

            <input type="hidden" name="device_id" id="device_id" value="">
            <input type="hidden" name="device_type" id="device_type" value="web">

            <div class="form-group">
                <label for="username">اسم المستخدم</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="{{ old('username') }}"
                    autocomplete="username"
                    class="{{ $errors->has('username') ? 'is-invalid' : '' }}"
                    autofocus
                >
                @error('username')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                >
                @error('password')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

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
        <script src="{{ asset('assets/js/shared/auth-login.js') }}"></script>

        <div class="auth-footer auth-footer--home">
            <span>للمساعدة تواصل مع مسؤول النظام</span>
        </div>

    </div>
</div>

</body>
</html>
