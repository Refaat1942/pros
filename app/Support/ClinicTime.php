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

    /**
     * يوم العمل التشغيلي — يبدأ الساعة 01:00 صباحاً.
     * (00:00–00:59 تُحسب ضمن اليوم السابق.)
     */
    public static function clinicDayDateString(?CarbonInterface $at = null): string
    {
        $at = ($at ?? self::now())->copy()->timezone(self::zone());

        if ($at->hour < 1) {
            return $at->copy()->subDay()->toDateString();
        }

        return $at->toDateString();
    }

    public static function clinicDayStart(?CarbonInterface $at = null): Carbon
    {
        return Carbon::parse(self::clinicDayDateString($at).' 01:00:00', self::zone());
    }

    public static function clinicDayEnd(?CarbonInterface $at = null): Carbon
    {
        return self::clinicDayStart($at)->copy()->addDay()->subSecond();
    }

    public static function format(?CarbonInterface $value, string $pattern = 'd/m/Y H:i'): string
    {
        if (! $value) {
            return '—';
        }

        return $value->copy()->timezone(self::zone())->format($pattern);
    }

    /**
     * @return array{from: ?Carbon, to: ?Carbon}
     */
    public static function parseDateRange(?string $from, ?string $to): array
    {
        $fromDate = ($from !== null && $from !== '')
            ? Carbon::parse($from, self::zone())->startOfDay()
            : null;

        $toDate = ($to !== null && $to !== '')
            ? Carbon::parse($to, self::zone())->endOfDay()
            : null;

        if ($fromDate && $toDate && $fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [
                $toDate->copy()->startOfDay(),
                $fromDate->copy()->endOfDay(),
            ];
        }

        return ['from' => $fromDate, 'to' => $toDate];
    }
}
