@php
    $isQr = ($settings['type'] ?? 'barcode') === 'qr';
    $query = request()->query();
@endphp
<!DOCTYPE html>
<html lang="ar" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة {{ $isQr ? 'QR' : 'باركود' }} — {{ $heading }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/print-labels.css') }}?v={{ filemtime(public_path('assets/css/print-labels.css')) }}">
    <style>
        :root {
            --label-width: {{ $settings['label_width_mm'] }}mm;
            --label-height: {{ $settings['label_height_mm'] }}mm;
            --margin-left: 0mm;
            --margin-top: 0mm;
            --page-margin: 0mm;
            --gap: 0mm;
            --offset-x: 0mm;
            --offset-y: 0mm;
        }

        @media print {
            @page {
                size: {{ $settings['label_width_mm'] }}mm {{ $settings['label_height_mm'] }}mm;
                margin: 0;
            }
        }
    </style>
</head>
<body class="labels-body">
    <div class="labels-toolbar" dir="rtl">
        <h1>🏷️ طباعة — {{ $heading }}</h1>
        <p class="labels-toolbar-hint">
            ملصق ثابت <strong>50 × 25 مم</strong> — في نافذة الطباعة: المقاس 50×25، الهوامش None، التكبير 100%.
        </p>
        <div class="actions">
            <a class="label-type-btn {{ $isQr ? '' : 'is-active' }}" href="?{{ http_build_query(array_merge($query, ['type' => 'barcode'])) }}">▮▯▮ باركود</a>
            <a class="label-type-btn {{ $isQr ? 'is-active' : '' }}" href="?{{ http_build_query(array_merge($query, ['type' => 'qr'])) }}">▣ QR code</a>
            <button type="button" onclick="window.print()">🖨️ طباعة</button>
            <span class="count">{{ count($labels) }} ملصق</span>
        </div>
    </div>

    <div class="labels-sheet">
        @foreach ($labels as $label)
            @if ($isQr)
                <div class="label label--qr">
                    <div class="qr-wrap">{!! $label['svg'] !!}</div>
                    <div class="qr-text">
                        <div class="name">{{ $label['name'] }}</div>
                        <div class="code">{{ $label['barcode'] }}</div>
                    </div>
                </div>
            @else
                <div class="label">
                    <div class="name">{{ $label['name'] }}</div>
                    <div class="barcode">
                        <div class="barcode-svg-wrap">{!! $label['svg'] !!}</div>
                    </div>
                    <div class="code">{{ $label['barcode'] }}</div>
                </div>
            @endif
        @endforeach
    </div>
</body>
</html>
