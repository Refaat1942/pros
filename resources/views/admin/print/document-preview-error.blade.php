<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعذّر المعاينة</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; padding: 40px; background: #f8fafc; color: #0f172a; }
        .box { max-width: 640px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 12px; }
        p { line-height: 1.6; margin: 0 0 10px; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
        a { color: #0369a1; font-weight: 700; }
    </style>
</head>
<body>
<div class="box">
    <h1>تعذّر عرض معاينة الوثيقة</h1>
    <p>الوثيقة: <code>{{ $document }}</code></p>
  @if ($errorMessage)
        <p style="color:#b91c1c;font-size:13px;">{{ $errorMessage }}</p>
    @endif
    <p>جرّب على السيرفر:</p>
    <pre style="background:#f1f5f9;padding:12px;border-radius:8px;font-size:12px;">bash deploy.sh
php artisan migrate --force
php artisan view:clear
php artisan config:clear</pre>
    <p><a href="{{ url('/admin/documents-hub') }}">← العودة لمركز الوثائق</a></p>
</div>
</body>
</html>
