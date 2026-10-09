@php
    use App\Services\DocumentTemplateService;
    use App\Support\DocumentPrintContext;
    use App\Support\QuotePrintPresenter;

    // النموذج الرسمي المعتمد لعرض الأسعار — docs/forms/quote-form.jpg
    // النصوص (السريان/التوريد/الضمان/الموقّع/التذييل) من مركز الوثائق ← «عرض سعر».
    // نفس النموذج يُستخدم للفاتورة الختامية بعد التسليم ($invoiceDoc + $invoiceCase).
    $isInvoice = isset($invoiceDoc);
    $printCtx = DocumentPrintContext::fromRequest(request(), $isInvoice ? $invoiceCase : $quote->caseRecord);
    $tpl      = $documentTemplate ?? app(DocumentTemplateService::class)->for('quote', $printCtx->department, $printCtx->stage);
    $doc      = $isInvoice ? $invoiceDoc : QuotePrintPresenter::document($quote, $tpl);
    $totals   = $isInvoice ? $doc['totals'] : ($printTotals ?? $doc['totals']);
    $settings = $doc['settings'];
    $branding = app(\App\Services\SettingService::class)->branding();
    $refNo    = $isInvoice ? ($doc['invoice_no'] ?: $doc['case_no']) : $quote->quote_no;
    $refLabel = $isInvoice ? 'رقم الفاتورة' : \App\Models\Quote::SERIAL_LABEL;
    $docTitle = $isInvoice ? 'فاتورة' : (trim((string) ($tpl['doc_title'] ?? '')) ?: 'عرض أسعار');
    $showLogo = (bool) ($tpl['show_logo'] ?? true);
    $showSeal = (bool) ($tpl['show_seal'] ?? true);
    $footerNote = trim((string) ($tpl['footer_note'] ?? ''));
    $sheetClass = \App\Support\DocumentTemplateSheet::sheetClass($tpl);
    $discountPct = rtrim(rtrim(number_format((float) ($totals['discount_percent'] ?? 0), 2, '.', ''), '0'), '.');
    $setting = fn (string $key) => trim((string) ($settings[$key] ?? ''));
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isInvoice ? 'فاتورة' : 'عرض سعر' }} — {{ $refNo }}</title>
    @include('prints.partials.a4-base')
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --ink: #111111;
            --line: #222222;
            --muted: #4b5563;
            --head: #e5e7eb;
            --bar: #1f2933;
            --corner: #1f78c8;
            --corner-dark: #0f2747;
            --red: #8b1a1a;
        }

        body {
            font-family: 'Cairo', 'Traditional Arabic', 'Simplified Arabic', 'Segoe UI', 'Tahoma', sans-serif;
            color: var(--ink);
            background: #fff;
            font-size: 11.5pt;
            font-weight: 700;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            position: relative;
            width: 210mm;
            max-width: none;
            min-height: 297mm;
            margin: 0 auto;
            padding: 14mm 17mm 12mm;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #fff;
        }

        /* ── زوايا الترويسة المائلة ── */
        .corner {
            position: absolute;
            width: 46mm;
            height: 30mm;
            pointer-events: none;
        }

        .corner::before,
        .corner::after {
            content: '';
            position: absolute;
            inset: 0;
        }

        /* كالنموذج المعتمد: أعلى اليمين وأسفل اليسار */
        .corner--top {
            top: 0;
            right: 0;
        }

        .corner--top::before {
            background: var(--corner);
            clip-path: polygon(0 0, 100% 0, 100% 100%);
        }

        .corner--top::after {
            background: var(--corner-dark);
            clip-path: polygon(38% 0, 100% 0, 100% 62%);
        }

        .corner--bottom {
            bottom: 0;
            left: 0;
        }

        .corner--bottom::before {
            background: var(--corner);
            clip-path: polygon(0 0, 0 100%, 100% 100%);
        }

        .corner--bottom::after {
            background: var(--corner-dark);
            clip-path: polygon(0 38%, 0 100%, 62% 100%);
        }

        /* ── الترويسة ── */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10mm;
            padding: 0 16mm 4mm 2mm; /* يمين: بعيداً عن الزاوية المائلة */
            border-bottom: 1.6px solid var(--line);
        }

        .org-lines {
            font-size: 15pt;
            font-weight: 800;
            line-height: 1.45;
            letter-spacing: 0.4px;
        }

        .header-side {
            display: flex;
            align-items: center;
            gap: 4mm;
        }

        .org-logo-thermal {
            width: 30mm;
            height: 30mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .org-logo-thermal__inner,
        .org-logo-thermal img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .org-logo-thermal--seal {
            border-radius: 50%;
        }

        .quote-ref {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1mm;
            font-size: 7.5pt;
            font-weight: 700;
            color: var(--muted);
        }

        .quote-ref__qr {
            width: 17mm;
            height: 17mm;
        }

        .quote-ref__qr svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .quote-ref__no {
            direction: ltr;
            font-variant-numeric: tabular-nums;
            color: var(--ink);
        }

        /* ── عنوان النموذج ── */
        .doc-title {
            margin: 8mm 4mm 6mm;
            padding: 1.6mm 0;
            background: var(--bar);
            color: #fff;
            text-align: center;
            font-size: 15pt;
            font-weight: 800;
            text-decoration: none;
            border-bottom: 3px solid #000;
        }

        /* ── الجداول ── */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        .grid th,
        .grid td {
            border: 1.2px solid var(--line);
            padding: 1.6mm 3mm;
            vertical-align: middle;
        }

        .grid .label {
            width: 30mm;
            text-align: right;
            font-size: 10.5pt;
            font-weight: 800;
            line-height: 1.3;
            white-space: normal;
        }

        .grid .value {
            text-align: center;
            font-weight: 700;
        }

        .info-table {
            margin: 0 4mm;
            width: calc(100% - 8mm);
        }

        .section-title {
            margin: 5mm 4mm 1.5mm;
            font-size: 10.5pt;
            font-weight: 800;
        }

        .section-title::before {
            content: '•';
            margin-left: 1mm;
        }

        .patient-table {
            margin: 0 4mm;
            width: calc(100% - 8mm);
            font-size: 10.5pt;
        }

        .patient-table .label {
            width: 30mm;
        }

        .patient-table .label--narrow {
            width: 18mm;
            text-align: center;
        }

        .patient-table .value--narrow {
            width: 22mm;
        }

        .spec-table {
            margin: 0 4mm;
            width: calc(100% - 8mm);
            font-size: 10pt;
        }

        .spec-table thead th {
            background: var(--head);
            font-size: 12.5pt;
            font-weight: 800;
            text-align: center;
        }

        .spec-table thead th.spec-head {
            letter-spacing: 6px;
            font-size: 14pt;
        }

        .spec-table td.label {
            width: 30mm;
        }

        .spec-table td.value {
            line-height: 1.55;
        }

        /* ── الإجماليات ── */
        .totals-table {
            margin: 4mm 4mm 0;
            width: calc(100% - 8mm);
        }

        .totals-table td {
            border: 2px solid var(--line);
            padding: 2mm 4mm;
        }

        .totals-table .t-label {
            width: 62%;
            text-align: center;
            font-size: 14pt;
            font-weight: 800;
        }

        .totals-table .t-value {
            text-align: left;
            direction: rtl;
            font-size: 11.5pt;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .totals-table .t-value .num {
            direction: ltr;
            unicode-bidi: embed;
        }

        .totals-table .t-discount .t-value {
            color: var(--red);
        }

        .totals-table .t-net .t-label {
            font-size: 17pt;
        }

        .footer-note {
            margin: 4mm 4mm 0;
            font-size: 10pt;
            line-height: 1.7;
            text-align: justify;
        }

        /* ── التوقيع ── */
        .signature {
            margin: 12mm 0 0 10mm;
            width: 70mm;
            text-align: center;
            font-size: 13pt;
            font-weight: 800;
            line-height: 1.6;
            align-self: flex-end;
        }

        .signature__line {
            letter-spacing: 3px;
        }

        /* ── التذييل ── */
        .doc-footer {
            margin-top: auto;
            padding-top: 3mm;
            border-top: 1.6px solid var(--line);
            display: flex;
            justify-content: space-between;
            gap: 4mm;
            font-size: 8.5pt;
            font-weight: 700;
            padding-left: 30mm; /* بعيداً عن الزاوية السفلية */
            position: relative;
            z-index: 1;
        }

        .doc-footer span {
            white-space: nowrap;
        }

        .doc-footer .ltr {
            direction: ltr;
            unicode-bidi: embed;
        }

        .spacer {
            min-height: 8mm;
        }

        /* ── شاشة / معاينة ── */
        .no-print {
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 100;
        }

        .no-print button {
            padding: 8px 18px;
            background: var(--corner-dark);
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
        }

        @media screen {
            body { background: #e5e7eb; padding: 16px 0; }
            .sheet { box-shadow: 0 4px 24px rgba(15, 23, 42, 0.12); }
        }

        body.embed-preview .no-print { display: none !important; }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            /* الورقة بعرض الصفحة كاملة (@page margin 0) — بدل حد 277mm العام في print-a4.css */
            .sheet,
            .sheet.doc-tpl-compact {
                box-shadow: none;
                min-height: 296mm;
                max-height: 296mm;
                overflow: hidden;
            }
            /* بدون «تنسيق مضغوط»: يُسمح بالامتداد لأكثر من صفحة إن طالت المواصفات */
            .sheet:not(.doc-tpl-compact) {
                max-height: none;
                overflow: visible;
            }
            .spec-table thead { display: table-header-group; }
            .totals-table, .signature { page-break-inside: avoid; }
        }
    </style>
</head>
<body class="@if(!empty($embed)) embed-preview @endif" @if($autoPrint ?? true) onload="window.print()" @endif>

<div class="no-print">
    <button type="button" onclick="window.print()">🖨️ طباعة</button>
</div>

<div class="{{ $sheetClass }}">
    <div class="corner corner--top" aria-hidden="true"></div>
    <div class="corner corner--bottom" aria-hidden="true"></div>

    <header class="doc-header">
        <div class="org-lines">
            @foreach ($branding['lines'] as $line)
                <div>{{ $line }}</div>
            @endforeach
        </div>
        <div class="header-side">
            @if (!empty($quoteQrSvg))
                <div class="quote-ref" aria-label="QR {{ $refLabel }} — {{ $refNo }}">
                    <div class="quote-ref__qr">{!! $quoteQrSvg !!}</div>
                    <div>{{ $refLabel }}</div>
                    <div class="quote-ref__no">{{ $refNo }}</div>
                </div>
            @else
                <div class="quote-ref">
                    <div>{{ $refLabel }}</div>
                    <div class="quote-ref__no">{{ $refNo }}</div>
                </div>
            @endif
            @if ($showLogo || $showSeal)
                @include('prints.partials.org-logo', ['logoSize' => '30mm', 'seal' => $showSeal, 'showLogo' => $showLogo])
            @endif
        </div>
    </header>

    <h1 class="doc-title">{{ $docTitle }}</h1>

    <table class="grid info-table">
        @if ($isInvoice)
            <tr>
                <td class="label">تاريخ الفاتورة (التسليم)</td>
                <td class="value">{{ $doc['delivered_at'] }}</td>
            </tr>
            <tr>
                <td class="label">رقم الحالة / أمر الشغل</td>
                <td class="value">{{ $doc['case_no'] ?: '—' }} · {{ $doc['work_order_no'] ?: '—' }}@if ($doc['quote_no'] !== '') · عرض السعر {{ $doc['quote_no'] }}@endif</td>
            </tr>
            <tr>
                <td class="label">الجهة</td>
                <td class="value">{{ $doc['entity'] }}</td>
            </tr>
            <tr>
                <td class="label">المسؤول عن السداد</td>
                <td class="value">{{ $doc['payer'] }}</td>
            </tr>
        @else
            <tr>
                <td class="label">تاريخ عرض السعر</td>
                <td class="value">{{ $doc['date'] }}</td>
            </tr>
            <tr>
                <td class="label">مدة سريان عرض السعر</td>
                <td class="value">{{ $doc['validity'] }}</td>
            </tr>
            <tr>
                <td class="label">الجهة</td>
                <td class="value">{{ $doc['entity'] }}</td>
            </tr>
        @endif
    </table>

    <div class="section-title">بيانات المريض :</div>
    <table class="grid patient-table">
        <tr>
            <td class="label">الإســـم</td>
            <td class="value">{{ $doc['patient_name'] }}</td>
            <td class="label label--narrow">السن</td>
            <td class="value value--narrow">{{ $doc['age'] !== null ? $doc['age'].' عام' : '' }}</td>
        </tr>
        <tr>
            <td class="label">رقم المريض</td>
            <td class="value" colspan="3">{{ $doc['patient_code'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">نوع الإصابة</td>
            <td class="value">{{ $doc['injury'] }}</td>
            <td class="label label--narrow">تاريخها</td>
            <td class="value value--narrow">{{ $doc['injury_year'] }}</td>
        </tr>
    </table>

    <div class="section-title">المواصفات الفنية والتكلفة :</div>
    <table class="grid spec-table">
        <thead>
            <tr>
                <th>البيان / المكونات</th>
                <th class="spec-head">المواصفات التفصيلية</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($doc['spec_rows'] as $row)
                <tr>
                    <td class="label">{{ $row['label'] }}</td>
                    <td class="value">{{ $row['detail'] }}</td>
                </tr>
            @empty
                <tr>
                    <td class="label">&nbsp;</td>
                    <td class="value">&nbsp;</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals-table">
        @if (! empty($totals['has_discount']))
            <tr>
                <td class="t-label">إجمالي السعر قبل الخصم</td>
                <td class="t-value">ج.م. <span class="num">{{ QuotePrintPresenter::money((float) $totals['gross_total']) }}</span></td>
            </tr>
            <tr class="t-discount">
                <td class="t-label">نسبة الخصم ({{ $discountPct }}%)</td>
                <td class="t-value">ج.م. <span class="num">{{ QuotePrintPresenter::money((float) $totals['discount_amount']) }}</span></td>
            </tr>
            <tr class="t-net">
                <td class="t-label">إجمالي السعر بعد الخصم</td>
                <td class="t-value">ج.م. <span class="num">{{ QuotePrintPresenter::money((float) $totals['display_total']) }}</span></td>
            </tr>
        @else
            <tr class="t-net">
                <td class="t-label">{{ $isInvoice ? 'إجمالي الفاتورة' : 'إجمالي السعر' }}</td>
                <td class="t-value">ج.م. <span class="num">{{ QuotePrintPresenter::money((float) $totals['display_total']) }}</span></td>
            </tr>
        @endif
        @if ($isInvoice && $doc['paid'] !== null)
            <tr>
                <td class="t-label">المدفوع بالخزنة</td>
                <td class="t-value">ج.م. <span class="num">{{ QuotePrintPresenter::money((float) $doc['paid']) }}</span></td>
            </tr>
            <tr class="t-net">
                <td class="t-label">المتبقي</td>
                <td class="t-value">ج.م. <span class="num">{{ QuotePrintPresenter::money((float) $doc['remaining']) }}</span></td>
            </tr>
        @endif
    </table>

    @if (! $isInvoice && $footerNote !== '')
        <div class="footer-note">{{ $footerNote }}</div>
    @endif

    <div class="signature">
        <div class="signature__line">التوقيـــع (&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</div>
        @if ($setting('signatory_name') !== '')
            <div>{{ $setting('signatory_name') }}</div>
        @endif
        @if ($setting('signatory_title') !== '')
            <div>{{ $setting('signatory_title') }}</div>
        @endif
    </div>

    <div class="spacer"></div>

    @if ($setting('footer_phones') !== '' || $setting('footer_address') !== '' || $setting('footer_website') !== '')
        <footer class="doc-footer">
            @if ($setting('footer_phones') !== '')
                <span>☎ <span class="ltr">{{ $setting('footer_phones') }}</span></span>
            @endif
            @if ($setting('footer_address') !== '')
                <span>📍 {{ $setting('footer_address') }}</span>
            @endif
            @if ($setting('footer_website') !== '')
                <span>🌐 <span class="ltr">{{ $setting('footer_website') }}</span></span>
            @endif
        </footer>
    @endif
</div>

</body>
</html>
