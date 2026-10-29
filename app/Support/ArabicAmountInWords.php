<?php

namespace App\Support;

/**
 * تفقيط المبالغ بالعربي للفواتير: «فقط ثمانية آلاف وأربعمائة وأربعة وعشرون جنيهًا مصريًا لا غير».
 * يدعم حتى مليارات، والقروش (رقمين عشريين).
 */
final class ArabicAmountInWords
{
    private const ONES = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة',
        'عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];

    private const TENS = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];

    private const HUNDREDS = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    /** [مفرد، مثنى، جمع (3-10)] لكل مرتبة. */
    private const SCALES = [
        1_000_000_000 => ['مليار', 'ملياران', 'مليارات'],
        1_000_000 => ['مليون', 'مليونان', 'ملايين'],
        1_000 => ['ألف', 'ألفان', 'آلاف'],
    ];

    public static function egp(float $amount): string
    {
        $amount = round(max(0, $amount), 2);
        $pounds = (int) floor($amount);
        $piasters = (int) round(($amount - $pounds) * 100);

        $text = 'فقط '.($pounds > 0 ? self::number($pounds) : 'صفر').' '.self::poundWord($pounds);
        if ($piasters > 0) {
            $text .= ' و'.self::number($piasters).' '.self::piasterWord($piasters);
        }

        return $text.' لا غير';
    }

    public static function number(int $n): string
    {
        if ($n === 0) {
            return 'صفر';
        }

        $parts = [];
        foreach (self::SCALES as $value => [$one, $two, $plural]) {
            $count = intdiv($n, $value);
            $n %= $value;
            if ($count === 0) {
                continue;
            }
            $parts[] = match (true) {
                $count === 1 => $one,
                $count === 2 => $two,
                $count <= 10 => self::belowThousand($count).' '.$plural,
                default => self::belowThousand($count).' '.$one,
            };
        }

        if ($n > 0) {
            $parts[] = self::belowThousand($n);
        }

        return implode(' و', $parts);
    }

    private static function belowThousand(int $n): string
    {
        $parts = [];
        if ($n >= 100) {
            $parts[] = self::HUNDREDS[intdiv($n, 100)];
            $n %= 100;
        }
        if ($n > 0) {
            if ($n < 20) {
                $parts[] = self::ONES[$n];
            } else {
                $unit = $n % 10;
                $parts[] = $unit > 0
                    ? self::ONES[$unit].' و'.self::TENS[intdiv($n, 10)]
                    : self::TENS[intdiv($n, 10)];
            }
        }

        return implode(' و', $parts);
    }

    private static function poundWord(int $n): string
    {
        $tail = $n % 100;

        return match (true) {
            $n === 2 => 'جنيهان مصريان',
            $tail >= 3 && $tail <= 10 => 'جنيهات مصرية',
            $n === 1 || $n === 0 || $tail <= 2 => 'جنيه مصري',
            default => 'جنيهًا مصريًا',
        };
    }

    private static function piasterWord(int $n): string
    {
        return match (true) {
            $n === 2 => 'قرشان',
            $n >= 3 && $n <= 10 => 'قروش',
            $n === 1 => 'قرش',
            default => 'قرشًا',
        };
    }
}
