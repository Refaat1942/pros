<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بطاقة {{ $patient->patient_code }}</title>
    <style>
        /* بطاقة المريض — أبيض/أسود، واضحة وقابلة للطباعة */

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            background: #334155;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 32px 16px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── شريط أدوات الشاشة ───────────────────── */
        .toolbar {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            margin-bottom: 28px;
        }
        .toolbar h2 { color: #f1f5f9; font-size: 16px; font-weight: 700; }
        .toolbar .meta { color: #94a3b8; font-size: 13px; }
        .toolbar button {
            background: #059669;
            color: #fff;
            border: 0;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            margin-top: 8px;
        }
        .toolbar button:hover { background: #047857; }

        /* ── البطاقة ─────────────────────────────── */
        .card {
            width: 340px;
            background: #fff;
            color: #000;
            border: 2px solid #000;
            border-radius: 10px;
            padding: 16px 18px;
            display: flex;
            flex-direction: column;
        }

        /* رأس */
        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .card-head .center-name {
            font-size: 16px;
            font-weight: 800;
            line-height: 1.2;
        }
        .card-head .badge {
            font-size: 12px;
            font-weight: 800;
            border: 1.5px solid #000;
            border-radius: 6px;
            padding: 3px 10px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* جسم — QR يسار، بيانات يمين */
        .card-body {
            display: flex;
            flex-direction: row-reverse;
            align-items: center;
            gap: 16px;
        }
        .card-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .card-info .pt-name {
            font-size: 19px;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 2px;
        }
        .card-info .pt-row {
            font-size: 14px;
            font-weight: 600;
            line-height: 1.2;
        }
        .card-info .pt-row b { font-weight: 800; }
        .card-info .pt-sub {
            font-size: 13px;
            font-weight: 600;
            line-height: 1.2;
        }

        /* QR */
        .card-qr {
            width: 120px;
            height: 120px;
            flex-shrink: 0;
            border: 1.5px solid #000;
            border-radius: 6px;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-qr svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* تذييل */
        .card-foot {
            font-size: 11.5px;
            font-weight: 700;
            text-align: center;
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 14px;
        }
        .card-foot .track {
            display: block;
            direction: ltr;
            font-size: 10px;
            font-weight: 600;
            color: #1e293b;
            margin-top: 4px;
            word-break: break-all;
        }

        /* ── طباعة ───────────────────────────────── */
        @media print {
            body {
                background: #fff;
                padding: 0;
                display: block;
                min-height: auto;
            }
            .toolbar { display: none; }
            .card {
                border: 2px solid #000;
                margin: 12mm auto;
            }
            @page {
                size: A4;
                margin: 10mm;
            }
        }
    </style>
</head>
<body @if($autoPrint ?? true) onload="window.print()" @endif>

    <div class="toolbar">
        <h2>🏷️ بطاقة المريض الرقمية</h2>
        <span class="meta">{{ $patient->name }} &mdash; {{ $patient->patient_code }}</span>
        <button type="button" onclick="window.print()">🖨️ طباعة البطاقة</button>
    </div>

    <div class="card">

        {{-- رأس --}}
        <div class="card-head">
            <span class="center-name">مركز الأطراف الصناعية</span>
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
