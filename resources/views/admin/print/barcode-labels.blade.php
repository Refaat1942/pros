<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة باركود — {{ $item->code }}</title>
    <style>
        /* ورقة ملصقات حرارية: ملصق 38mm × 25mm، اثنان جنباً إلى جنب في الصف. */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: #f1f5f9;
            padding: 8px;
        }

        .toolbar {
            text-align: center;
            margin-bottom: 12px;
        }

        .toolbar button {
            background: #2563eb; color: #fff; border: 0;
            padding: 10px 22px; border-radius: 8px; font-size: 14px;
            cursor: pointer; font-family: inherit;
        }

        .sheet {
            display: flex;
            flex-wrap: wrap;
            gap: 2mm;
            justify-content: flex-start;
            background: #fff;
            padding: 4mm;
            width: 84mm; /* ملصقان (38mm) + الفجوات */
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
            .sheet { width: auto; padding: 0; gap: 0; }
            .label { border: none; }
            @page { margin: 4mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">🖨️ طباعة الملصقات</button>
        <span style="margin-inline-start:12px;font-size:13px;color:#475569;">
            {{ $item->name }} — {{ $copies }} ملصق
        </span>
    </div>

    <div class="sheet">
        @for ($i = 0; $i < $copies; $i++)
            <div class="label">
                <div class="name">{{ $item->name }}</div>
                <div class="barcode">{!! $barcodeSvg !!}</div>
                <div class="code">{{ $item->barcode }}</div>
            </div>
        @endfor
    </div>
</body>
</html>
