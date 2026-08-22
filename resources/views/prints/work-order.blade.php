@php
    use App\Support\CaseFinancialSummary;

    $patient = $case->patient;
    $bom     = $case->bom;
    $items   = $bom?->items ?? collect();
    $dateDisplay = now()->format('d/m/Y');
    $approvalNo = $case->quote_no ?? '—';
    $approvalDate = $case->approval_date?->format('d/m/Y') ?? '—';
    $valueDisplay = number_format(CaseFinancialSummary::billableAmount($case), 0);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إذن شغل — {{ $case->work_order_no ?? $case->order_ref }}</title>
    @include('prints.partials.a4-base')
    <style>
        .wo-title {
            text-align: center;
            font-size: 18pt;
            font-weight: 900;
            text-decoration: underline;
            margin: 10px 0 14px;
        }
        .staff-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 12px 0 8px;
            font-size: 11.5pt;
            font-weight: 800;
        }
        .trial-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 24px;
            font-size: 11pt;
            margin: 8px 0 12px;
        }
        .labor-table .h-row { height: 9mm; }
        .footer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-top: 14px;
            font-size: 11pt;
            font-weight: 800;
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
            @foreach (app(\App\Services\SettingService::class)->branding()['lines'] as $line)
                <div>{{ $line }}</div>
            @endforeach
            <div class="dept">قسم الإنتاج — إذن شغل</div>
        </div>
        <div class="header-left">
            @include('prints.partials.org-logo', ['logoSize' => '30mm', 'seal' => true])
            <div class="header-meta">
                <div class="serial-red">{{ $case->order_ref }}</div>
                <div>إذن شغل رقم: <span class="fill">{{ $case->work_order_no ?? '—' }}</span></div>
                <div>التاريخ: <span class="fill">{{ $dateDisplay }}</span> م</div>
                <div>رقم الحالة: <span class="fill">{{ $case->case_no }}</span></div>
            </div>
        </div>
    </header>

    <div class="wo-title">إذن شغل</div>

    <table class="print-table meta-table avoid-break">
        <tbody>
            <tr>
                <th>الاسم</th>
                <td class="txt-right">{{ $patient?->name ?? $bom?->patient_name ?? '—' }}</td>
            </tr>
            <tr>
                <th>رقم التصديق / التاريخ</th>
                <td class="txt-right">{{ $approvalNo }} · {{ $approvalDate }}</td>
            </tr>
            <tr>
                <th>الجهة المحول منها</th>
                <td class="txt-right">{{ $case->displayEntity() }}</td>
            </tr>
            <tr>
                <th>العنوان</th>
                <td class="txt-right">&nbsp;</td>
            </tr>
            <tr>
                <th>القيمة (نقدي / شيك / قرار / خطاب)</th>
                <td class="txt-right num">{{ $valueDisplay }} ج.م</td>
            </tr>
        </tbody>
    </table>

    <table class="print-table items-table avoid-break" style="margin-top:14px;">
        <caption>المواصفات / بنود العمل</caption>
        <thead>
            <tr>
                <th style="width:18%">الكود</th>
                <th class="col-name">الصنف</th>
                <th style="width:12%">الكمية</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td class="mono">{{ $item->stock_item_code ?: '—' }}</td>
                    <td class="col-name txt-right">{{ $item->name }}</td>
                    <td class="num">{{ $item->qty }}</td>
                </tr>
            @empty
                <tr><td colspan="3">—</td></tr>
            @endforelse
            @for ($i = max(0, 8 - $items->count()); $i > 0; $i--)
                <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
            @endfor
        </tbody>
    </table>

    <div class="staff-row">
        <span>الموظف المختص: <span class="fill">&nbsp;</span></span>
        <span>رئيس القسم: <span class="fill">&nbsp;</span></span>
        <span>مدير الإنتاج: <span class="fill">&nbsp;</span></span>
    </div>

    <div class="trial-row">
        <span>تاريخ التجربة الأولى: <span class="fill">&nbsp;</span></span>
        <span>توقيع المختص: <span class="fill">&nbsp;</span></span>
        <span>تاريخ التجربة الثانية: <span class="fill">&nbsp;</span></span>
    </div>

    <table class="print-table labor-table avoid-break">
        <thead>
            <tr>
                <th rowspan="2">القسم المختص</th>
                <th rowspan="2">اسم القائم بالتشغيل</th>
                <th colspan="2">التاريخ (من — إلى)</th>
                <th colspan="2">ساعة التشغيل</th>
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
                    <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="footer-note">
        <span>ملاحظة المصنع: <span class="fill">&nbsp;</span></span>
        <span>مجموعة ساعات العمل: <span class="fill">&nbsp;</span></span>
    </div>

    <div class="footer-grid avoid-break">
        <div>مراجعة التصنيع: <span class="fill">&nbsp;</span></div>
        <div>مراجعة الخامات: <span class="fill">&nbsp;</span></div>
        <div>ملاحظات: <span class="fill">&nbsp;</span></div>
    </div>
    <div class="footer-note" style="margin-top:10px;">
        <span>توقيع المستلم وعنوانه: <span class="fill fill-wide">&nbsp;</span></span>
    </div>
    <div class="footer-note">
        <span>التاريخ: <span class="fill">&nbsp;</span> / <span class="fill">&nbsp;</span> / 20<span class="fill">&nbsp;</span> م</span>
        <span>توقيع مدير الإنتاج: <span class="fill">&nbsp;</span></span>
    </div>
</div>

</body>
</html>
