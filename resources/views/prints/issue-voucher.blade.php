@php
    use App\Support\StockItemUomLookup;

    $voucherNo   = $voucher['voucher_no'] ?? '—';
    $patientName = $voucher['patient_name'] ?? '—';
    $companyName = $voucher['company_name'] ?? '—';
    $items       = $voucher['items'] ?? collect();
    $uomMap      = StockItemUomLookup::forCodes($items->pluck('stock_item_code')->filter()->all());
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إذن صرف — {{ $voucherNo }}</title>
    @include('prints.partials.a4-base')
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
                <th style="width:28%;">اسم المريض</th>
                <td>{{ $patientName }}</td>
                <th style="width:28%;">الجهة / التصديق</th>
                <td>{{ $companyName }}</td>
            </tr>
            <tr>
                <th>التاريخ</th>
                <td colspan="3">{{ now()->format('d/m/Y') }}</td>
            </tr>
        </tbody>
    </table>

    <p class="line" style="font-weight:800;margin-bottom:8px;">رجاء التكرم بصرف الأصناف التالية للورشة:</p>

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
                    <td class="mono">{{ $item->stock_item_code ?? '—' }}</td>
                    <td>{{ $item->name ?? '—' }}</td>
                    <td class="num">{{ (int) ($item->qty ?? 0) }}</td>
                    <td>{{ $uomMap[$item->stock_item_code ?? ''] ?? 'قطعة' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty-row">&nbsp;</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <footer class="sign-footer" style="margin-top:36px;text-align:left;font-weight:800;">
        <div>يعتمد ،،</div>
        <div class="sig-line" style="margin-top:28px;border-top:1.5px solid #000;width:55mm;">&nbsp;</div>
        <div class="sig-title" style="margin-top:4px;font-size:12pt;">رئيس المخازن</div>
    </footer>
</div>

</body>
</html>
