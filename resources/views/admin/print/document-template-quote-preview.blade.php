<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>معاينة — {{ $documentTemplate['doc_title'] ?? 'عرض سعر' }}</title>
    @include('prints.partials.a4-base')
</head>
<body>
@php
    $sheetClass = \App\Support\DocumentTemplateSheet::sheetClass($documentTemplate ?? []);
@endphp
<div class="{{ $sheetClass }}">
    @include('prints.partials.org-header', [
        'dept' => $documentTemplate['dept_label'] ?? null,
        'seal' => (bool) ($documentTemplate['show_seal'] ?? true),
        'showLogo' => (bool) ($documentTemplate['show_logo'] ?? true),
    ])
    <h1 class="doc-title">{{ $documentTemplate['doc_title'] ?? 'عرض سعر' }}</h1>
    @if (!empty($documentTemplate['subtitle']))
        <p style="text-align:center;font-size:11pt;margin-bottom:12px;">{{ $documentTemplate['subtitle'] }}</p>
    @endif
    <table class="print-table meta-table">
        <tbody>
            <tr><th>مريض تجريبي</th><td>معاينة قالب عرض السعر</td></tr>
            <tr><th>التاريخ</th><td>{{ now()->format('d/m/Y') }}</td></tr>
            <tr><th>الإجمالي</th><td>15,000 ج.م</td></tr>
        </tbody>
    </table>
    @if (!empty($documentTemplate['footer_note']))
        <p style="margin-top:16px;font-size:10pt;">{{ $documentTemplate['footer_note'] }}</p>
    @endif
    <div style="display:flex;justify-content:space-between;margin-top:24px;font-weight:700;">
        <span>{{ $documentTemplate['signature_1'] ?? 'مسؤول الاستقبال' }}</span>
        <span>{{ $documentTemplate['signature_2'] ?? 'يعتمد' }}</span>
    </div>
</div>
</body>
</html>
