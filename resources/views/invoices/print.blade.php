@php
    /**
     * الفاتورة الختامية — تصميم «الكروت» (رقم 4 اللي اختاره العميل).
     * البيانات من InvoicePrintPresenter، والنصوص/الموقّعون من قالب «فاتورة» في مركز الوثائق.
     */
    use App\Support\QuotePrintPresenter;

    $doc = $invoiceDoc;
    $tpl = $documentTemplate ?? $doc['settings'] ?? [];
    $setting = fn (string $key) => trim((string) ($tpl[$key] ?? ''));
    $totals = $doc['totals'];
    $money = fn ($v) => QuotePrintPresenter::money((float) $v);
    $discountPct = rtrim(rtrim(number_format((float) ($totals['discount_percent'] ?? 0), 2, '.', ''), '0'), '.');
    $settings = app(\App\Services\SettingService::class);
    $branding = $settings->branding();
    $logoPath = $branding['logo_path'] ?? '';
    $showLogo = (bool) ($tpl['show_logo'] ?? true) && $settings->brandingLogoExists($logoPath);
    $status = $doc['status'] ?? ['label' => '', 'tone' => 'entity'];
    $specRows = array_values(array_filter($doc['spec_rows'] ?? [], fn ($r) => ! in_array($r['label'], ['مدة التوريد', 'الضمان'], true)));
    $warranty = $setting('warranty');
    $docTitle = $setting('doc_title') ?: 'فاتورة';
    $extraSignatures = array_values(array_filter(
        is_array($tpl[\App\Services\DocumentTemplateService::SIGNATURES_KEY] ?? null) ? $tpl[\App\Services\DocumentTemplateService::SIGNATURES_KEY] : [],
        fn ($s) => is_array($s) && (trim((string) ($s['title'] ?? '')) !== '' || trim((string) ($s['name'] ?? '')) !== ''),
    ));
    $signatures = array_merge(
        [['title' => 'توقيع المستلم', 'name' => $doc['patient_name'] ?? '']],
        ($setting('signatory_title') !== '' || $setting('signatory_name') !== '')
            ? [['title' => $setting('signatory_title'), 'name' => $setting('signatory_name')]]
            : [],
        $extraSignatures,
    );
    $footer = array_filter([
        $setting('footer_phones') !== '' ? '☎ '.$setting('footer_phones') : null,
        $setting('footer_address') !== '' ? '📍 '.$setting('footer_address') : null,
        $setting('footer_website') !== '' ? '🌐 '.$setting('footer_website') : null,
    ]);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $docTitle }} — {{ $doc['invoice_no'] ?: $doc['case_no'] }}</title>
    @include('partials.web-fonts')
    <script src="{{ asset('assets/js/shared/print-fit.js') }}?v={{ filemtime(public_path('assets/js/shared/print-fit.js')) }}" defer></script>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --pri: #6d28d9; --pri2: #9333ea; --teal: #0f766e; --ln: #e5e7eb; --mut: #6b7280; }
        body { font-family: 'Cairo', 'Tajawal', 'Segoe UI', Tahoma, sans-serif; color: #111827; background: #e5e7eb;
               -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .num { direction: ltr; unicode-bidi: embed; }
        .no-print { text-align: center; padding: 10px; }
        .no-print button { padding: 8px 22px; border: 0; border-radius: 8px; background: var(--pri); color: #fff; font-family: inherit; font-weight: 700; cursor: pointer; }
        body.embed-preview .no-print { display: none; }

        .sheet { width: 210mm; min-height: 297mm; margin: 0 auto 16px; padding: 10mm; background: #f3f4f6;
                 display: flex; flex-direction: column; gap: 4mm; box-shadow: 0 4px 24px rgba(15, 23, 42, .12); }
        .card { background: #fff; border-radius: 14px; padding: 4mm 5mm; box-shadow: 0 1px 3px rgba(0, 0, 0, .06); }
        .hd { display: flex; justify-content: space-between; align-items: center; gap: 8mm;
              background: linear-gradient(135deg, var(--pri), var(--pri2)); color: #fff; }
        .hd .brand { display: flex; gap: 10px; align-items: center; }
        .hd .logo { width: 18mm; height: 18mm; border-radius: 50%; background: #fff; color: var(--pri);
                    display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 8pt; overflow: hidden; flex-shrink: 0; }
        .hd .logo img { width: 100%; height: 100%; object-fit: contain; }
        .hd .lines div { font-size: 9.5pt; font-weight: 700; line-height: 1.45; }
        .hd .lines div:first-child { font-size: 10.5pt; font-weight: 900; }
        .hd h1 { font-size: 26pt; font-weight: 900; text-align: left; line-height: 1.1; }
        .hd h1 small { display: block; font-size: 10.5pt; font-weight: 800; opacity: .92; margin-top: 2px; }

        .chips { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3mm; }
        .chip { background: #fff; border-radius: 12px; padding: 3mm 4mm; border-top: 4px solid var(--pri); }
        .chip:nth-child(2) { border-color: var(--teal); }
        .chip:nth-child(3) { border-color: #d97706; }
        .chip:nth-child(4) { border-color: #2563eb; }
        .chip span { display: block; font-size: 8.5pt; color: var(--mut); font-weight: 700; }
        .chip b { font-size: 10.5pt; }

        .two { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
        h3 { font-size: 10.5pt; font-weight: 900; color: #374151; margin-bottom: 2mm; }
        dl { display: grid; grid-template-columns: 26mm 1fr; font-size: 9.8pt; row-gap: 2px; }
        dt { color: var(--mut); font-weight: 700; }
        dd { font-weight: 800; }

        table { width: 100%; border-collapse: collapse; font-size: 9.8pt; }
        .spec td { padding: 3.5px 6px; border-bottom: 1px solid var(--ln); vertical-align: top; }
        .spec tr:last-child td { border-bottom: 0; }
        .spec td.l { width: 42mm; font-weight: 800; color: var(--pri); }
        .pay th { text-align: right; font-size: 8.8pt; color: var(--mut); padding: 3px 6px; border-bottom: 1px solid var(--ln); white-space: nowrap; }
        .pay td { padding: 3px 6px; font-size: 9.2pt; border-bottom: 1px solid var(--ln); white-space: nowrap; }
        .pay .empty { color: var(--mut); text-align: center; white-space: normal; padding: 6px; }

        .tot div { display: flex; justify-content: space-between; font-size: 10pt; font-weight: 800; padding: 3px 0; }
        .tot .disc { color: #b91c1c; }
        .big { margin-top: 2mm; border-radius: 12px; background: #ecfdf5; color: var(--teal); padding: 3mm 4mm;
               display: flex; justify-content: space-between; align-items: center; }
        .big span { font-weight: 800; }
        .big b { font-size: 16pt; font-weight: 900; }
        .words { font-size: 8.8pt; font-weight: 800; color: #374151; margin-top: 2mm; line-height: 1.6; }

        .status { display: inline-block; border-radius: 999px; padding: 1px 12px; font-weight: 900; font-size: 9pt; color: #fff; }
        .status--paid { background: var(--teal); }
        .status--due { background: #b91c1c; }
        .status--entity { background: #2563eb; }
        .status--slate { background: #475569; }

        .note { font-size: 8.8pt; color: #4b5563; line-height: 1.6; }
        .sigs { display: flex; gap: 4mm; align-items: flex-end; margin-top: auto; }
        .sigs .sig { flex: 1; text-align: center; font-size: 9pt; font-weight: 800; }
        .sigs .sig .ln { margin-top: 9mm; border-top: 1px dashed #9ca3af; padding-top: 2px; color: var(--mut); font-weight: 700; font-size: 8.5pt; min-height: 5mm; }
        .sigs .qr { width: 22mm; flex-shrink: 0; text-align: center; font-size: 7.5pt; color: var(--mut); }
        .sigs .qr svg { width: 20mm; height: 20mm; display: block; margin: 0 auto 1mm; }
        .foot { text-align: center; font-size: 8.3pt; color: var(--mut); }

        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .sheet { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body class="@if(!empty($embed)) embed-preview @endif" data-fit-mm="294" data-print-width-mm="210" @if($autoPrint ?? true) onload="window.print()" @endif>

<div class="no-print"><button type="button" onclick="window.print()">🖨️ طباعة</button></div>

<div class="sheet">
    <div class="card hd">
        <div class="brand">
            <div class="logo">
                @if ($showLogo)
                    <img src="{{ $settings->brandingLogoUrl($logoPath) }}" alt="">
                @else
                    شعار
                @endif
            </div>
            <div class="lines">
                @foreach ($branding['lines'] as $line)
                    <div>{{ $line }}</div>
                @endforeach
            </div>
        </div>
        <h1>{{ $docTitle }}<small>رقم الفاتورة: <span class="num">{{ $doc['invoice_no'] ?: '—' }}</span></small></h1>
    </div>

    <div class="chips">
        <div class="chip"><span>تاريخ الفاتورة (التسليم)</span><b>{{ $doc['invoice_date'] ?: $doc['date'] }}</b></div>
        <div class="chip"><span>رقم الحالة</span><b class="num">{{ $doc['case_no'] ?: '—' }}</b></div>
        <div class="chip"><span>عرض السعر</span><b class="num">{{ $doc['quote_no'] ?: '—' }}</b></div>
        <div class="chip"><span>أمر الشغل</span><b class="num">{{ $doc['work_order_no'] ?: '—' }}</b></div>
    </div>

    <div class="two">
        <div class="card">
            <h3>👤 المريض</h3>
            <dl>
                <dt>الاسم</dt><dd>{{ $doc['patient_name'] ?: '—' }}</dd>
                <dt>رقم المريض</dt><dd class="num">{{ $doc['patient_code'] ?: '—' }}</dd>
                <dt>السن</dt><dd>{{ $doc['age'] !== null ? $doc['age'].' عام' : '—' }}</dd>
                <dt>الإصابة</dt><dd>{{ $doc['injury'] ?: '—' }}</dd>
            </dl>
        </div>
        <div class="card">
            <h3>🏢 جهة السداد</h3>
            <dl>
                <dt>الجهة</dt><dd>{{ $doc['entity'] ?: '—' }}</dd>
                <dt>السداد</dt><dd>{{ $doc['payer'] ?: '—' }}</dd>
                @if (! empty($totals['has_discount']))
                    <dt>{{ ! empty($totals['discount_is_amount']) ? 'الخصم' : 'التعاقد' }}</dt>
                    <dd>{{ ! empty($totals['discount_is_amount']) ? $money($totals['discount_amount']).' ج.م' : 'خصم '.$discountPct.'%' }}</dd>
                @endif
                @if (($doc['approval_ref'] ?? '') !== '')
                    <dt>الموافقة</dt><dd>{{ $doc['approval_ref'] }}</dd>
                @endif
                <dt>الحالة</dt><dd><span class="status status--{{ $status['tone'] }}">{{ $status['label'] }}</span></dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <h3>🦿 المواصفات الفنية للطرف المُسلَّم</h3>
        <table class="spec">
            @forelse ($specRows as $row)
                @if (trim((string) $row['label']) !== '')
                    <tr><td class="l">{{ $row['label'] }}</td><td>{{ $row['detail'] }}</td></tr>
                @else
                    <tr><td colspan="2">{{ $row['detail'] }}</td></tr>
                @endif
            @empty
                <tr><td colspan="2">—</td></tr>
            @endforelse
        </table>
    </div>

    <div class="two">
        <div class="card">
            <h3>💳 الدفعات</h3>
            <table class="pay">
                <tr><th>#</th><th>الإيصال</th><th>التاريخ</th><th>الطريقة</th><th>المبلغ</th></tr>
                @forelse ($doc['payments'] ?? [] as $i => $payment)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="num">{{ $payment['no'] }}</td>
                        <td>{{ $payment['date'] }}</td>
                        <td>{{ $payment['method'] }}</td>
                        <td class="num">{{ $money($payment['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">{{ ! empty($doc['is_cash']) ? 'لا توجد دفعات مسجّلة' : 'السداد على الجهة — لا تحصيل من المريض' }}</td></tr>
                @endforelse
            </table>
        </div>
        <div class="card">
            <h3>🧾 الحساب</h3>
            <div class="tot">
                @if (! empty($totals['has_discount']))
                    <div><span>الإجمالي قبل الخصم</span><span class="num">{{ $money($totals['gross_total']) }}</span></div>
                    <div class="disc">
                        <span>{{ ! empty($totals['discount_is_amount']) ? 'الخصم' : 'الخصم ('.$discountPct.'%)' }}</span>
                        <span class="num">{{ $money($totals['discount_amount']) }} −</span>
                    </div>
                @endif
                @if ($doc['paid'] !== null)
                    <div><span>المدفوع بالخزنة</span><span class="num">{{ $money($doc['paid']) }}</span></div>
                    <div><span>المتبقي</span><span class="num">{{ $money($doc['remaining']) }}</span></div>
                @endif
            </div>
            <div class="big"><span>{{ ! empty($totals['has_discount']) ? 'الصافي' : 'إجمالي الفاتورة' }}</span><b class="num">{{ $money($totals['display_total']) }} ج.م</b></div>
            <div class="words">{{ $doc['amount_words'] ?? '' }}</div>
        </div>
    </div>

    @if ($warranty !== '' || $setting('footer_note') !== '')
        <div class="card note">
            @if ($warranty !== '')<div><b>🛡️ الضمان:</b> {{ $warranty }}</div>@endif
            @if ($setting('footer_note') !== '')<div>{{ $setting('footer_note') }}</div>@endif
        </div>
    @endif

    <div class="card sigs">
        @foreach ($signatures as $sig)
            <div class="sig">
                {{ $sig['title'] ?? '' }}
                <div class="ln">{{ $sig['name'] ?? '' }}</div>
            </div>
        @endforeach
        @if (! empty($quoteQrSvg))
            <div class="qr">{!! $quoteQrSvg !!}للتحقق</div>
        @endif
    </div>

    @if ($footer !== [])
        <div class="foot">{{ implode('  ·  ', $footer) }}</div>
    @endif
</div>

</body>
</html>
