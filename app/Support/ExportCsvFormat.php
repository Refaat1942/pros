<?php

namespace App\Support;

/**
 * تنسيق خلايا CSV للتصدير — بدون لاحقة العملة في ملفات Excel.
 */
final class ExportCsvFormat
{
    public static function cell(mixed $value): string
    {
        $text = trim((string) $value);
        $text = preg_replace('/\s*\(\s*ج\.?\s*م\.?\s*\)/u', '', $text) ?? $text;
        $text = preg_replace('/\s*ألف\s+ج\.?\s*م\.?\s*/u', ' ألف', $text) ?? $text;
        $text = preg_replace('/\s+ج\.?\s*م\.?\s*$/u', '', $text) ?? $text;

        return trim($text);
    }

    /** @param  list<string|int|float|null>  $row  @return list<string> */
    public static function row(array $row): array
    {
        return array_map(fn ($cell) => self::cell($cell), $row);
    }
}
