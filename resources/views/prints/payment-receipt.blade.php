@php
    $dateDisplay = $receipt['received_at'] ?? now()->format('d/m/Y H:i');
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إيصال دفع — {{ $receipt['payment_no'] }}</title>
    @include('prints.partials.a4-base')
    <style>
        .receipt-title {
            text-align: center;
            font-size: 20pt;
            font-weight: 800;
            text-decoration: underline;
            margin: 14px 0 6px;
        }
        .receipt-sub { text-align: center; font-size: 11pt; color: #333; margin-bottom: 14px; }
        .amount-box {
            border: 2px solid #000;
            border-radius: 6px;
            padding: 8px 14px;
            margin: 10px auto 16px;
            max-width: 90mm;
            text-align: center;
            font-size: 20pt;
            font-weight: 800;
        }
        .amount-box small { display:block; font-size: 9pt; font-weight: 600; color:#555; }
        .rec-grid { font-size: 13pt; line-height: 2.15; margin: 10px 0; }
        .rec-grid .fill { min-width: 55mm; }
        .words-line {
            border: 1px dashed #000;
            border-radius: 6px;
            padding: 8px 12px;
            margin: 10px 0 16px;
            font-size: 12.5pt;
            font-weight: 700;
            background: #fafafa;
        }
        .sign-row {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-top: 26px;
            font-size: 12pt;
            font-weight: 700;
        }
        .sign-row .fill { min-width: 45mm; }
        .receipt-note { margin-top: 14px; font-size: 10.5pt; color: #444; }
    </style>
</head>
<body @if($autoPrint ?? true) onload="window.print()" @endif>

<div class="no-print">
    <button type="button" onclick="window.print()">🖨️ طباعة</button>
</div>

<div class="sheet">
    <header class="doc-header">
        <div class="header-right">
            @foreach (app(\App\Services\SettingService::class)->branding()['lines'] as $line)
                <div>{{ $line }}</div>
            @endforeach
            <div class="dept">الخزنة — القسم المالي</div>
        </div>
        <div class="header-left">
            @include('prints.partials.org-logo', ['logoSize' => '30mm', 'seal' => true])
            <div class="header-meta">
                <div class="serial-red">{{ $receipt['payment_no'] }}</div>
                <div>التاريخ: <span class="fill" style="min-width:30mm;">{{ $dateDisplay }}</span></div>
            </div>
        </div>
    </header>

    <div class="receipt-title">إيصال دفع</div>
    <div class="receipt-sub">سيريال الإيصال: {{ $receipt['payment_no'] }}</div>

    <div class="amount-box">
        {{ number_format($receipt['amount'], 2) }} ج.م
        <small>المبلغ المستلم</small>
    </div>

    <div class="words-line">
        وقدره: {{ $receipt['amount_words'] }}
    </div>

    <section class="rec-grid">
        <div class="line">استلمنا من السيد/ة: <span class="fill fill-wide">{{ $receipt['patient_name'] }}</span></div>
        @if(!empty($receipt['patient_serial']))
            <div class="line">سيريال ملف المريض: <span class="fill" style="min-width:35mm;">{{ $receipt['patient_serial'] }}</span></div>
        @endif
        <div class="line">الجهة: <span class="fill fill-wide">{{ $receipt['entity'] }}</span></div>
        <div class="line">
            رقم الحالة: <span class="fill" style="min-width:28mm;">{{ $receipt['case_no'] ?? '—' }}</span>
            المرجع: <span class="fill" style="min-width:28mm;">{{ $receipt['order_ref'] ?? '—' }}</span>
        </div>
        <div class="line">وسيلة الدفع: <span class="fill" style="min-width:35mm;">{{ $receipt['method_label'] }}</span></div>
        @if(!empty($receipt['reference']))
            <div class="line">{{ $receipt['reference_label'] }}: <span class="fill" style="min-width:40mm;">{{ $receipt['reference'] }}</span></div>
        @endif
        @if(!empty($receipt['notes']))
            <div class="line">ملاحظات: <span class="fill fill-wide">{{ $receipt['notes'] }}</span></div>
        @endif
    </section>

    <div class="sign-row">
        <span>أمين الخزنة: <span class="fill">{{ $receipt['received_by'] ?? '' }}</span></span>
        <span>توقيع المستلم: <span class="fill">&nbsp;</span></span>
    </div>

    <div class="receipt-note">
        هذا الإيصال إثبات لاستلام المبلغ الموضح أعلاه — يُحتفظ بنسخة بملف الحالة.
    </div>
</div>

</body>
</html>
