<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طلبات التوريد — {{ $generatedAt->format('d/m/Y') }}</title>
    @include('prints.partials.a4-base')
    <style>
        .supply-sheet { max-height: none; }
        .items-table tbody tr { page-break-inside: avoid; }
        .meta-note { font-size: 10pt; color: #444; margin-bottom: 10px; }
    </style>
</head>
<body @if($autoPrint ?? true) onload="window.print()" @endif>
<div class="no-print">
    <button type="button" onclick="window.print()">🖨️ طباعة</button>
</div>

<div class="sheet supply-sheet">
    @include('prints.partials.org-header', ['dept' => 'قسم المخازن'])

    <h1 class="doc-title">طلبات التوريد المفتوحة</h1>
    <p class="meta-note">تاريخ الطباعة: {{ $generatedAt->format('d/m/Y H:i') }} — البنود بانتظار التوريد أو جاهزة للاستلام</p>

    <table class="print-table items-table">
        <thead>
            <tr>
                <th style="width:12%;">رقم الطلب</th>
                <th>الصنف</th>
                <th style="width:8%;">الكمية</th>
                <th style="width:10%;">الوحدة</th>
                <th style="width:12%;">تاريخ الطلب</th>
                <th style="width:12%;">تاريخ الاستلام</th>
                <th style="width:14%;">الحالة</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td class="mono">{{ $line['request_no'] ?? '—' }}</td>
                    <td>{{ $line['display_name'] ?? '—' }}</td>
                    <td class="num">{{ $line['qty'] ?? '—' }}</td>
                    <td>{{ $line['uom'] ?? '—' }}</td>
                    <td>{{ $line['requested_at'] ?? '—' }}</td>
                    <td>{{ $line['received_at'] ?? '—' }}</td>
                    <td>{{ $line['status_label'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-row">لا توجد طلبات توريد مفتوحة.</td></tr>
            @endforelse
        </tbody>
    </table>

    <footer class="sign-footer" style="margin-top:24px;text-align:left;font-weight:700;">
        <div>يعتمد ،،</div>
        <div style="margin-top:20mm;border-top:1.5px solid #000;width:55mm;">&nbsp;</div>
        <div style="margin-top:4px;font-size:11pt;">رئيس المخازن</div>
    </footer>
</div>
</body>
</html>
