@php
    $branding = app(\App\Services\SettingService::class)->branding();
    $logoRel = $branding['logo_path'];
    $logoExists = app(\App\Services\SettingService::class)->brandingLogoExists($logoRel);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بطاقة {{ $patient->patient_code }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/print-labels.css') }}">
</head>
<body class="patient-card-body" @if($autoPrint ?? true) onload="window.print()" @endif>

    <div class="toolbar">
        <h2>🏷️ بطاقة المريض الرقمية</h2>
        <span class="meta">{{ $patient->name }} &mdash; {{ $patient->patient_code }}</span>
        <button type="button" onclick="window.print()">🖨️ طباعة البطاقة</button>
    </div>

    <div class="card">

        {{-- رأس --}}
        <div class="card-head">
            <span class="card-brand">
                @if ($logoExists)
                    <img class="card-logo" src="{{ asset($logoRel) }}" alt="{{ $branding['center_name'] }}">
                @endif
                <span class="center-name">{{ $branding['center_name'] }}</span>
            </span>
            <span class="badge">{{ $typeLabel }}</span>
        </div>

        {{-- جسم --}}
        <div class="card-body">
            <div class="card-info">
                <div class="pt-name">{{ $patient->name }}</div>
                @if($patient->patient_serial)
                    <div class="pt-row">سيريال الملف: <b>{{ $patient->patient_serial }}</b></div>
                @endif
                <div class="pt-row">رقم المريض: {{ $patient->patient_code }}</div>
                <div class="pt-row">رقم الدور: {{ $queueNumber }}</div>
                @if($rank)
                    <div class="pt-sub">الرتبة: {{ $rank }}</div>
                @elseif($company)
                    <div class="pt-sub">{{ $company }}</div>
                @endif
            </div>

            <div class="card-qr">
                {!! $qrSvg !!}
            </div>
        </div>

        {{-- تذييل --}}
        <div class="card-foot">
            امسح الكود لمتابعة حالة الطلب وموعد التسليم
        </div>

    </div>

</body>
</html>
