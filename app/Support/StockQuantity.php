<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * تحويل وتحليل كميات الأصناف (100 جرام → 0.1 كيلو، سعر = qty × سعر الوحدة).
 */
final class StockQuantity
{
    public static function isFractionalUom(?string $uom): bool
    {
        $uom = self::normalizeUomKey($uom);
        if ($uom === '') {
            return false;
        }

        foreach (config('uom.count_units', []) as $count) {
            if (self::normalizeUomKey($count) === $uom) {
                return false;
            }
        }

        return self::familyForUom($uom) !== null;
    }

    /**
     * @return array{value: float, uom: string}
     */
    public static function parse(string $input, ?string $defaultUom = null): array
    {
        $raw = trim($input);
        if ($raw === '') {
            throw new InvalidArgumentException('الكمية مطلوبة.');
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*\/\s*(\d+(?:[.,]\d+)?)\s*(.*)$/u', $raw, $frac)) {
            $den = (float) str_replace(',', '.', $frac[2]);
            if ($den <= 0) {
                throw new InvalidArgumentException('كسور الكمية غير صالحة.');
            }
            $num = (float) str_replace(',', '.', $frac[1]);
            $uom = trim($frac[3]) !== '' ? trim($frac[3]) : ($defaultUom ?? 'قطعة');

            return ['value' => round($num / $den, 4), 'uom' => $uom];
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(.+)$/u', $raw, $m)) {
            $value = (float) str_replace(',', '.', $m[1]);
            $uom = trim($m[2]);

            return ['value' => round($value, 4), 'uom' => $uom];
        }

        if (preg_match('/^\d+(?:[.,]\d+)?$/', $raw)) {
            $value = (float) str_replace(',', '.', $raw);
            $uom = $defaultUom ?? 'قطعة';

            return ['value' => round($value, 4), 'uom' => $uom];
        }

        throw new InvalidArgumentException('صيغة الكمية غير مفهومة — استخدم رقم أو «100 جرام» أو «0.1 كيلو».');
    }

    public static function toUom(float $value, string $fromUom, string $toUom): float
    {
        $from = self::normalizeUomKey($fromUom);
        $to = self::normalizeUomKey($toUom);

        if ($from === $to) {
            return round($value, 4);
        }

        $fromFactor = self::factorInFamily($from);
        $toFactor = self::factorInFamily($to);

        if ($fromFactor === null || $toFactor === null) {
            throw new InvalidArgumentException("لا يمكن التحويل بين «{$fromUom}» و«{$toUom}».");
        }

        $baseValue = $value * $fromFactor;

        return round($baseValue / $toFactor, 4);
    }

    public static function lineCost(float $qtyInItemUom, float $pricePerItemUom): float
    {
        return round($qtyInItemUom * $pricePerItemUom, 2);
    }

    public static function format(float $value, ?string $uom): string
    {
        $uom = $uom ?? '';
        $formatted = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

        return $formatted.($uom !== '' ? ' '.$uom : '');
    }

    /**
     * @param  string|float|int|null  $qty
     */
    public static function toItemUom($qty, ?string $qtyUom, ?string $itemUom): float
    {
        $itemUom = $itemUom ?? 'قطعة';

        if ($qty === null || $qty === '') {
            return self::isFractionalUom($itemUom) ? 0.0 : 1.0;
        }

        if (is_numeric($qty)) {
            $value = (float) $qty;
            $parsedUom = $qtyUom ?? $itemUom;
        } else {
            $parsed = self::parse((string) $qty, $qtyUom ?? $itemUom);
            $value = $parsed['value'];
            $parsedUom = $parsed['uom'];
        }

        return self::toUom($value, $parsedUom, $itemUom);
    }

    private static function normalizeUomKey(?string $uom): string
    {
        return mb_strtolower(trim((string) $uom));
    }

    private static function familyForUom(string $uom): ?string
    {
        $key = self::normalizeUomKey($uom);
        foreach (config('uom.families', []) as $family => $meta) {
            $units = $meta['units'] ?? [];
            foreach ($units as $unit => $_) {
                if (self::normalizeUomKey($unit) === $key) {
                    return $family;
                }
            }
        }

        return null;
    }

    private static function factorInFamily(string $uom): ?float
    {
        $key = self::normalizeUomKey($uom);
        foreach (config('uom.families', []) as $meta) {
            foreach ($meta['units'] ?? [] as $unit => $factor) {
                if (self::normalizeUomKey($unit) === $key) {
                    return (float) $factor;
                }
            }
        }

        return null;
    }
}
