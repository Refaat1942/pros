<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة باركود — {{ $heading }}</title>
    <style>
        /* ورقة ملصقات حرارية: ملصق 38mm × 25mm، اثنان جنباً إلى جنب في الصف. */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --page-margin: {{ $settings['page_margin'] }}mm;
            --gap: {{ $settings['gap'] }}mm;
            --offset-x: {{ $settings['offset_x'] }}mm;
            --offset-y: {{ $settings['offset_y'] }}mm;
        }

        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: #f1f5f9;
            padding: 8px;
        }

        .toolbar {
            max-width: 760px;
            margin: 0 auto 12px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
        }

        .toolbar h1 { font-size: 15px; margin-bottom: 8px; color: #0f172a; }

        .toolbar .fields {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 8px 12px;
            margin-bottom: 10px;
        }

        .toolbar label { display: block; font-size: 12px; color: #475569; margin-bottom: 2px; }
        .toolbar input { width: 100%; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 13px; }

        .toolbar .actions { display: flex; gap: 8px; align-items: center; }
        .toolbar button {
            background: #2563eb; color: #fff; border: 0;
            padding: 9px 20px; border-radius: 8px; font-size: 14px;
            cursor: pointer; font-family: inherit;
        }
        .toolbar button.secondary { background: #475569; }
        .toolbar .count { font-size: 13px; color: #475569; }

        .sheet {
            display: flex;
            flex-wrap: wrap;
            gap: var(--gap);
            justify-content: flex-start;
            background: #fff;
            padding: var(--page-margin);
            width: calc(76mm + var(--gap) + (var(--page-margin) * 2));
            margin: 0 auto;
        }

        .label {
            width: 38mm;
            height: 25mm;
            border: 1px dashed #cbd5e1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1mm;
            padding-inline-start: calc(1mm + var(--offset-x));
            padding-top: calc(1mm + var(--offset-y));
            overflow: hidden;
            page-break-inside: avoid;
        }

        .label .name {
            font-size: 7pt;
            font-weight: 700;
            text-align: center;
            line-height: 1.1;
            max-height: 6mm;
            overflow: hidden;
            margin-bottom: 0.5mm;
        }

        .label .barcode { width: 34mm; height: 11mm; }
        .label .barcode svg { width: 100%; height: 100%; }

        .label .code {
            font-size: 8pt;
            font-weight: 700;
            direction: ltr;
            letter-spacing: 0.5px;
            margin-top: 0.5mm;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .sheet { width: auto; padding: 0; gap: var(--gap); }
            .label { border: none; }
            @page { margin: var(--page-margin); }
        }
    </style>
</head>
<body>
    <div class="toolbar">
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

    <div class="sheet">
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
