<!DOCTYPE html>
<html lang="ar" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة باركود — {{ $heading }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/print-labels.css') }}?v={{ filemtime(public_path('assets/css/print-labels.css')) }}">
    <style>
        :root {
            --label-width: {{ $settings['label_width_mm'] }}mm;
            --label-height: {{ $settings['label_height_mm'] }}mm;
            --margin-left: {{ $settings['margin_left_mm'] }}mm;
            --margin-top: {{ $settings['margin_top_mm'] }}mm;
            --page-margin: {{ $settings['page_margin'] }}mm;
            --gap: {{ $settings['gap'] }}mm;
            --offset-x: {{ $settings['offset_x'] }}mm;
            --offset-y: {{ $settings['offset_y'] }}mm;
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
        <h1>🏷️ إعدادات الطباعة — {{ $heading }}</h1>
        <p class="labels-toolbar-hint">
            ملصق <strong>{{ number_format($settings['label_width_mm'], 1) }} × {{ number_format($settings['label_height_mm'], 1) }} مم</strong>
            (≈ {{ number_format($settings['label_width_in'], 3) }}" × {{ number_format($settings['label_height_in'], 2) }}")
            — طبّق نفس المقاس في driver الطابعة (Custom size).
        </p>
        <form id="settingsForm" onsubmit="applySettings(event)">
            <div class="fields">
                <div>
                    <label>عدد النسخ لكل صنف</label>
                    <input type="number" name="copies" min="1" max="200" value="{{ $settings['copies'] }}">
                    <span class="field-help">{{ $settings['field_help']['copies'] ?? '' }}</span>
                </div>
                <div>
                    <label>عرض الملصق (مم)</label>
                    <input type="number" name="label_width_mm" step="0.1" min="10" max="300" value="{{ $settings['label_width_mm'] }}">
                    <span class="field-help">{{ $settings['field_help']['label_width_mm'] ?? '' }}</span>
                </div>
                <div>
                    <label>ارتفاع الملصق (مم)</label>
                    <input type="number" name="label_height_mm" step="0.1" min="10" max="300" value="{{ $settings['label_height_mm'] }}">
                    <span class="field-help">{{ $settings['field_help']['label_height_mm'] ?? '' }}</span>
                </div>
                <div>
                    <label>إزاحة يسار (معايرة)</label>
                    <input type="number" name="margin_left_mm" step="0.5" value="{{ $settings['margin_left_mm'] }}">
                    <span class="field-help">{{ $settings['field_help']['margin_left_mm'] ?? '' }}</span>
                </div>
                <div>
                    <label>إزاحة أعلى (معايرة)</label>
                    <input type="number" name="margin_top_mm" step="0.5" value="{{ $settings['margin_top_mm'] }}">
                    <span class="field-help">{{ $settings['field_help']['margin_top_mm'] ?? '' }}</span>
                </div>
                <div>
                    <label>عرض الوحدة (كثافة الباركود)</label>
                    <input type="number" name="module_width" step="0.1" min="0.5" max="3" value="{{ $settings['module_width'] }}">
                    <span class="field-help">{{ $settings['field_help']['module_width'] ?? '' }}</span>
                </div>
                <div>
                    <label>ارتفاع الباركود (بكسل)</label>
                    <input type="number" name="barcode_height" step="1" min="20" max="80" value="{{ $settings['barcode_height'] }}">
                    <span class="field-help">{{ $settings['field_help']['barcode_height'] ?? '' }}</span>
                </div>
                <div>
                    <label>إزاحة المحتوى X (مم)</label>
                    <input type="number" name="offset_x" step="0.5" value="{{ $settings['offset_x'] }}">
                    <span class="field-help">{{ $settings['field_help']['offset_x'] ?? '' }}</span>
                </div>
                <div>
                    <label>إزاحة المحتوى Y (مم)</label>
                    <input type="number" name="offset_y" step="0.5" value="{{ $settings['offset_y'] }}">
                    <span class="field-help">{{ $settings['field_help']['offset_y'] ?? '' }}</span>
                </div>
                <div>
                    <label>الفجوة بين الملصقات (مم)</label>
                    <input type="number" name="gap" step="0.5" min="0" value="{{ $settings['gap'] }}">
                    <span class="field-help">{{ $settings['field_help']['gap'] ?? '' }}</span>
                </div>
                <div>
                    <label>هامش المعاينة (مم)</label>
                    <input type="number" name="page_margin" step="0.5" min="0" value="{{ $settings['page_margin'] }}">
                    <span class="field-help">{{ $settings['field_help']['page_margin'] ?? '' }}</span>
                </div>
            </div>
            <details class="labels-print-guide">
                <summary>📖 دليل ضبط أي طابعة</summary>
                <ol>
                    <li>افتح <strong>Printing Preferences → Page Setup</strong> في driver الطابعة.</li>
                    <li>اختر <strong>Custom</strong> وادخل نفس العرض/الارتفاع (مم) المعروضين أعلاه.</li>
                    <li>في Chrome: More settings → Scale = <strong>100%</strong>، Margins = <strong>None</strong>، Background graphics = <strong>On</strong>.</li>
                    <li>لو الطباعة متزحزحة: عدّل «إزاحة يسار» و«إزاحة أعلى» ثم اضغط تطبيق.</li>
                </ol>
            </details>
            <div class="actions">
                <button type="button" class="secondary" onclick="applySettings()">↻ تطبيق</button>
                <button type="button" onclick="printLabels()">🖨️ طباعة</button>
                <span class="count">{{ count($labels) }} ملصق</span>
            </div>
        </form>
    </div>

    <div class="labels-sheet">
        @foreach ($labels as $label)
            <div class="label">
                <div class="name">{{ $label['name'] }}</div>
                <div class="barcode">
                    <img class="barcode-img"
                         src="{{ $label['svg_data_uri'] }}"
                         alt="{{ $label['barcode'] }}"
                         decoding="sync">
                </div>
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

        function printLabels() {
            var imgs = document.querySelectorAll('.barcode-img');
            var waits = Array.prototype.map.call(imgs, function (img) {
                if (img.complete) {
                    return Promise.resolve();
                }

                return new Promise(function (resolve) {
                    img.onload = resolve;
                    img.onerror = resolve;
                });
            });

            Promise.all(waits).then(function () {
                window.print();
            });
        }
    </script>
</body>
</html>
