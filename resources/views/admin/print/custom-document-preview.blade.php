<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>معاينة — {{ $documentTemplate['doc_title'] ?? ($customDocument->title ?? 'وثيقة') }}</title>
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
    <h1 class="doc-title">{{ $documentTemplate['doc_title'] ?? $customDocument->title ?? 'وثيقة' }}</h1>
    @if (!empty($documentTemplate['subtitle']))
        <p style="text-align:center;font-size:11pt;margin-bottom:12px;">{{ $documentTemplate['subtitle'] }}</p>
    @endif

    @if ($customDocument && $customDocument->referenceUrl())
        <div style="margin:12px 0;padding:10px;border:1px dashed #94a3b8;border-radius:8px;background:#f8fafc;">
            <p style="font-size:10pt;font-weight:700;margin-bottom:8px;">النموذج المرجعي المرفوع:</p>
            @if ($customDocument->referenceIsImage())
                <img src="{{ $customDocument->referenceUrl() }}" alt="نموذج مرجعي" style="max-width:100%;max-height:180mm;border:1px solid #cbd5e1;">
            @else
                <a href="{{ $customDocument->referenceUrl() }}" target="_blank" rel="noopener" style="font-weight:700;">فتح ملف PDF المرجعي</a>
            @endif
        </div>
    @endif

    @if (!empty($documentTemplate['body_html']))
        <div class="custom-doc-body" style="margin:12px 0;line-height:1.7;font-size:11pt;">
            {!! $documentTemplate['body_html'] !!}
        </div>
    @else
        <p style="margin:12px 0;font-size:11pt;color:#64748b;">محتوى تجريبي — عدّل نص الوثيقة من شاشة التخصيص.</p>
    @endif

    @if (!empty($documentTemplate['footer_note']))
        <p style="margin-top:16px;font-size:10pt;">{{ $documentTemplate['footer_note'] }}</p>
    @endif
    <div style="display:flex;justify-content:space-between;margin-top:24px;font-weight:700;">
        <span>{{ $documentTemplate['signature_1'] ?? 'مسؤول' }}</span>
        <span>{{ $documentTemplate['signature_2'] ?? 'يعتمد' }}</span>
    </div>
</div>
</body>
</html>
