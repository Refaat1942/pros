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
    .org-logo-thermal {
        width: var(--org-logo-size, 32mm);
        height: var(--org-logo-size, 32mm);
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .org-logo-thermal__inner {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .org-logo-thermal img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        filter: grayscale(100%) contrast(1.62) brightness(1);
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
        -webkit-print-color-adjust: economy;
        print-color-adjust: economy;
    }
    .org-logo-thermal--seal {
        padding: 1.2mm;
        border: 1.1px solid #1a1a1a;
        border-radius: 50%;
        background: #fff;
        box-shadow: inset 0 0 0 0.4mm #fff;
    }
    .org-logo-thermal--seal img {
        filter: grayscale(100%) contrast(1.72) brightness(0.98);
    }
    @media print {
        .org-logo-thermal img {
            filter: grayscale(100%) contrast(1.75) brightness(0.96);
        }
        .org-logo-thermal--seal img {
            filter: grayscale(100%) contrast(1.82) brightness(0.94);
        }
    }
    .quote-qr-box {
        width: 24mm;
        flex-shrink: 0;
        padding: 1.2mm;
        border: 1px solid #000;
        background: #fff;
        text-align: center;
        line-height: 1;
    }
    .quote-qr-box__code {
        width: 20mm;
        height: 20mm;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .quote-qr-box__code svg {
        width: 100%;
        height: 100%;
        display: block;
    }
    .quote-qr-box__label {
        margin-top: 1mm;
        font-size: 6.5pt;
        font-weight: 800;
        letter-spacing: 0.02em;
        color: #111;
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
