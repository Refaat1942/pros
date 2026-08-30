<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>باركود الشاشة — {{ $name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif;
            background: #0f172a;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            width: 100%;
            max-width: 760px;
            background: #fff;
            color: #0f172a;
            border-radius: 20px;
            padding: 28px 24px 32px;
            text-align: center;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
        }
        h1 {
            font-size: 18px;
            line-height: 1.5;
            margin: 0 0 8px;
            font-weight: 800;
        }
        .subtitle {
            font-size: 13px;
            color: #64748b;
            margin: 0 0 20px;
        }
        .barcode-panel {
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px 20px;
            margin: 0 auto;
        }
        .barcode-panel img {
            display: block;
            width: 100%;
            max-width: 680px;
            height: auto;
            margin: 0 auto;
            image-rendering: pixelated;
            image-rendering: -webkit-optimize-contrast;
            filter: contrast(2);
        }
        .code {
            margin-top: 18px;
            font-size: 32px;
            font-weight: 900;
            letter-spacing: 2px;
            direction: ltr;
            color: #000;
        }
        .hint {
            margin-top: 22px;
            font-size: 14px;
            line-height: 1.7;
            color: #475569;
            background: #f8fafc;
            border-radius: 12px;
            padding: 14px 16px;
        }
        .hint strong { color: #0f766e; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $name }}</h1>
        <p class="subtitle">مسح من الشاشة — بدون طابعة</p>
        <div class="barcode-panel">
            <img src="{{ $svg_data_uri }}" alt="{{ $barcode }}" decoding="sync">
        </div>
        <div class="code">{{ $barcode }}</div>
        <p class="hint">
            <strong>وجّه ماسح الباركود USB</strong> نحو منتصف الشاشة ثم امسح.<br>
            لا تحتاج طابعة — يعمل على شاشة الكمبيوتر أو التابلت.<br>
            للوضوح: Scale = 100%، إضاءة جيدة، وابتعد 15–25 سم من الشاشة.
        </p>
    </div>
</body>
</html>
