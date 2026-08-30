@php
    use App\Support\StockItemUomLookup;
    use App\Services\DocumentTemplateService;

    $voucherNo   = $voucher['voucher_no'] ?? '—';
    $workOrderNo = $voucher['work_order_no'] ?? '—';
    $caseNo      = $voucher['case_no'] ?? '—';
    $patientName = $voucher['patient_name'] ?? '—';
    $companyName = $voucher['company_name'] ?? '—';
    $specGroups  = $voucher['spec_groups'] ?? [];
    $items       = collect($voucher['items'] ?? []);
@php
    $uomMap = [];
    try {
        $uomMap = StockItemUomLookup::forCodes($items->pluck('stock_item_code')->filter()->all());
    } catch (\Throwable $e) {
        report($e);
    }
    $technician  = $voucher['technician_name'] ?? null;
    $sectionName = $voucher['workshop_section_name'] ?? null;

    $tplService = app(DocumentTemplateService::class);
    $printCtx = \App\Support\DocumentPrintContext::fromRequest(request());
    $tpl = $documentTemplate ?? $tplService->for('issue_voucher', $printCtx->department, $printCtx->stage);
    $docTitle = $tplService->renderText($tpl['doc_title'] ?? 'إذن صرف مواد — رقم ( {no} )', ['no' => $voucherNo]);
@endphp
@include('prints.partials.document-template-vars')
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إذن صرف — {{ $voucherNo }}</title>
    @include('prints.partials.a4-base')
    <style>
        .compact-voucher .doc-title { font-size: 14pt; margin-bottom: 8px; }
        .compact-voucher .meta-table { font-size: 10pt; }
        .spec-section { margin: 8px 0 10px; }
        .spec-title {
            text-align: center;
            font-weight: 800;
            font-size: 12pt;
            margin-bottom: 4px;
        }
        .spec-group-label {
            font-weight: 800;
            font-size: 11pt;
            margin: 6px 0 4px;
            padding: 3px 6px;
            background: #f5f5f5;
            border: 1px solid #ccc;
        }
        .spec-layout {
            display: flex;
            gap: 6px;
            border: 1.5px solid #000;
            min-height: 22mm;
            margin-bottom: 6px;
        }
        .spec-sketch {
            width: 28mm;
            border-left: 1.5px solid #000;
            flex-shrink: 0;
        }
        .spec-lines { flex: 1; padding: 3px 5px; }
        .spec-line {
            border-bottom: 1px dotted #888;
            min-height: 4mm;
            font-size: 10pt;
            padding: 1px 2px;
        }
        .notes-box {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 10pt;
            margin-top: 4px;
        }
        .compact-voucher .items-table { font-size: 10pt; }
        .voucher-signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 16px;
            margin-top: 16px;
            font-size: 10pt;
            font-weight: 700;
        }
        .voucher-signatures .sig-block { text-align: center; }
        .voucher-signatures .sig-line {
            margin-top: 14mm;
            border-top: 1.5px solid #000;
            padding-top: 4px;
        }
        .voucher-signatures .sig-meta {
            font-size: 9pt;
            font-weight: 600;
            margin-top: 4px;
            color: #333;
        }
        @media print {
            .compact-voucher { page-break-inside: avoid; max-height: 277mm; overflow: hidden; }
            .compact-voucher .items-table tbody tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body @if($autoPrint ?? true) onload="window.print()" @endif>

<div class="no-print">
    <button type="button" onclick="window.print()">🖨️ طباعة</button>
</div>

<div class="{{ $sheetClass }} issue-voucher-sheet compact-voucher">
    @include('prints.partials.org-header', [
        'dept' => $tpl['dept_label'] ?? 'قسم المخازن',
        'seal' => (bool) ($tpl['show_seal'] ?? true),
        'showLogo' => (bool) ($tpl['show_logo'] ?? true),
    ])

    <h1 class="doc-title issue-voucher-title">{{ $docTitle }}</h1>

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

    @if (!empty($tpl['show_spec_section']))
    <section class="spec-section avoid-break">
        <div class="spec-title">{{ $tpl['spec_section_title'] ?? 'الطرف الصناعي — التوصيف' }}</div>
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
    @endif

    <p class="line" style="font-weight:800;margin-bottom:8px;">{{ $tpl['intro_line'] ?? 'مواد الصرف لقسم الإنتاج:' }}</p>

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
            <div>{{ $tpl['signature_1'] ?? 'الفني المختص' }}</div>
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
            <div>{{ $tpl['signature_2'] ?? 'مدير الإنتاج' }}</div>
            <div class="sig-line">التوقيع</div>
        </div>
        <div class="sig-block">
            <div>{{ $tpl['signature_3'] ?? 'قائد المصنع' }}</div>
            <div class="sig-line">التوقيع</div>
        </div>
        <div class="sig-block">
            <div>{{ $tpl['signature_4'] ?? 'رئيس المخازن' }}</div>
            <div class="sig-line">التوقيع — يعتمد</div>
        </div>
    </footer>
    @if (!empty($tpl['footer_note']))
        <p style="margin-top:10px;font-size:10pt;font-weight:600;">{{ $tpl['footer_note'] }}</p>
    @endif
</div>

</body>
</html>
