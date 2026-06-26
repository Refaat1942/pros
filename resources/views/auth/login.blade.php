<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول — {{ $dashboardConfig['sidebar']['title'] ?? $dashboardConfig['title'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/auth.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="auth-dashboard auth-{{ $dashboard }}">

<div class="auth-bg"></div>

<div class="auth-wrapper">
    <div class="auth-card">

        {{-- Brand — مختلف لكل داشبورد --}}
        <div class="auth-brand">
            <div class="logo-icon">{{ $dashboardConfig['sidebar']['icon'] ?? '🔐' }}</div>
            <h1>{{ $dashboardConfig['sidebar']['title'] ?? $dashboardConfig['title'] }}</h1>
            <p>{{ $dashboardConfig['sidebar']['subtitle'] ?? 'تسجيل الدخول إلى النظام' }}</p>
        </div>

        <form method="POST" action="{{ route('dashboard.login.submit', $dashboard) }}" novalidate id="dashboardLoginForm">
            @csrf

            {{-- بيانات الجهاز للإشعارات (FCM) — تُملأ تلقائياً --}}
            <input type="hidden" name="device_id" id="device_id" value="">
            <input type="hidden" name="device_type" id="device_type" value="web">

            {{-- Email --}}
            <div class="form-group">
                <label for="email">البريد الإلكتروني</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="example@clinic.com"
                    autocomplete="email"
                    class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
                    autofocus
                >
                @error('email')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            {{-- Password --}}
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
            // استخراج device_id (FCM token) وتعبئته في النموذج قبل الإرسال — اختياري.
            (function () {
                if (typeof window.getFcmToken !== 'function') return;
                window.getFcmToken().then(function (token) {
                    if (token) document.getElementById('device_id').value = token;
                }).catch(function () { /* صامت */ });
            })();
        </script>
        <script src="{{ asset('assets/js/shared/auth-login.js') }}"></script>

        <div class="auth-footer">
            <a href="/" class="back-link">← العودة للصفحة الرئيسية</a>
            <span>للمساعدة تواصل مع مسؤول النظام</span>
        </div>

    </div>
</div>

</body>
</html>
