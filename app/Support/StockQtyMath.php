<?php

namespace App\Support;

/**
 * مقارنة كميات مخزنية عشرية (متر/سم، كيلو/جرام).
 */
final class StockQtyMath
{
    public const EPSILON = 0.0001;

    public static function isPositive(float $qty): bool
    {
        return $qty > self::EPSILON;
    }

    public static function lte(float $a, float $b): bool
    {
        return $a <= $b + self::EPSILON;
    }

    public static function gte(float $a, float $b): bool
    {
        return $a + self::EPSILON >= $b;
    }

    public static function eq(float $a, float $b): bool
    {
        return abs($a - $b) <= self::EPSILON;
    }
}
