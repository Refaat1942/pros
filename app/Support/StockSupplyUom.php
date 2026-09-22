<?php

namespace App\Support;

use App\Models\StockItem;

/**
 * تحويل كميات/أسعار التوريد إلى وحدة المخزن (الصرف والارتجاع والـ WAC).
 */
final class StockSupplyUom
{
    public static function unitsPerSupplyUnit(StockItem $item): float
    {
        $factor = (float) ($item->units_per_supply_unit ?? 1);

        return $factor > 0 ? $factor : 1.0;
    }

    public static function supplyUomLabel(StockItem $item): ?string
    {
        $label = trim((string) ($item->supply_uom ?? ''));

        return $label !== '' ? $label : null;
    }

    /** هل يُفضَّل إدخال الاستلام بوحدة التوريد؟ */
    public static function receivesInSupplyUom(StockItem $item): bool
    {
        $supply = self::supplyUomLabel($item);
        if ($supply === null) {
            return false;
        }

        $factor = self::unitsPerSupplyUnit($item);
        $base = trim((string) ($item->uom ?? ''));

        return $factor !== 1.0 || ($base !== '' && $base !== $supply);
    }

    public static function supplyQtyToBase(float $supplyQty, StockItem $item): float
    {
        return round($supplyQty * self::unitsPerSupplyUnit($item), 4);
    }

    public static function supplyUnitPriceToBase(float $supplyUnitPrice, StockItem $item): float
    {
        $factor = self::unitsPerSupplyUnit($item);

        return round($supplyUnitPrice / $factor, 4);
    }

    public static function baseQtyToSupply(float $baseQty, StockItem $item): float
    {
        $factor = self::unitsPerSupplyUnit($item);

        return round($baseQty / $factor, 4);
    }

    /**
     * @return array{base_qty: float, base_unit_price: float, supply_qty: float|null, supply_unit_price: float|null}
     */
    public static function resolveReceiveQuantities(
        StockItem $item,
        float $qty,
        float $unitPrice,
        string $quantityBasis,
    ): array {
        $basis = $quantityBasis === 'auto'
            ? (self::receivesInSupplyUom($item) ? 'supply' : 'base')
            : $quantityBasis;

        if ($basis === 'supply') {
            return [
                'base_qty' => self::supplyQtyToBase($qty, $item),
                'base_unit_price' => self::supplyUnitPriceToBase($unitPrice, $item),
                'supply_qty' => round($qty, 4),
                'supply_unit_price' => round($unitPrice, 4),
            ];
        }

        return [
            'base_qty' => round($qty, 4),
            'base_unit_price' => round($unitPrice, 4),
            'supply_qty' => self::receivesInSupplyUom($item) ? self::baseQtyToSupply($qty, $item) : null,
            'supply_unit_price' => self::receivesInSupplyUom($item)
                ? round($unitPrice * self::unitsPerSupplyUnit($item), 4)
                : null,
        ];
    }

    public static function conversionHint(StockItem $item): string
    {
        if (! self::receivesInSupplyUom($item)) {
            return 'الاستلام والصرف بوحدة المخزن: '.($item->uom ?? 'قطعة');
        }

        $supply = self::supplyUomLabel($item);
        $factor = self::unitsPerSupplyUnit($item);
        $base = $item->uom ?? 'وحدة';

        return "1 {$supply} = ".rtrim(rtrim(number_format($factor, 4, '.', ''), '0'), '.')." {$base}";
    }
}
