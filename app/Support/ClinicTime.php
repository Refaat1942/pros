<?php

namespace App\Support;

use Carbon\Carbon;
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

    /** الآن بتوقيت المركز — للتواريخ التشغيلية (تسجيل، مواعيد اليوم). */
    public static function now(): Carbon
    {
        return Carbon::now(self::zone());
    }

    /** تاريخ اليوم التشغيلي للمركز بصيغة Y-m-d. */
    public static function todayDateString(): string
    {
        return self::now()->toDateString();
    }

    public static function format(?CarbonInterface $value, string $pattern = 'd/m/Y H:i'): string
    {
        if (! $value) {
            return '—';
        }

        return $value->copy()->timezone(self::zone())->format($pattern);
    }
}
