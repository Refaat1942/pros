@php
    use App\Models\BomItem;
    use App\Support\ArabicAmount;

    $case        = $quote->caseRecord;
    $quoteDate   = $quote->quote_date ?? now();
    $totals      = $printTotals ?? ['display_total' => (float) $quote->total, 'has_discount' => false, 'discount_percent' => 0, 'discount_amount' => 0, 'gross_total' => (float) $quote->total];
    $totalFmt    = ArabicAmount::splitFormatted((float) $totals['display_total']);
    $amountWords = ArabicAmount::tafqeet((float) $totals['display_total']);
    $grossFmt    = ! empty($totals['has_discount'])
        ? ArabicAmount::splitFormatted((float) $totals['gross_total'])
        : null;
    $discountFmt = ! empty($totals['has_discount'])
        ? ArabicAmount::splitFormatted((float) $totals['discount_amount'])
        : null;
    $refNo       = $quote->quote_no;
    $letterRef   = $quote->order_ref ?: ($case?->order_ref ?? '—');

    // العرض للجهة/المريض يعرض بنود التوصيف فقط دون تفصيل أسعار البنود؛
    // القيمة تظهر مرة واحدة في سطر الإجمالي فقط.
    $specItems   = $quote->items->where('source', BomItem::SOURCE_SPEC)->values();
    if ($specItems->isEmpty()) {
        $specItems = $quote->items->values();
    }
    $minRows     = max($specItems->count(), 4);
    $emptyRows   = $minRows - $specItems->count();
    $dateDisplay = $quoteDate->format('d/m/Y');
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
            margin: 10mm 12mm 14mm;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --ink: #111827;
            --muted: #4b5563;
            --line: #1f2937;
            --soft: #f3f4f6;
            --accent: #1e3a5f;
        }

        body {
            font-family: 'Traditional Arabic', 'Simplified Arabic', 'Segoe UI', 'Tahoma', sans-serif;
            font-size: 13.5pt;
            line-height: 1.55;
            color: var(--ink);
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            width: 100%;
            max-width: 190mm;
            margin: 0 auto;
            border: 1.5px solid var(--line);
            padding: 10mm 11mm 12mm;
        }

        /* ── Header ── */
        .doc-header {
            direction: ltr;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 10px 12px;
            align-items: start;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--line);
            margin-bottom: 12px;
        }

        .header-aside {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            min-width: 58mm;
        }

        .header-main {
            direction: rtl;
            display: flex;
            justify-content: flex-end;
            align-items: flex-start;
        }

        .header-org {
            text-align: right;
        }

        .header-org__tail {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            margin-top: 4px;
        }

        .header-org__qr {
            display: block;
        }

        .header-org__line {
            font-weight: 700;
            font-size: 13pt;
            line-height: 1.65;
        }

        .header-org__dept {
            display: inline-block;
            padding: 2px 10px;
            border: 1px solid var(--line);
            font-size: 11.5pt;
            font-weight: 800;
        }

        .org-logo-thermal {
            width: 30mm;
            height: 30mm;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .org-logo-thermal__inner {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .org-logo-thermal img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: grayscale(100%) contrast(1.62) brightness(1);
        }

        .org-logo-thermal--seal {
            width: 32mm;
            height: 32mm;
            padding: 1.2mm;
            border: 1.1px solid #1a1a1a;
            border-radius: 50%;
            background: #fff;
        }

        .quote-qr-box {
            width: 24mm;
            padding: 1.2mm;
            border: 1px solid var(--line);
            background: #fff;
            text-align: center;
            line-height: 1;
        }

        .quote-qr-box__code {
            width: 20mm;
            height: 20mm;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quote-qr-box__code svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .header-meta {
            width: 100%;
            margin-top: 8mm;
            border: 1px solid var(--line);
            background: var(--soft);
            font-size: 10pt;
            font-weight: 700;
            line-height: 1.6;
        }

        .header-meta__row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 4px 8px;
            border-bottom: 1px solid #d1d5db;
        }

        .header-meta__row:last-child {
            border-bottom: none;
        }

        .header-meta__label {
            color: var(--muted);
            font-weight: 600;
            white-space: nowrap;
        }

        .header-meta__value {
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            text-align: left;
        }

        /* ── Title ── */
        .doc-title-wrap {
            text-align: center;
            margin: 12px 0 16px;
        }

        .doc-title {
            display: inline-block;
            min-width: 52%;
            padding: 8px 24px;
            border: 2px solid var(--line);
            background: var(--soft);
            font-size: 19pt;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        /* ── Info panel ── */
        .info-panel {
            border: 1px solid var(--line);
            margin-bottom: 14px;
            background: #fff;
        }

        .info-panel__head {
            padding: 6px 12px;
            background: var(--soft);
            border-bottom: 1px solid var(--line);
            font-size: 12pt;
            font-weight: 800;
        }

        .info-panel__body {
            padding: 10px 12px 12px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 16px;
        }

        .info-field {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .info-field--full {
            grid-column: 1 / -1;
        }

        .info-field__label {
            font-size: 10pt;
            font-weight: 700;
            color: var(--muted);
        }

        .info-field__value {
            min-height: 1.45em;
            padding: 4px 8px;
            border: 1px solid #cbd5e1;
            background: #fafafa;
            font-size: 12.5pt;
            font-weight: 800;
            line-height: 1.45;
        }

        .intro-text {
            margin-top: 10px;
            font-size: 12.5pt;
            line-height: 1.85;
        }

        .intro-text p + p {
            margin-top: 4px;
        }

        /* ── Table ── */
        .pricing-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 10px;
            font-size: 12pt;
        }

        .pricing-table th,
        .pricing-table td {
            border: 1px solid var(--line);
            padding: 7px 8px;
            vertical-align: middle;
        }

        .pricing-table thead th {
            background: var(--soft);
            font-weight: 800;
            text-align: center;
            font-size: 11.5pt;
        }

        .pricing-table .col-no {
            width: 8%;
            text-align: center;
            font-weight: 700;
            color: var(--muted);
        }

        .pricing-table .col-spec {
            width: 40%;
            text-align: right;
        }

        .pricing-table .col-qty {
            width: 8%;
            text-align: center;
            font-variant-numeric: tabular-nums;
        }

        .pricing-table .col-amount {
            width: 13%;
        }

        .pricing-table .col-remarks {
            width: 18%;
        }

        .pricing-table tbody td {
            min-height: 8mm;
        }

        .pricing-table tbody tr:nth-child(even) td {
            background: #fafafa;
        }

        .pricing-table .num {
            text-align: center;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        .pricing-table .total-row td {
            background: #eef2f7 !important;
            font-weight: 800;
        }

        .pricing-table .total-label {
            text-align: center;
            font-size: 12.5pt;
        }

        .pricing-table .discount-row td {
            background: #fff7ed !important;
        }

        /* ── Summary ── */
        .amount-box {
            margin: 10px 0 16px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            background: var(--soft);
            font-size: 12.5pt;
            line-height: 1.75;
        }

        .amount-box strong {
            font-weight: 800;
        }

        .amount-box__words {
            display: inline;
            padding: 0 4px;
            border-bottom: 1px dotted var(--line);
            font-weight: 800;
        }

        /* ── Footer ── */
        .disclaimer {
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-right: 4px solid var(--accent);
            background: #fff;
            font-size: 11.5pt;
            line-height: 1.85;
            text-align: justify;
            margin-bottom: 22px;
        }

        .disclaimer__title {
            font-weight: 800;
            margin-bottom: 4px;
            font-size: 11pt;
            color: var(--accent);
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 8px;
        }

        .sig-block {
            border: 1px dashed #9ca3af;
            min-height: 28mm;
            padding: 8px 10px 10px;
            text-align: center;
            display: flex;
            flex-direction: column;
        }

        .sig-block .sig-label {
            font-size: 12pt;
            font-weight: 800;
        }

        .sig-block .sig-space {
            flex: 1;
            margin: 8px 0;
        }

        .sig-block .sig-title {
            font-size: 10.5pt;
            font-weight: 700;
            color: var(--muted);
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
        }

        .sig-block--approve {
            border-style: solid;
            border-color: var(--line);
        }

        /* ── Screen toolbar & embed ── */
        .no-print {
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 100;
        }

        .no-print button {
            padding: 8px 18px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
        }

        body.embed-preview {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 16px;
            background: #e5e7eb;
        }

        body.embed-preview .sheet {
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.12);
        }

        body.embed-preview .no-print {
            display: none !important;
        }

        @media print {
            body { background: #fff; }

            .no-print { display: none !important; }

            .sheet {
                max-width: none;
                border: none;
                padding: 0;
            }

            .pricing-table thead {
                display: table-header-group;
            }

            .signatures,
            .disclaimer,
            .amount-box {
                page-break-inside: avoid;
            }

            .org-logo-thermal img {
                filter: grayscale(100%) contrast(1.75) brightness(0.96);
            }
        }
    </style>
</head>
<body class="@if(!empty($embed)) embed-preview @endif" @if($autoPrint ?? true) onload="window.print()" @endif>

<div class="no-print">
    <button type="button" onclick="window.print()">🖨️ طباعة</button>
</div>

<div class="sheet">

    <header class="doc-header">
        <div class="header-aside">
            @include('prints.partials.org-logo', ['logoSize' => '30mm', 'seal' => true])
            <div class="header-meta" dir="rtl">
                <div class="header-meta__row">
                    <span class="header-meta__label">التاريخ</span>
                    <span class="header-meta__value">{{ $dateDisplay }} م</span>
                </div>
                <div class="header-meta__row">
                    <span class="header-meta__label">{{ \App\Models\Quote::SERIAL_LABEL }}</span>
                    <span class="header-meta__value">{{ $refNo }}</span>
                </div>
            </div>
        </div>
        <div aria-hidden="true"></div>
        <div class="header-main">
            <div class="header-org">
                <div class="header-org__line">وزارة الدفاع</div>
                <div class="header-org__line">مركز الطب الطبيعي والتأهيلي</div>
                <div class="header-org__line">وعلاج الروماتيزم ق.م</div>
                <div class="header-org__line">مصنع الأجهزة التعويضية</div>
                <div class="header-org__tail">
                    <div class="header-org__dept">القسم المالي</div>
                    @if (!empty($quoteQrSvg))
                        <div class="header-org__qr">
                            <div class="quote-qr-box" aria-label="QR عرض السعر — {{ $refNo }}">
                                <div class="quote-qr-box__code">{!! $quoteQrSvg !!}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <div class="doc-title-wrap">
        <h1 class="doc-title">عرض سعر</h1>
    </div>

    <section class="info-panel">
        <div class="info-panel__head">بيانات العرض</div>
        <div class="info-panel__body">
            <div class="info-grid">
                <div class="info-field info-field--full">
                    <span class="info-field__label">السادة / جهة التعاقد</span>
                    <span class="info-field__value">{{ $quote->company_name ?? '—' }}</span>
                </div>
                <div class="info-field">
                    <span class="info-field__label">اسم المريض</span>
                    <span class="info-field__value">{{ $quote->patient_name }}</span>
                </div>
                <div class="info-field">
                    <span class="info-field__label">خطاب التحويل رقم</span>
                    <span class="info-field__value">{{ $letterRef }}</span>
                </div>
                <div class="info-field">
                    <span class="info-field__label">تاريخ خطاب التحويل</span>
                    <span class="info-field__value">{{ $dateDisplay }} م</span>
                </div>
                <div class="info-field">
                    <span class="info-field__label">مرجع الطلب</span>
                    <span class="info-field__value">{{ $quote->order_ref ?? ($case?->order_ref ?? '—') }}</span>
                </div>
            </div>
            <div class="intro-text">
                <p>بعد التحية،،،</p>
                <p>بتوقيع الكشف الطبي على المريض المذكور أعلاه، المحول بمعرفتكم، نوصي له بالآتي:</p>
            </div>
        </div>
    </section>

    <table class="pricing-table">
        <thead>
            <tr>
                <th class="col-no" rowspan="2">م</th>
                <th class="col-spec" rowspan="2">المواصفات</th>
                <th class="col-qty" rowspan="2">الكمية</th>
                <th class="col-amount" colspan="2">المبلغ</th>
                <th class="col-remarks" rowspan="2">ملاحظات</th>
            </tr>
            <tr>
                <th class="col-amount">قرش</th>
                <th class="col-amount">جنيه</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($specItems as $index => $item)
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td class="col-spec">{{ $item->name }}</td>
                    <td class="num">{{ $item->qty }}</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endforeach
            @for ($i = 0; $i < $emptyRows; $i++)
                <tr>
                    <td class="col-no">&nbsp;</td>
                    <td class="col-spec">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td class="num">&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor
            @if (! empty($totals['has_discount']))
                <tr class="total-row">
                    <td colspan="3" class="total-label">الإجمالي قبل الخصم</td>
                    <td class="num">{{ $grossFmt['piasters'] }}</td>
                    <td class="num">{{ $grossFmt['pounds'] }}</td>
                    <td>&nbsp;</td>
                </tr>
                <tr class="total-row discount-row">
                    <td colspan="3" class="total-label">خصم جهة التعاقد ({{ rtrim(rtrim(number_format((float) $totals['discount_percent'], 2, '.', ''), '0'), '.') }}%)</td>
                    <td class="num">− {{ $discountFmt['piasters'] }}</td>
                    <td class="num">− {{ $discountFmt['pounds'] }}</td>
                    <td>&nbsp;</td>
                </tr>
            @endif
            <tr class="total-row">
                <td colspan="3" class="total-label">الإجمالي</td>
                <td class="num">{{ $totalFmt['piasters'] }}</td>
                <td class="num">{{ $totalFmt['pounds'] }}</td>
                <td>&nbsp;</td>
            </tr>
        </tbody>
    </table>

    <div class="amount-box">
        <strong>الإجمالي (فقط وقدره):</strong>
        <span class="amount-box__words">{{ $amountWords }}</span>
    </div>

    <div class="disclaimer">
        <div class="disclaimer__title">ملحوظة</div>
        رجاء موافاتنا بالقيمة الموضحة بعاليه نقداً أو بشيك باسم ولى الأمر مركز الطب الطبيعي والتأهيلي وعلاج الروماتيزم للقوات المسلحة بالعجوزة. هذا العرض ساري لمدة ١٥ يوم. وتفضلوا بقبول فائق الإحترام.
    </div>

    <footer class="signatures">
        <div class="sig-block">
            <div class="sig-label">المختص</div>
            <div class="sig-space">&nbsp;</div>
            <div class="sig-title">التوقيع</div>
        </div>
        <div class="sig-block">
            <div class="sig-label">المراجع</div>
            <div class="sig-space">&nbsp;</div>
            <div class="sig-title">التوقيع</div>
        </div>
        <div class="sig-block sig-block--approve">
            <div class="sig-label">يعتمد ،،،</div>
            <div class="sig-space">&nbsp;</div>
            <div class="sig-title">رئيس القسم المالي</div>
        </div>
    </footer>

</div>

</body>
</html>
