@php
    $patient = $case->patient;
    $bom     = $case->bom;
    $items   = $bom?->items ?? collect();
    $specLines = max($items->count(), 12);
    $emptySpec = $specLines - $items->count();
    $dateDisplay = now()->format('d/m/Y');
    $approvalNo = $case->quote_no ?? '—';
    $approvalDate = $case->approval_date?->format('d/m/Y') ?? '—';
    $valueDisplay = number_format((float) ($case->quote_total ?: $case->total_cost), 0);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إذن شغل — {{ $case->work_order_no ?? $case->order_ref }}</title>
    @include('prints.partials.a4-base')
    <style>
        .meta-grid { font-size: 12.5pt; line-height: 2.1; margin-bottom: 10px; }
        .spec-section { margin: 10px 0; }
        .spec-title {
            text-align: center;
            font-weight: 800;
            font-size: 14pt;
            margin-bottom: 6px;
        }
        .spec-layout {
            display: flex;
            gap: 8px;
            border: 1.5px solid #000;
            min-height: 72mm;
        }
        .spec-sketch {
            width: 38mm;
            border-left: 1.5px solid #000;
            flex-shrink: 0;
        }
        .spec-lines { flex: 1; padding: 4px 6px; }
        .spec-line {
            border-bottom: 1px dotted #888;
            min-height: 5.2mm;
            font-size: 11.5pt;
            padding: 1px 2px;
        }
        .staff-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 10px 0 6px;
            font-size: 12pt;
            font-weight: 700;
        }
        .trial-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 24px;
            font-size: 11.5pt;
            margin: 8px 0 12px;
        }
        .labor-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-top: 6px;
        }
        .labor-table th,
        .labor-table td {
            border: 1px solid #000;
            padding: 3px 4px;
            text-align: center;
            vertical-align: middle;
        }
        .labor-table th { font-weight: 700; background: #f5f5f5; }
        .labor-table .h-row { height: 9mm; }
        .footer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-top: 14px;
            font-size: 11pt;
            font-weight: 700;
        }
        .footer-note {
            margin-top: 8px;
            font-size: 10.5pt;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }
    </style>
</head>
<body @if($autoPrint ?? true) onload="window.print()" @endif>

<div class="no-print">
    <button type="button" onclick="window.print()">🖨️ طباعة</button>
</div>

<div class="sheet">
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
            <div class="header-meta">
                <div class="serial-red">{{ $case->order_ref }}</div>
                <div>إذن شغل رقم: <span class="fill" style="min-width:18mm;">{{ $case->work_order_no ?? '—' }}</span></div>
                <div>التاريخ: <span class="fill" style="min-width:22mm;">{{ $dateDisplay }}</span>م</div>
                <div>رقم الحالة: ( <span class="fill" style="min-width:16mm;">{{ $case->case_no }}</span> )</div>
            </div>
        </div>
    </header>

    <section class="meta-grid">
        <div class="line">الإسم: <span class="fill fill-wide">{{ $patient?->name ?? $bom?->patient_name ?? '—' }}</span></div>
        <div class="line">
            رقم التصديق: <span class="fill" style="min-width:28mm;">{{ $approvalNo }}</span>
            تاريخه: <span class="fill" style="min-width:22mm;">{{ $approvalDate }}</span>
        </div>
        <div class="line">الجهة المحول منها: <span class="fill fill-wide">{{ $case->company_name ?? $case->sovereign_entity ?? '—' }}</span></div>
        <div class="line">العنوان: <span class="fill fill-wide">&nbsp;</span></div>
        <div class="line">
            القيمة: <span class="fill" style="min-width:24mm;">{{ $valueDisplay }}</span>
            ( نقدي / شيك / قرار / خطاب تحويل )
        </div>
    </section>

    <section class="spec-section avoid-break">
        <div class="spec-title">المواصفات</div>
        <div class="spec-layout">
            <div class="spec-sketch" aria-hidden="true">&nbsp;</div>
            <div class="spec-lines">
                @foreach ($items as $item)
                    <div class="spec-line">
                        {{ $item->name }}@if($item->qty > 1) — ×{{ $item->qty }}@endif
                        @if($item->stock_item_code) ({{ $item->stock_item_code }})@endif
                    </div>
                @endforeach
                @for ($i = 0; $i < $emptySpec; $i++)
                    <div class="spec-line">&nbsp;</div>
                @endfor
            </div>
        </div>
    </section>

    <div class="staff-row">
        <span>الموظف المختص: <span class="fill" style="min-width:30mm;">&nbsp;</span></span>
        <span>رئيس القسم: <span class="fill" style="min-width:30mm;">&nbsp;</span></span>
        <span>تنفذ . مدير الإنتاج: <span class="fill" style="min-width:24mm;">&nbsp;</span></span>
    </div>

    <div class="trial-row">
        <span>تاريخ التجربة الأولى: <span class="fill" style="min-width:22mm;">&nbsp;</span></span>
        <span>توقيع المختص: <span class="fill" style="min-width:28mm;">&nbsp;</span></span>
        <span>تاريخ التجربة الثانية: <span class="fill" style="min-width:22mm;">&nbsp;</span></span>
    </div>

    <table class="labor-table avoid-break">
        <thead>
            <tr>
                <th rowspan="2">القسم المختص</th>
                <th rowspan="2">اسم القائم بالتشغيل</th>
                <th colspan="2">التاريخ ( من — إلى )</th>
                <th colspan="2">ساعة التشغيل ( ساعة — دقيقة )</th>
                <th rowspan="2">رئيس قسم الإنتاج</th>
            </tr>
            <tr>
                <th>من</th>
                <th>إلى</th>
                <th>ساعة</th>
                <th>دقيقة</th>
            </tr>
        </thead>
        <tbody>
            @for ($r = 0; $r < 3; $r++)
                <tr class="h-row">
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="footer-note">
        <span>ملاحظة المصنع: <span class="fill" style="min-width:40mm;">&nbsp;</span></span>
        <span>مجموعة ساعات العمل: <span class="fill" style="min-width:20mm;">&nbsp;</span></span>
    </div>

    <div class="footer-grid avoid-break">
        <div>مراجعة التصنيع: <span class="fill" style="min-width:24mm;">&nbsp;</span></div>
        <div>مراجعة الخامات: <span class="fill" style="min-width:24mm;">&nbsp;</span></div>
        <div>ملاحظات: <span class="fill" style="min-width:20mm;">&nbsp;</span></div>
    </div>
    <div class="footer-note" style="margin-top:10px;">
        <span>توقيع المستلم وعنوانه: <span class="fill fill-wide">&nbsp;</span></span>
    </div>
    <div class="footer-note">
        <span>التاريخ: <span class="fill" style="min-width:22mm;">&nbsp;</span> / <span class="fill" style="min-width:10mm;">&nbsp;</span> / 20<span class="fill" style="min-width:8mm;">&nbsp;</span> م</span>
        <span>توقيع ( &nbsp;&nbsp; ) مدير الإنتاج</span>
    </div>
</div>

</body>
</html>
