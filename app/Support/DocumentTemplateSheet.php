<?php

namespace App\Support;

/** فئة CSS لورقة الطباعة وفق إعدادات القالب. */
final class DocumentTemplateSheet
{
  /** @param  array<string, mixed>|null  $documentTemplate */
    public static function sheetClass(?array $documentTemplate = null, string $base = 'sheet'): string
    {
        $tpl = $documentTemplate ?? [];
        $compact = (bool) ($tpl['compact_layout'] ?? true);

        return trim($base.($compact ? ' doc-tpl-compact' : ''));
    }
}
