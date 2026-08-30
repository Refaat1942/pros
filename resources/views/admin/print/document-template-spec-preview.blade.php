<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>معاينة — {{ $documentTemplate['doc_title'] ?? 'تقرير التوصيف' }}</title>
    @include('prints.partials.a4-base')
</head>
<body>
@include('prints.partials.document-template-vars')
<div class="{{ $sheetClass }}">
    @include('prints.partials.org-header', [
        'dept' => $documentTemplate['dept_label'] ?? null,
        'seal' => (bool) ($documentTemplate['show_seal'] ?? true),
        'showLogo' => (bool) ($documentTemplate['show_logo'] ?? true),
    ])
    <h1 class="doc-title">{{ $documentTemplate['doc_title'] ?? 'تقرير التوصيف الفني' }}</h1>
    <p style="margin:12px 0;font-size:11pt;line-height:1.7;">
        هذا معاينة لتخصيص تقرير التوصيف. عند الطباعة الفعلية من شاشة التوصيف تُستخدم البيانات الحقيقية للحالة.
    </p>
    <div style="border:1px solid #000;padding:12px;margin:12px 0;">
        <strong>بنود توصيف تجريبية:</strong>
        <ul style="margin:8px 0 0;padding-right:18px;">
            <li>طرف صناعي ركبة — مواصفات تجريبية</li>
            <li>مادة تثبيت ×2</li>
        </ul>
    </div>
    @if (!empty($documentTemplate['footer_note']))
        <p style="font-size:10pt;">{{ $documentTemplate['footer_note'] }}</p>
    @endif
    <div style="display:flex;justify-content:space-between;margin-top:24px;font-weight:700;">
        <span>{{ $documentTemplate['signature_1'] ?? 'أخصائي التوصيف' }}</span>
        <span>{{ $documentTemplate['signature_2'] ?? 'رئيس القسم' }}</span>
    </div>
</div>
</body>
</html>
