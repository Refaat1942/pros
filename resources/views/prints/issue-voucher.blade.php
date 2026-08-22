@php
    use App\Support\StockItemUomLookup;

    $voucherNo   = $voucher['voucher_no'] ?? '—';
    $workOrderNo = $voucher['work_order_no'] ?? '—';
    $caseNo      = $voucher['case_no'] ?? '—';
    $patientName = $voucher['patient_name'] ?? '—';
    $companyName = $voucher['company_name'] ?? '—';
    $specGroups  = $voucher['spec_groups'] ?? [];
    $items       = collect($voucher['items'] ?? []);
    $uomMap      = StockItemUomLookup::forCodes($items->pluck('stock_item_code')->filter()->all());
    $technician  = $voucher['technician_name'] ?? null;
    $sectionName = $voucher['workshop_section_name'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إذن صرف — {{ $voucherNo }}</title>
    @include('prints.partials.a4-base')
    <style>
        .spec-section { margin: 12px 0 16px; }
        .spec-title {
            text-align: center;
            font-weight: 800;
            font-size: 13pt;
            margin-bottom: 6px;
        }
        .spec-group-label {
            font-weight: 800;
            font-size: 12pt;
            margin: 8px 0 4px;
            padding: 4px 8px;
            background: #f5f5f5;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .spec-layout {
            display: flex;
            gap: 8px;
            border: 1.5px solid #000;
            min-height: 36mm;
            margin-bottom: 8px;
        }
        .spec-sketch {
            width: 32mm;
            border-left: 1.5px solid #000;
            flex-shrink: 0;
        }
        .spec-lines { flex: 1; padding: 4px 6px; }
        .spec-line {
            border-bottom: 1px dotted #888;
            min-height: 5mm;
            font-size: 11pt;
            padding: 1px 2px;
        }
        .notes-box {
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 11pt;
            margin-top: 6px;
        }
        .voucher-signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 24px;
            margin-top: 28px;
            font-size: 11pt;
            font-weight: 700;
        }
        .voucher-signatures .sig-block { text-align: center; }
        .voucher-signatures .sig-line {
            margin-top: 22mm;
            border-top: 1.5px solid #000;
            padding-top: 4px;
        }
        .voucher-signatures .sig-meta {
            font-size: 10pt;
            font-weight: 600;
            margin-top: 4px;
            color: #333;
        }
    </style>
</head>
<body @if($autoPrint ?? true) onload="window.print()" @endif>

<div class="no-print">
    <button type="button" onclick="window.print()">🖨️ طباعة</button>
</div>

<div class="sheet avoid-break issue-voucher-sheet">
    @include('prints.partials.org-header', ['dept' => 'قسم المخازن'])

    <h1 class="doc-title issue-voucher-title">إذن صرف مواد — رقم ( <span class="fill">{{ $voucherNo }}</span> )</h1>

    <table class="meta-table print-table" style="margin-bottom: 14px;">
        <tbody>
            <tr>
                <th style="width:22%;">اسم المريض</th>
                <td>{{ $patientName }}</td>
                <th style="width:22%;">الجهة / التصديق</th>
                <td>{{ $companyName }}</td>
            </tr>
            <tr>
                <th>أمر الشغل</th>
                <td class="mono">{{ $workOrderNo }}</td>
                <th>رقم الحالة</th>
                <td>{{ $caseNo }}</td>
            </tr>
            <tr>
                <th>التاريخ</th>
                <td colspan="3">{{ now()->format('d/m/Y') }}</td>
            </tr>
        </tbody>
    </table>

    <section class="spec-section avoid-break">
        <div class="spec-title">الطرف الصناعي — التوصيف (غير منفصل عن إذن الصرف)</div>
        @forelse ($specGroups as $group)
            @if (count($specGroups) > 1)
                <div class="spec-group-label">{{ $group['label'] }}</div>
            @endif
            <div class="spec-layout">
                <div class="spec-sketch" aria-hidden="true">&nbsp;</div>
                <div class="spec-lines">
                    @foreach ($group['lines'] as $line)
                        <div class="spec-line">
                            {{ $line['name'] }}@if(($line['qty'] ?? 0) > 1) — ×{{ $line['qty'] }}@endif
                            @if(! empty($line['stock_item_code'])) ({{ $line['stock_item_code'] }})@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="spec-layout">
                <div class="spec-sketch" aria-hidden="true">&nbsp;</div>
                <div class="spec-lines">
                    @for ($i = 0; $i < 6; $i++)
                        <div class="spec-line">&nbsp;</div>
                    @endfor
                </div>
            </div>
        @endforelse

        @if (! empty($voucher['written_items']))
            <div class="notes-box">
                <strong>بنود مكتوبة:</strong>
                <div style="white-space:pre-line;margin-top:4px;">{{ $voucher['written_items'] }}</div>
            </div>
        @endif
        @if (! empty($voucher['tech_notes']))
            <div class="notes-box">
                <strong>ملاحظات فنية:</strong>
                <div style="margin-top:4px;">{{ $voucher['tech_notes'] }}</div>
            </div>
        @endif
    </section>

    <p class="line" style="font-weight:800;margin-bottom:8px;">مواد الصرف للورشة (مطابقة التوصيف أعلاه):</p>

    <table class="print-table items-table">
        <thead>
            <tr>
                <th style="width:8%;">#</th>
                <th style="width:16%;">الكود</th>
                <th>اسم الصنف</th>
                <th style="width:12%;">الكمية</th>
                <th style="width:12%;">الوحدة</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $index => $item)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    <td class="mono">{{ $item['stock_item_code'] ?? '—' }}</td>
                    <td style="text-align:right;">{{ $item['name'] ?? '—' }}</td>
                    <td class="num">{{ (int) ($item['qty'] ?? 0) }}</td>
                    <td>{{ $uomMap[$item['stock_item_code'] ?? ''] ?? 'قطعة' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty-row">&nbsp;</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <footer class="voucher-signatures avoid-break">
        <div class="sig-block">
            <div>الفني المختص</div>
            @if ($technician || $sectionName)
                <div class="sig-meta">
                    @if ($technician){{ $technician }}@endif
                    @if ($technician && $sectionName) — @endif
                    @if ($sectionName)قسم {{ $sectionName }}@endif
                </div>
            @endif
            <div class="sig-line">التوقيع</div>
        </div>
        <div class="sig-block">
            <div>مدير الإنتاج</div>
            <div class="sig-line">التوقيع</div>
        </div>
        <div class="sig-block">
            <div>قائد المصنع</div>
            <div class="sig-line">التوقيع</div>
        </div>
        <div class="sig-block">
            <div>رئيس المخازن</div>
            <div class="sig-line">التوقيع — يعتمد</div>
        </div>
    </footer>
</div>

</body>
</html>
