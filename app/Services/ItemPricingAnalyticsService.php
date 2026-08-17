<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockItemPrice;

/**
 * Per-item pricing analytics — WAC, purchase batches, and unit margin potential.
 */
class ItemPricingAnalyticsService
{
    public function __construct(private readonly StockPriceService $stockPriceService) {}

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     qty: int,
     *     wac: float,
     *     highest_purchase_price: float,
     *     lowest_purchase_price: float,
     *     price_batch_count: int,
     *     unit_margin: float,
     *     margin_pct: float,
     *     wac_inventory_value: float,
     *     highest_inventory_value: float,
     *     margin_erosion: bool
     * }>
     */
    public function catalogMargins(?int $limit = null): array
    {
        $query = StockItem::query()->orderBy('code');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get(['code', 'name', 'qty', 'wac'])
            ->map(fn (StockItem $item) => $this->rowForItem($item))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function rowForItem(StockItem $item): array
    {
        $wac = round($this->stockPriceService->wacUnitPrice($item->code), 4);
        $highest = round($this->stockPriceService->highestUnitPrice($item->code), 4);
        $lowest = round($this->lowestUnitPrice($item->code), 4);
        $qty = max(0, (int) $item->qty);
        $unitMargin = round($highest - $wac, 4);
        $marginPct = $wac > 0 ? round(($unitMargin / $wac) * 100, 2) : 0.0;

        return [
            'code' => $item->code,
            'name' => $item->name,
            'qty' => $qty,
            'wac' => $wac,
            'highest_purchase_price' => $highest,
            'lowest_purchase_price' => $lowest,
            'price_batch_count' => StockItemPrice::query()
                ->whereHas('stockItem', fn ($q) => $q->where('code', $item->code))
                ->count(),
            'unit_margin' => $unitMargin,
            'margin_pct' => $marginPct,
            'diff' => $unitMargin,
            'wac_inventory_value' => round($qty * $wac, 2),
            'highest_inventory_value' => round($qty * $highest, 2),
            'margin_erosion' => $unitMargin > 0,
        ];
    }

    public function lowestUnitPrice(string $stockItemCode): float
    {
        $min = StockItemPrice::query()
            ->whereHas('stockItem', fn ($q) => $q->where('code', $stockItemCode))
            ->where('amount', '>', 0)
            ->min('amount');

        return $min !== null ? (float) $min : 0.0;
    }
}
