<?php

namespace App\Support;

/**
 * تفقيط المبالغ بالجنيه المصري — لعروض الأسعار الرسمية.
 */
class ArabicAmount
{
    /** @return array{pounds: int, piasters: int} */
    public static function split(float $amount): array
    {
        $rounded   = round($amount, 2);
        $pounds    = (int) floor($rounded);
        $piasters  = (int) round(($rounded - $pounds) * 100);

        if ($piasters === 100) {
            $pounds++;
            $piasters = 0;
        }

        return ['pounds' => $pounds, 'piasters' => $piasters];
    }

    /**
     * @return array{pounds: string, piasters: string}
     */
    public static function splitFormatted(float $amount): array
    {
        $parts = self::split($amount);

        return [
            'pounds'   => number_format($parts['pounds']),
            'piasters' => str_pad((string) $parts['piasters'], 2, '0', STR_PAD_LEFT),
        ];
    }

    public static function tafqeet(float $amount): string
    {
        $parts = self::split($amount);

        if ($parts['pounds'] === 0 && $parts['piasters'] === 0) {
            return 'فقط صفر جنيه مصري لا غير';
        }

        $text = 'فقط ';

        if ($parts['pounds'] > 0) {
            $text .= self::integerToWords($parts['pounds']) . ' ' . self::poundLabel($parts['pounds']);
        }

        if ($parts['piasters'] > 0) {
            if ($parts['pounds'] > 0) {
                $text .= ' و';
            }
            $text .= self::integerToWords($parts['piasters']) . ' ' . self::piasterLabel($parts['piasters']);
        }

        return $text . ' لا غير';
    }

    private static function poundLabel(int $n): string
    {
        if ($n === 1) {
            return 'جنيه مصري';
        }

        if ($n === 2) {
            return 'جنيهان مصريان';
        }

        if ($n >= 3 && $n <= 10) {
            return 'جنيهات مصرية';
        }

        return 'جنيه مصري';
    }

    private static function piasterLabel(int $n): string
    {
        if ($n === 1) {
            return 'قرش';
        }

        if ($n === 2) {
            return 'قرشان';
        }

        if ($n >= 3 && $n <= 10) {
            return 'قروش';
        }

        return 'قرش';
    }

    private static function integerToWords(int $number): string
    {
        if ($number === 0) {
            return 'صفر';
        }

        $parts = [];

        if ($number >= 1_000_000) {
            $millions = intdiv($number, 1_000_000);
            $parts[]  = self::groupToWords($millions) . ' ' . self::millionLabel($millions);
            $number  %= 1_000_000;
        }

        if ($number >= 1_000) {
            $thousands = intdiv($number, 1_000);
            $parts[]   = self::groupToWords($thousands) . ' ' . self::thousandLabel($thousands);
            $number   %= 1_000;
        }

        if ($number > 0) {
            $parts[] = self::groupToWords($number);
        }

        return implode(' و', array_filter($parts));
    }

    private static function groupToWords(int $n): string
    {
        if ($n === 0) {
            return '';
        }

        if ($n < 20) {
            return self::ONES[$n];
        }

        if ($n < 100) {
            $ones = $n % 10;
            $tens = intdiv($n, 10);

            return $ones === 0
                ? self::TENS[$tens]
                : self::ONES[$ones] . ' و' . self::TENS[$tens];
        }

        $hundreds = intdiv($n, 100);
        $rest     = $n % 100;
        $head     = self::HUNDREDS[$hundreds];

        return $rest === 0 ? $head : $head . ' و' . self::groupToWords($rest);
    }

    private static function thousandLabel(int $n): string
    {
        if ($n === 1) {
            return 'ألف';
        }

        if ($n === 2) {
            return 'ألفان';
        }

        if ($n >= 3 && $n <= 10) {
            return 'آلاف';
        }

        return 'ألف';
    }

    private static function millionLabel(int $n): string
    {
        if ($n === 1) {
            return 'مليون';
        }

        if ($n === 2) {
            return 'مليونان';
        }

        if ($n >= 3 && $n <= 10) {
            return 'ملايين';
        }

        return 'مليون';
    }

    private const ONES = [
        0  => 'صفر',
        1  => 'واحد',
        2  => 'اثنان',
        3  => 'ثلاثة',
        4  => 'أربعة',
        5  => 'خمسة',
        6  => 'ستة',
        7  => 'سبعة',
        8  => 'ثمانية',
        9  => 'تسعة',
        10 => 'عشرة',
        11 => 'أحد عشر',
        12 => 'اثنا عشر',
        13 => 'ثلاثة عشر',
        14 => 'أربعة عشر',
        15 => 'خمسة عشر',
        16 => 'ستة عشر',
        17 => 'سبعة عشر',
        18 => 'ثمانية عشر',
        19 => 'تسعة عشر',
    ];

    private const TENS = [
        0  => '',
        1  => 'عشرة',
        2  => 'عشرون',
        3  => 'ثلاثون',
        4  => 'أربعون',
        5  => 'خمسون',
        6  => 'ستون',
        7  => 'سبعون',
        8  => 'ثمانون',
        9  => 'تسعون',
    ];

    private const HUNDREDS = [
        1 => 'مائة',
        2 => 'مائتان',
        3 => 'ثلاثمائة',
        4 => 'أربعمائة',
        5 => 'خمسمائة',
        6 => 'ستمائة',
        7 => 'سبعمائة',
        8 => 'ثمانمائة',
        9 => 'تسعمائة',
    ];
}
