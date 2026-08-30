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
            font-weight: 900;
            text-decoration: underline;
            margin: 12px 0 8px;
        }
        .receipt-sub {
            text-align: center;
            font-size: 11pt;
            color: #333;
            margin-bottom: 14px;
        }
        .amount-box {
            border: 2px solid #000;
            border-radius: 4px;
            padding: 10px 14px;
            margin: 12px auto 16px;
            max-width: 95mm;
            text-align: center;
            font-size: 22pt;
            font-weight: 900;
        }
        .amount-box small {
            display: block;
            font-size: 9pt;
            font-weight: 700;
            color: #444;
            margin-top: 4px;
        }
        .words-line {
            border: 1px solid #000;
            padding: 10px 12px;
            margin: 10px 0 16px;
            font-size: 12.5pt;
            font-weight: 800;
            background: #fafafa;
        }
        .receipt-note {
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px solid #000;
            font-size: 10.5pt;
            color: #333;
            text-align: center;
        }
        .sign-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 24px;
            font-size: 12pt;
            font-weight: 800;
        }
        .sign-row .fill { min-width: 45mm; }
    </style>
</head>
<body @if($autoPrint ?? true) onload="window.print()" @endif>

@php
    $printCtx = \App\Support\DocumentPrintContext::fromRequest(request());
    $tpl = $documentTemplate ?? app(\App\Services\DocumentTemplateService::class)->for(
        'payment_receipt',
        $printCtx->department,
        $printCtx->stage,
    );
@endphp
@include('prints.partials.document-template-vars')

<div class="no-print">
    <button type="button" onclick="window.print()">🖨️ طباعة</button>
</div>

<div class="{{ $sheetClass }}">
    @include('prints.partials.org-header', [
        'dept' => $tpl['dept_label'] ?? 'الخزنة',
        'seal' => (bool) ($tpl['show_seal'] ?? true),
        'showLogo' => (bool) ($tpl['show_logo'] ?? true),
        'headerMeta' => '<div class="serial-red">'.e($receipt['payment_no']).'</div><div>التاريخ: <span class="fill">'.e($receipt['received_at'] ?? now()->format('d/m/Y H:i')).'</span></div>',
    ])

    <div class="receipt-title">{{ $tpl['doc_title'] ?? 'إيصال دفع' }}</div>
    <div class="receipt-sub">{{ $tpl['subtitle'] ?? '' }} · سيريال: {{ $receipt['payment_no'] }} · {{ $receipt['installment_label'] ?? 'دفعة 1' }}</div>

    <div class="amount-box">
        {{ number_format($receipt['amount'], 2) }} ج.م
        <small>مبلغ هذه الدفعة</small>
    </div>

    @if(!($receipt['fully_paid'] ?? true))
    <div class="words-line" style="background:#fffbeb;">
        المطلوب: {{ number_format($receipt['amount_due'] ?? 0, 2) }} ج.م ·
        المحصّل: {{ number_format($receipt['paid_total'] ?? 0, 2) }} ج.م ·
        المتبقي: {{ number_format($receipt['remaining'] ?? 0, 2) }} ج.م
    </div>
    @endif

    <div class="words-line">وقدره: {{ $receipt['amount_words'] }}</div>

    <table class="print-table meta-table avoid-break">
        <tbody>
            <tr>
                <th>استلمنا من السيد/ة</th>
                <td class="txt-right">{{ $receipt['patient_name'] }}</td>
            </tr>
            @if(!empty($receipt['patient_serial']))
            <tr>
                <th>سيريال ملف المريض</th>
                <td class="txt-right">{{ $receipt['patient_serial'] }}</td>
            </tr>
            @endif
            <tr>
                <th>الجهة</th>
                <td class="txt-right">{{ $receipt['entity'] }}</td>
            </tr>
            <tr>
                <th>رقم الحالة / المرجع</th>
                <td class="txt-right">{{ $receipt['case_no'] ?? '—' }} · {{ $receipt['order_ref'] ?? '—' }}</td>
            </tr>
            <tr>
                <th>وسيلة الدفع</th>
                <td class="txt-right">{{ $receipt['method_label'] }}</td>
            </tr>
            @if(!empty($receipt['reference']))
            <tr>
                <th>{{ $receipt['reference_label'] }}</th>
                <td class="txt-right">{{ $receipt['reference'] }}</td>
            </tr>
            @endif
            @if(!empty($receipt['notes']))
            <tr>
                <th>ملاحظات</th>
                <td class="txt-right">{{ $receipt['notes'] }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="sign-row">
        <span>{{ $tpl['signature_1'] ?? 'أمين الخزنة' }}: <span class="fill">{{ $receipt['received_by'] ?? '' }}</span></span>
        <span>{{ $tpl['signature_2'] ?? 'توقيع المستلم' }}: <span class="fill">&nbsp;</span></span>
    </div>

    <div class="receipt-note">
        {{ $tpl['footer_note'] ?: 'هذا الإيصال إثبات لاستلام المبلغ الموضح أعلاه — يُحتفظ بنسخة بملف الحالة.' }}
    </div>
</div>

</body>
</html>
