@php
    $tpl = $documentTemplate ?? [];
    $compact = (bool) ($tpl['compact_layout'] ?? true);
    $sheetClass = trim(($sheetClass ?? 'sheet') . ($compact ? ' doc-tpl-compact' : ''));
@endphp
