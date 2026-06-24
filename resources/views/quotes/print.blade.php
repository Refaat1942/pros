@php
    use App\Support\ArabicAmount;

    $case       = $quote->caseRecord;
    $quoteDate  = $quote->quote_date ?? now();
    $totalSplit = ArabicAmount::split((float) $quote->total);
    $totalFmt   = ArabicAmount::splitFormatted((float) $quote->total);
    $amountWords = ArabicAmount::tafqeet((float) $quote->total);
    $refNo      = $quote->quote_no;
    $letterRef  = $quote->order_ref ?: ($case?->order_ref ?? '—');
    $minRows    = max($quote->items->count(), 5);
    $emptyRows  = $minRows - $quote->items->count();
    $dateDay    = $quoteDate->format('d');
    $dateMonth  = $quoteDate->format('m');
    $dateYear   = $quoteDate->format('Y');
    $dateDisplay = "{$dateDay}/{$dateMonth}/{$dateYear}";
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض سعر — {{ $quote->quote_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 14mm 16mm;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Traditional Arabic', 'Simplified Arabic', 'Arial', 'Tahoma', sans-serif;
            font-size: 15pt;
            line-height: 1.55;
            color: #000;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            width: 100%;
            max-width: 190mm;
            margin: 0 auto;
        }

        /* ── Header ── */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 10px;
        }

        .header-right {
            flex: 1;
            text-align: right;
            font-weight: 700;
            font-size: 14pt;
            line-height: 1.65;
        }

        .header-right .dept {
            text-decoration: underline;
        }

        .header-left {
            width: auto;
            min-width: 52mm;
            flex-shrink: 0;
            text-align: center;
        }

        .logo-placeholder,
        .quote-qr {
            width: 34mm;
            height: 34mm;
            margin: 0 auto 6px;
        }

        .logo-placeholder {
            border: 1.5px dashed #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9pt;
            color: #888;
        }

        .quote-qr {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quote-qr svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .header-meta {
            font-size: 11pt;
            font-weight: 600;
            text-align: right;
            line-height: 1.85;
        }

        .header-meta .date-line,
        .header-meta .ref-line {
            white-space: nowrap;
        }

        .header-meta .date-value {
            display: inline-block;
            border-bottom: 1px dotted #000;
            padding: 0 2px 1px;
            min-width: 22mm;
            text-align: center;
            font-variant-numeric: tabular-nums;
        }

        .header-meta .ref-value {
            display: inline-block;
            border-bottom: 1px dotted #000;
            padding: 0 2px 1px;
            max-width: 42mm;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: bottom;
        }

        .header-meta .dots {
            display: inline-block;
            min-width: 18mm;
            border-bottom: 1px dotted #000;
        }

        /* معاينة داخل مودال الاستقبال */
        body.embed-preview {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 16px;
            background: #f1f5f9;
        }

        body.embed-preview .sheet {
            width: 100%;
            max-width: 190mm;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 2px 16px rgba(15, 23, 42, 0.08);
            padding: 10px 12px;
        }

        body.embed-preview .no-print {
            display: none !important;
        }

        body.embed-preview .doc-header {
            gap: 20px;
        }

        body.embed-preview .header-left {
            min-width: 58mm;
        }

        /* ── Title ── */
        .doc-title {
            text-align: center;
            font-size: 20pt;
            font-weight: 800;
            text-decoration: underline;
            margin: 14px 0 18px;
            letter-spacing: 0.5px;
        }

        /* ── Body ── */
        .doc-body {
            font-size: 14pt;
            line-height: 2.1;
        }

        .doc-line {
            margin-bottom: 2px;
        }

        .fill {
            display: inline-block;
            border-bottom: 1px dotted #000;
            min-width: 55mm;
            padding: 0 4px 1px;
            font-weight: 700;
        }

        .fill-wide {
            min-width: 80mm;
        }

        .fill-date {
            min-width: 12mm;
            text-align: center;
        }

        /* ── Table ── */
        .pricing-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0 10px;
            font-size: 13pt;
        }

        .pricing-table th,
        .pricing-table td {
            border: 1.5px solid #000;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .pricing-table thead th {
            font-weight: 800;
            text-align: center;
            background: #fff;
        }

        .pricing-table .col-spec {
            width: 48%;
            text-align: right;
        }

        .pricing-table .col-amount {
            width: 22%;
        }

        .pricing-table .col-remarks {
            width: 18%;
        }

        .pricing-table .sub-head {
            font-size: 12pt;
        }

        .pricing-table tbody td {
            height: 9mm;
        }

        .pricing-table .num {
            text-align: center;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .pricing-table .total-label {
            text-align: center;
            font-weight: 800;
            font-size: 14pt;
        }

        .total-words {
            margin: 8px 0 18px;
            font-size: 14pt;
            line-height: 1.9;
        }

        .total-words .fill {
            min-width: 100mm;
        }

        /* ── Footer ── */
        .disclaimer {
            font-size: 13pt;
            line-height: 1.85;
            text-align: justify;
            margin-bottom: 28px;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-top: 36px;
            font-size: 13pt;
            font-weight: 700;
        }

        .sig-block {
            flex: 1;
            text-align: center;
        }

        .sig-block .sig-label {
            white-space: nowrap;
        }

        .sig-block .sig-line {
            margin-top: 36px;
            border-top: 1px dotted #000;
            padding-top: 4px;
        }

        .sig-block .sig-title {
            margin-top: 4px;
            font-size: 12pt;
            min-height: 1.4em;
        }

        /* ── Screen toolbar ── */
        .no-print {
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 100;
        }

        .no-print button {
            padding: 8px 18px;
            background: #1e3a5f;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
        }

        @media print {
            body {
                background: #fff;
            }

            .no-print {
                display: none !important;
            }

            .sheet {
                max-width: none;
            }

            .pricing-table thead {
                display: table-header-group;
            }

            .signatures {
                page-break-inside: avoid;
            }

            .disclaimer {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body class="@if(!empty($embed)) embed-preview @endif" @if($autoPrint ?? true) onload="window.print()" @endif>

<div class="no-print">
    <button type="button" onclick="window.print()">🖨️ طباعة</button>
</div>

<div class="sheet">

    {{-- ── Header ── --}}
    <header class="doc-header">
        <div class="header-right">
            <div>وزارة الدفاع</div>
            <div>مركز الطب الطبيعي والتأهيلي</div>
            <div>وعلاج الروماتيزم ق.م</div>
            <div>مصنع الأجهزة التعويضية</div>
            <div class="dept">القسم المالي</div>
        </div>
        <div class="header-left">
            @if (!empty($quoteQrSvg))
                <div class="quote-qr" aria-label="QR عرض السعر — {{ $refNo }}">
                    {!! $quoteQrSvg !!}
                </div>
            @else
                <div class="logo-placeholder" aria-hidden="true">شعار الجهة</div>
            @endif
            <div class="header-meta">
                <div class="date-line">التاريخ: <span class="date-value">{{ $dateDisplay }}</span>م</div>
                <div class="ref-line">عند الرد يذكر رقم: <span class="ref-value">{{ $refNo }}</span></div>
            </div>
        </div>
    </header>

    <h1 class="doc-title">عرض سعر</h1>

    {{-- ── Body ── --}}
    <section class="doc-body">
        <p class="doc-line">السادة / <span class="fill fill-wide">{{ $quote->company_name ?? '—' }}</span></p>
        <p class="doc-line">بعد التحية،،،</p>
        <p class="doc-line">
            بتوقيع الكشف الطبي على السيد /
            <span class="fill fill-wide">{{ $quote->patient_name }}</span>
        </p>
        <p class="doc-line">
            المحول بمعرفتكم بموجب / خطاب رقم
            (<span class="fill" style="min-width:28mm;">{{ $letterRef }}</span>)
            بتاريخ:
            <span class="fill fill-date">{{ $dateDisplay }}</span>م
        </p>
        <p class="doc-line" style="margin-top:6px;">نوصي له بالآتي :</p>
    </section>

    {{-- ── Pricing table ── --}}
    <table class="pricing-table">
        <thead>
            <tr>
                <th class="col-spec" rowspan="2">المواصفات</th>
                <th class="col-amount" colspan="2">المبلغ</th>
                <th class="col-remarks" rowspan="2">ملاحظات</th>
            </tr>
            <tr>
                <th class="sub-head">قرش</th>
                <th class="sub-head">جنيه</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $item)
                @php
                    $lineSplit = ArabicAmount::split((float) $item->amount);
                    $specLabel = $item->qty > 1
                        ? $item->name . ' — عدد ' . $item->qty
                        : $item->name;
                @endphp
                <tr>
                    <td class="col-spec">{{ $specLabel }}</td>
                    <td class="num">{{ str_pad((string) $lineSplit['piasters'], 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="num">{{ number_format($lineSplit['pounds']) }}</td>
                    <td>{{ $item->stock_item_code ?? '' }}</td>
                </tr>
            @endforeach
            @for ($i = 0; $i < $emptyRows; $i++)
                <tr>
                    <td class="col-spec">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor
            <tr>
                <td class="total-label">الإجمالي</td>
                <td class="num">{{ $totalFmt['piasters'] }}</td>
                <td class="num">{{ $totalFmt['pounds'] }}</td>
                <td>&nbsp;</td>
            </tr>
        </tbody>
    </table>

    <p class="total-words">
        الإجمالي ( فقط وقدره <span class="fill">{{ $amountWords }}</span> )
    </p>

    {{-- ── Disclaimer & signatures ── --}}
    <p class="disclaimer">
        ملحوظة: رجاء موافاتنا بالقيمة الموضحة بعاليه نقداً أو بشيك باسم ولى الأمر مركز الطب الطبيعي والتأهيلي وعلاج الروماتيزم للقوات المسلحة بالعجوزة وهذا العرض ساري لمدة ١٥ يوم وتفضلو بقبول فائق الإحترام.
    </p>

    <footer class="signatures">
        <div class="sig-block">
            <div class="sig-label">المختص</div>
            <div class="sig-line">&nbsp;</div>
            <div class="sig-title">&nbsp;</div>
        </div>
        <div class="sig-block">
            <div class="sig-label">المراجع</div>
            <div class="sig-line">&nbsp;</div>
            <div class="sig-title">&nbsp;</div>
        </div>
        <div class="sig-block approve">
            <div class="sig-label">يعتمد ،،،</div>
            <div class="sig-line">&nbsp;</div>
            <div class="sig-title">رئيس القسم المالي</div>
        </div>
    </footer>

</div>

</body>
</html>
