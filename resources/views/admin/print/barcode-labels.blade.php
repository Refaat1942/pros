<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة باركود — {{ $heading }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/print-labels.css') }}">
    <style>
        :root {
            --page-margin: {{ $settings['page_margin'] }}mm;
            --gap: {{ $settings['gap'] }}mm;
            --offset-x: {{ $settings['offset_x'] }}mm;
            --offset-y: {{ $settings['offset_y'] }}mm;
        }
    </style>
</head>
<body class="labels-body">
    <div class="labels-toolbar">
        <h1>🏷️ إعدادات الطباعة — {{ $heading }}</h1>
        <form id="settingsForm" onsubmit="applySettings(event)">
            <div class="fields">
                <div><label>عدد النسخ لكل صنف</label><input type="number" name="copies" min="1" max="200" value="{{ $settings['copies'] }}"></div>
                <div><label>هامش الصفحة (مم)</label><input type="number" name="page_margin" step="0.5" min="0" value="{{ $settings['page_margin'] }}"></div>
                <div><label>الفجوة بين الملصقات (مم)</label><input type="number" name="gap" step="0.5" min="0" value="{{ $settings['gap'] }}"></div>
                <div><label>عرض الوحدة (كثافة الباركود)</label><input type="number" name="module_width" step="0.1" min="0.5" max="3" value="{{ $settings['module_width'] }}"></div>
                <div><label>ارتفاع الباركود (بكسل)</label><input type="number" name="barcode_height" step="1" min="20" max="80" value="{{ $settings['barcode_height'] }}"></div>
                <div><label>إزاحة أفقية X (مم)</label><input type="number" name="offset_x" step="0.5" value="{{ $settings['offset_x'] }}"></div>
                <div><label>إزاحة رأسية Y (مم)</label><input type="number" name="offset_y" step="0.5" value="{{ $settings['offset_y'] }}"></div>
            </div>
            <div class="actions">
                <button type="button" class="secondary" onclick="applySettings()">↻ تطبيق</button>
                <button type="button" onclick="window.print()">🖨️ طباعة</button>
                <span class="count">{{ count($labels) }} ملصق</span>
            </div>
        </form>
    </div>

    <div class="labels-sheet">
        @foreach ($labels as $label)
            <div class="label">
                <div class="name">{{ $label['name'] }}</div>
                <div class="barcode">{!! $label['svg'] !!}</div>
                <div class="code">{{ $label['barcode'] }}</div>
            </div>
        @endforeach
    </div>

    <script>
        function applySettings(e) {
            if (e) e.preventDefault();
            var form = document.getElementById('settingsForm');
            var params = new URLSearchParams(window.location.search);
            new FormData(form).forEach(function (value, key) { params.set(key, value); });
            window.location.search = params.toString();
        }
    </script>
</body>
</html>
