@php
    $voucherNo   = $voucher['voucher_no'] ?? '—';
    $patientName = $voucher['patient_name'] ?? '—';
    $companyName = $voucher['company_name'] ?? '—';
    $items       = $voucher['items'] ?? collect();
    $dateDisplay = now()->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إذن صرف — {{ $voucherNo }}</title>
    @include('prints.partials.a4-base')
    <style>
        .body-lines { margin: 24px 0 36px; font-size: 15pt; line-height: 2.4; }
        .items-block {
            border-bottom: 1px dotted #000;
            min-height: 28mm;
            padding: 4px 0 8px;
            display: block;
            line-height: 1.9;
        }
        .sign-footer {
            margin-top: 48px;
            text-align: left;
            font-weight: 700;
            font-size: 14pt;
        }
        .sign-footer .sig-line {
            margin-top: 32px;
            border-top: 1px dotted #000;
            width: 55mm;
        }
        .sign-footer .sig-title { margin-top: 4px; font-size: 12pt; }
    </style>
</head>
<body @if($autoPrint ?? true) onload="window.print()" @endif>

<div class="no-print">
    <button type="button" onclick="window.print()">🖨️ طباعة</button>
</div>

<div class="sheet avoid-break">
    <header class="doc-header">
        <div class="header-right">
            <div>وزارة الدفاع</div>
            <div>مركز الطب الطبيعي والتأهيلي</div>
            <div>وعلاج الروماتيزم ق.م</div>
            <div>مصنع الأجهزة التعويضية</div>
            <div class="dept">القسم المالي</div>
        </div>
        <div class="header-left">
            @include('prints.partials.org-logo', ['logoSize' => '30mm', 'seal' => true])
        </div>
    </header>

    <h1 class="doc-title">إذن صرف رقم ( <span class="fill" style="min-width:22mm;">{{ $voucherNo }}</span> )</h1>

    <section class="body-lines">
        <p class="line">السيد / رئيس المخازن : <span class="fill fill-wide">&nbsp;</span></p>
        <p class="line">
            رجاء التكرم بصرف :
            <span class="items-block fill-wide" style="min-width:85%;">
                @if ($items->isNotEmpty())
                    {{ $items->map(fn ($item) => $item->name . ($item->qty > 1 ? ' — عدد ' . $item->qty : ''))->implode(' — ') }}
                @else
                    &nbsp;
                @endif
            </span>
        </p>
        <p class="line">اسم المريض : <span class="fill fill-wide">{{ $patientName }}</span></p>
        <p class="line">
            بناء على التصديق الوارد لنا من :
            <span class="fill fill-wide">{{ $companyName }}</span>
        </p>
    </section>

    <footer class="sign-footer">
        <div>يعتمد ،،</div>
        <div class="sig-line">&nbsp;</div>
        <div class="sig-title">رئيس القسم المالي</div>
    </footer>
</div>

</body>
</html>
