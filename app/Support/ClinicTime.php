<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * عرض التواريخ بتواقيت المركز (مصر) — التخزين يبقى على توقيت التطبيق.
 */
final class ClinicTime
{
    public static function zone(): string
    {
        return (string) config('app.clinic_timezone', 'Africa/Cairo');
    }

    public static function format(?CarbonInterface $value, string $pattern = 'd/m/Y H:i'): string
    {
        if (! $value) {
            return '—';
        }

        return $value->copy()->timezone(self::zone())->format($pattern);
    }
}
