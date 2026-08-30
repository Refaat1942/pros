@php
    /** @deprecated استخدم App\Support\DocumentTemplateSheet::sheetClass() في القالب الأب */
    $tpl = $documentTemplate ?? [];
    $sheetClass = \App\Support\DocumentTemplateSheet::sheetClass($tpl, $sheetClass ?? 'sheet');
@endphp
