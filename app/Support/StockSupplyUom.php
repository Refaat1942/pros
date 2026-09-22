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
    public static function configuredReceiveBasis(StockItem $item): string
    {
        $raw = trim((string) ($item->receive_quantity_basis ?? ''));

        return in_array($raw, [StockItem::RECEIVE_BASIS_SUPPLY, StockItem::RECEIVE_BASIS_BASE], true)
            ? $raw
            : StockItem::RECEIVE_BASIS_AUTO;
    }

    public static function effectiveReceiveBasis(StockItem $item, string $requestBasis = 'auto'): string
    {
        $configured = self::configuredReceiveBasis($item);
        if ($configured !== StockItem::RECEIVE_BASIS_AUTO) {
            return $configured;
        }

        if ($requestBasis === StockItem::RECEIVE_BASIS_SUPPLY || $requestBasis === StockItem::RECEIVE_BASIS_BASE) {
            return $requestBasis;
        }

        return self::receivesInSupplyUom($item) ? StockItem::RECEIVE_BASIS_SUPPLY : StockItem::RECEIVE_BASIS_BASE;
    }

    public static function resolveReceiveQuantities(
        StockItem $item,
        float $qty,
        float $unitPrice,
        string $quantityBasis,
    ): array {
        $basis = self::effectiveReceiveBasis($item, $quantityBasis);

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

    /** ملخص محاسبي للعرض في كارت الصنف. */
    public static function accountingSummary(StockItem $item): string
    {
        $base = $item->uom ?? 'قطعة';

        if (! self::receivesInSupplyUom($item)) {
            return "الرصيد والـ WAC والصرف بوحدة المخزن ({$base}). قيمة الفاتورة = الكمية × سعر الوحدة.";
        }

        $supply = self::supplyUomLabel($item);

        return "الفاتورة تُسجَّل بـ {$supply}؛ الرصيد والـ WAC والصرف بـ {$base}. "
            .self::conversionHint($item)
            .' — مديونية المورد = كمية التوريد × سعر التوريد.';
    }
}
