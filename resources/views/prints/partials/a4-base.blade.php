@php
    $autoPrint = $autoPrint ?? true;
    $docTitle = $docTitle ?? '';
@endphp
<style>
    @page { size: A4 portrait; margin: 10mm 12mm 14mm; }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Traditional Arabic', 'Simplified Arabic', 'Arial', 'Tahoma', sans-serif;
        font-size: 14pt;
        line-height: 1.55;
        color: #000;
        background: #fff;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .sheet { width: 100%; max-width: 190mm; margin: 0 auto; }
    .doc-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 8px;
    }
    .header-right {
        flex: 1;
        text-align: right;
        font-weight: 700;
        font-size: 13pt;
        line-height: 1.65;
    }
    .header-right .dept { text-decoration: underline; }
    .header-left {
        min-width: 48mm;
        flex-shrink: 0;
        text-align: center;
    }
    .logo-placeholder {
        width: 30mm;
        height: 30mm;
        border: 1.5px dashed #666;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 8pt;
        color: #888;
        margin: 0 auto 4px;
    }
    .header-meta {
        font-size: 10.5pt;
        font-weight: 600;
        text-align: right;
        line-height: 1.8;
    }
    .serial-red { color: #c00; font-weight: 800; font-size: 12pt; }
    .doc-title {
        text-align: center;
        font-size: 17pt;
        font-weight: 800;
        text-decoration: underline;
        margin: 10px 0 14px;
    }
    .fill {
        display: inline-block;
        border-bottom: 1px dotted #000;
        min-width: 40mm;
        padding: 0 2px 1px;
        vertical-align: bottom;
    }
    .fill-wide { min-width: 70mm; }
    .line { margin: 6px 0; }
    .no-print {
        position: fixed;
        top: 12px;
        left: 12px;
        z-index: 100;
    }
    .no-print button {
        padding: 8px 18px;
        background: #1e3a5f;
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-family: inherit;
        font-size: 13px;
    }
    @media print {
        body { background: #fff; }
        .no-print { display: none !important; }
        .sheet { max-width: none; }
        .avoid-break { page-break-inside: avoid; }
    }
</style>
