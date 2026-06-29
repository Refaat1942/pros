@php
    $case = $case ?? $spec->caseRecord;
    $submittedAt = $spec->submitted_at ?? $spec->updated_at ?? now();
    $dateDisplay = $submittedAt->format('d/m/Y');
    $minRows = max($spec->items->count(), 6);
    $emptyRows = $minRows - $spec->items->count();
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير التوصيف الفني — {{ $spec->order_ref }}</title>
    @include('prints.partials.a4-base')
    <style>
        .meta-grid {
            font-size: 12.5pt;
            line-height: 2;
            margin: 8px 0 14px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5pt;
            margin: 10px 0;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 5px 8px;
            text-align: center;
            vertical-align: middle;
        }
        .items-table th {
            font-weight: 800;
            background: #f5f5f5;
        }
        .items-table .col-name { text-align: right; }
        .items-table .col-code { font-family: monospace; font-size: 10.5pt; }
        .notes-box {
            border: 1px solid #000;
            min-height: 22mm;
            padding: 8px 10px;
            font-size: 12pt;
            line-height: 1.7;
            margin-top: 12px;
        }
        .notes-title {
            font-weight: 800;
            margin-bottom: 4px;
        }
        .footer-signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 18mm;
            font-size: 12pt;
            font-weight: 700;
        }
        .sig-line {
            margin-top: 14mm;
            border-top: 1px dotted #444;
            padding-top: 4px;
            text-align: center;
        }
        .header-left .header-meta { margin-top: 6px; text-align: center; }
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
            <div class="dept">قسم التوصيف الفني</div>
        </div>
        <div class="header-left">
            @include('prints.partials.org-logo', ['logoSize' => '30mm', 'seal' => true])
            <div class="header-meta">
                <div class="serial-red">{{ $spec->order_ref }}</div>
                <div>رقم الحالة: <span class="fill" style="min-width:16mm;">{{ $case?->case_no ?? '—' }}</span></div>
                <div>تاريخ الإرسال: <span class="fill" style="min-width:22mm;">{{ $dateDisplay }}</span>م</div>
            </div>
        </div>
    </header>

    <h1 class="doc-title">تقرير التوصيف الفني</h1>

    <section class="meta-grid">
        <div class="line">اسم المريض: <span class="fill fill-wide">{{ $spec->patient_name ?? '—' }}</span></div>
        <div class="line">الجهة / التعاقد: <span class="fill fill-wide">{{ $spec->company_name ?? $case?->displayEntity() ?? '—' }}</span></div>
        <div class="line">الطبيب المعالج: <span class="fill fill-wide">{{ $spec->doctor_name ?? '—' }}</span></div>
        <div class="line">مرجع الطلب: <span class="fill" style="min-width:36mm;">{{ $spec->order_ref }}</span></div>
    </section>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width:8%;">#</th>
                <th style="width:18%;">كود الصنف</th>
                <th class="col-name">اسم الصنف / المواصفات</th>
                <th style="width:12%;">الكمية</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($spec->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="col-code">{{ $item->stock_item_code }}</td>
                    <td class="col-name">{{ $item->name }}</td>
                    <td>{{ $item->qty }}</td>
                </tr>
            @endforeach
            @for ($i = 0; $i < $emptyRows; $i++)
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="notes-box">
        <div class="notes-title">ملاحظات فنية:</div>
        <div>{{ $spec->tech_notes ?: '—' }}</div>
    </div>

    <div class="footer-signatures">
        <div>
            <div>فني التوصيف</div>
            <div class="sig-line">التوقيع</div>
        </div>
        <div>
            <div>تاريخ الطباعة: {{ now()->format('d/m/Y') }}</div>
            <div class="sig-line">ختم القسم</div>
        </div>
    </div>
</div>

</body>
</html>
