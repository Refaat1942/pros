<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لا يوجد باركود — {{ $name }}</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, Tahoma, sans-serif;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            max-width: 520px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px 24px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(15, 23, 42, 0.08);
        }
        h1 { font-size: 18px; margin: 0 0 12px; color: #0f172a; }
        p { font-size: 14px; line-height: 1.7; color: #475569; margin: 0 0 10px; }
        .actions { margin-top: 20px; }
        a {
            display: inline-block;
            padding: 10px 18px;
            background: #059669;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>⚠️ لا يوجد باركود لهذا الصنف</h1>
        <p><strong>{{ $name }}</strong></p>
        <p>ارفع شيت Excel وتأكد من عمود <strong>أكواد</strong> (أو الأكواد)، أو عدّل الصنف وأدخل الكود التشغيلي.</p>
        <p>بعد وجود الكود سيُولَّد باركود تلقائياً مثل <code dir="ltr">BC-1H38</code>.</p>
        <div class="actions">
            <a href="{{ url('/admin/catalog') }}">← رجوع للأصناف</a>
        </div>
    </div>
</body>
</html>
