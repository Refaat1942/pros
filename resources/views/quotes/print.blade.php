<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض سعر {{ $quote->quote_no }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Tajawal', sans-serif; color: #0f172a; padding: 32px; background: #fff; }
        .header { text-align: center; margin-bottom: 28px; border-bottom: 2px solid #0e7490; padding-bottom: 16px; }
        .header h1 { font-size: 1.5rem; color: #155e75; margin-bottom: 4px; }
        .header p { color: #64748b; font-size: 0.9rem; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
        .meta-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
        .meta-item label { display: block; font-size: 0.75rem; color: #64748b; margin-bottom: 4px; }
        .meta-item span { font-weight: 700; font-size: 0.95rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #e2e8f0; padding: 10px 12px; text-align: right; font-size: 0.9rem; }
        th { background: #f0f9ff; color: #155e75; font-weight: 700; }
        .total-row td { font-weight: 800; font-size: 1.05rem; background: #f0fdf4; }
        .qr-section { text-align: center; margin-top: 32px; padding-top: 24px; border-top: 1px dashed #cbd5e1; }
        .qr-section img { margin: 12px auto; display: block; }
        .qr-section p { font-size: 0.85rem; color: #64748b; }
        .quote-no { font-family: monospace; font-size: 1.1rem; font-weight: 800; color: #0e7490; letter-spacing: 1px; }
        @media print {
            body { padding: 16px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print" style="margin-bottom:16px;text-align:left;">
    <button onclick="window.print()" style="padding:8px 20px;background:#0e7490;color:#fff;border:none;border-radius:8px;cursor:pointer;font-family:inherit;">🖨️ طباعة</button>
</div>

<div class="header">
    <h1>مركز الأطراف الصناعية</h1>
    <p>عرض سعر رسمي — Official Price Quotation</p>
</div>

<div class="meta">
    <div class="meta-item">
        <label>رقم العرض</label>
        <span class="quote-no">{{ $quote->quote_no }}</span>
    </div>
    <div class="meta-item">
        <label>تاريخ العرض</label>
        <span>{{ $quote->quote_date?->format('Y-m-d') }}</span>
    </div>
    <div class="meta-item">
        <label>المريض</label>
        <span>{{ $quote->patient_name }}</span>
    </div>
    <div class="meta-item">
        <label>جهة التعاقد</label>
        <span>{{ $quote->company_name ?? '—' }}</span>
    </div>
    <div class="meta-item">
        <label>مرجع الطلب</label>
        <span>{{ $quote->order_ref }}</span>
    </div>
    <div class="meta-item">
        <label>رقم الحالة</label>
        <span>{{ $quote->caseRecord?->case_no ?? '—' }}</span>
    </div>
</div>

<table data-paginate="10">
    <thead>
        <tr>
            <th>#</th>
            <th>الصنف</th>
            <th>الكود</th>
            <th>الكمية</th>
            <th>المبلغ (ج.م)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($quote->items as $i => $item)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $item->name }}</td>
            <td>{{ $item->stock_item_code }}</td>
            <td>{{ $item->qty }}</td>
            <td>{{ number_format($item->amount, 2) }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="4">الإجمالي</td>
            <td>{{ number_format($quote->total, 2) }} ج.م</td>
        </tr>
    </tbody>
</table>

<div class="qr-section">
    <p>امسح الرمز أدناه عند عودة خطاب الموافقة</p>
    <img
        src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($quote->quote_no) }}"
        alt="QR {{ $quote->quote_no }}"
        width="200"
        height="200"
    >
    <p class="quote-no">{{ $quote->quote_no }}</p>
</div>

</body>
</html>
