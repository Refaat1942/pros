<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Support\CostingEngine;
use Illuminate\Support\Collection;

/**
 * تقييم المخزون — مصدر واحد لكل اللوحات والتقارير.
 *
 * التكلفة (FIFO): الرصيد المتبقي = آخر ما سيُصرف من طبقات الشراء (نفس ترتيب
 * PriceBatchDispenseService)، وما لا تغطيه طبقة شراء = رصيد أول المدة بالسعر الأساسي
 * للصنف — نفس سعر طبقة «رصيد أول المدة» عند الصرف.
 *
 * سعر البيع: نفس محرك عرض السعر (CostingEngine) — أعلى سعر شراء × (مكوّنات + ربح
 * الطرف الصناعي)، أو ربح الصرف السريع للأصناف المعلَّمة «صرف سريع».
 *
 * الرصيد السالب (طلب توريد) لا يمثّل مخزوناً وله قيمة صفر.
 */
class InventoryValuationService
{
    /** @var array{0: array<string, mixed>|null, 1: array<string, mixed>|null}|null */
    private ?array $profiles = null;

    public function __construct(
        private readonly CostingModeService $modes,
        private readonly CostingEngine $engine,
    ) {}

    /** @return array{selling_price: float, base_selling: float, quick_selling: float} */
    private function selling(float $limbMaterials, float $quickMaterials): array
    {
        $this->profiles ??= [$this->modes->limbProfile(), $this->modes->quickProfile()];

        return $this->engine->calculateSplit($this->profiles[0], $this->profiles[1], $limbMaterials, $quickMaterials);
    }

    /**
     * @return array{
     *   item_count: int,
     *   stocked_items: int,
     *   total_qty: float,
     *   cost_value: float,
     *   layered_cost_value: float,
     *   opening_cost_value: float,
     *   wac_value: float,
     *   highest_value: float,
     *   selling_value: float,
     *   limb_selling_value: float,
     *   quick_selling_value: float,
     *   expected_margin: float,
     * }
     */
    public function summary(): array
    {
        $totals = [
            'item_count' => 0,
            'stocked_items' => 0,
            'total_qty' => 0.0,
            'cost_value' => 0.0,
            'layered_cost_value' => 0.0,
            'opening_cost_value' => 0.0,
            'wac_value' => 0.0,
            'highest_value' => 0.0,
        ];
        $limbMaterials = 0.0;
        $quickMaterials = 0.0;

        $this->eachRow(function (array $row) use (&$totals, &$limbMaterials, &$quickMaterials) {
            $totals['item_count']++;
            if ($row['qty'] <= 0) {
                return;
            }

            $totals['stocked_items']++;
            $totals['total_qty'] += $row['qty'];
            $totals['cost_value'] += $row['cost_value'];
            $totals['layered_cost_value'] += $row['layered_cost_value'];
            $totals['opening_cost_value'] += $row['opening_cost_value'];
            $totals['wac_value'] += $row['wac_value'];
            $totals['highest_value'] += $row['highest_value'];

            if ($row['is_quick_dispense']) {
                $quickMaterials += $row['highest_value'];
            } else {
                $limbMaterials += $row['highest_value'];
            }
        }, withSelling: false);

        $split = $this->selling($limbMaterials, $quickMaterials);

        foreach (['total_qty', 'cost_value', 'layered_cost_value', 'opening_cost_value', 'wac_value', 'highest_value'] as $key) {
            $totals[$key] = round($totals[$key], 2);
        }

        return $totals + [
            'selling_value' => (float) $split['selling_price'],
            'limb_selling_value' => (float) $split['base_selling'],
            'quick_selling_value' => (float) $split['quick_selling'],
            'expected_margin' => round((float) $split['selling_price'] - $totals['cost_value'], 2),
        ];
    }

    /**
     * صف تقييم لكل صنف (للتقارير وأمر الفحص).
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        $rows = [];
        $this->eachRow(function (array $row) use (&$rows) {
            $rows[] = $row;
        });

        return $rows;
    }

    /** @return array<string, mixed> */
    public function valueItem(StockItem $item): array
    {
        $layers = StockItemPrice::query()
            ->where('stock_item_id', $item->id)
            ->where('qty', '>', 0)
            ->get();

        return $this->buildRow($item, $layers, true);
    }

    /**
     * WAC المخزّن = متوسط تكلفة الرصيد الفعلي (طبقات FIFO + رصيد أول المدة).
     * يُصلح WAC القديم بعد إعادة رفع شيت الأصناف أو تعديل السعر/الكمية من الكتالوج.
     */
    public function refreshStoredWac(StockItem $item): void
    {
        $row = $this->valueItem($item);
        if ($row['qty'] <= 0) {
            return;
        }

        $unit = round($row['cost_value'] / $row['qty'], 4);
        if (abs($unit - (float) $item->wac) < 0.00005) {
            return;
        }

        StockItem::query()->whereKey($item->id)->update(['wac' => $unit]);
    }

    /** @param  callable(array<string, mixed>): void  $callback */
    private function eachRow(callable $callback, bool $withSelling = true): void
    {
        $layersByItem = StockItemPrice::query()
            ->where('qty', '>', 0)
            ->get(['id', 'stock_item_id', 'price_ref', 'amount', 'qty', 'received_at', 'supply_request_line_id'])
            ->groupBy('stock_item_id');

        StockItem::query()
            ->select(['id', 'code', 'catalog_number', 'alt_codes', 'name', 'uom', 'qty', 'wac', 'price', 'is_quick_dispense'])
            ->orderBy('id')
            ->chunkById(500, function ($items) use ($layersByItem, $callback, $withSelling) {
                foreach ($items as $item) {
                    $callback($this->buildRow($item, $layersByItem->get($item->id, collect()), $withSelling));
                }
            });
    }

    /**
     * @param  Collection<int, StockItemPrice>  $layers
     * @return array<string, mixed>
     */
    private function buildRow(StockItem $item, Collection $layers, bool $withSelling): array
    {
        $qty = round(max(0.0, (float) $item->qty), 4);
        $price = max(0.0, (float) $item->price);
        $wac = max(0.0, (float) $item->wac);

        $positive = $layers->filter(fn (StockItemPrice $l) => (float) $l->qty > 0);
        $highestUnit = max($price, (float) ($positive->max(fn (StockItemPrice $l) => (float) $l->amount) ?? 0));

        // المتبقي في المخزن = آخر ما سيُصرف — نعكس ترتيب الصرف ونأخذ حتى رصيد الصنف.
        $remaining = $qty;
        $layeredCost = 0.0;
        $layeredQty = 0.0;
        $usedLayers = [];
        foreach (PriceBatchDispenseService::consumptionOrder($positive)->reverse() as $layer) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (float) $layer->qty);
            $amount = (float) $layer->amount;
            $layeredCost += $take * $amount;
            $layeredQty += $take;
            $remaining -= $take;
            $usedLayers[] = [
                'amount' => $amount,
                'qty' => round($take, 4),
                'opening' => $layer->isOpeningLayer(),
            ];
        }

        $openingQty = round(max(0.0, $remaining), 4);
        $openingUnit = PriceBatchDispenseService::openingUnitCost($item);
        $openingCost = $openingQty * $openingUnit;
        $costValue = $layeredCost + $openingCost;

        $wacUnit = $wac > 0 ? $wac : $highestUnit;
        $highestValue = $qty * $highestUnit;

        $row = [
            'id' => (int) $item->id,
            'code' => (string) $item->code,
            'operational_code' => (string) ($item->alt_codes ?? ''),
            'name' => (string) $item->name,
            'uom' => (string) ($item->uom ?? ''),
            'is_quick_dispense' => (bool) $item->is_quick_dispense,
            'qty' => $qty,
            'layered_qty' => round($layeredQty, 4),
            'opening_qty' => $openingQty,
            'opening_unit_cost' => round($openingUnit, 4),
            'layered_cost_value' => round($layeredCost, 2),
            'opening_cost_value' => round($openingCost, 2),
            'cost_value' => round($costValue, 2),
            'unit_cost' => $qty > 0 ? round($costValue / $qty, 4) : 0.0,
            'wac_unit' => round($wacUnit, 4),
            'wac_value' => round($qty * $wacUnit, 2),
            'highest_unit' => round($highestUnit, 4),
            'highest_value' => round($highestValue, 2),
            'layers' => array_reverse($usedLayers),
        ];

        if ($withSelling) {
            $split = $this->selling(
                $row['is_quick_dispense'] ? 0.0 : $highestValue,
                $row['is_quick_dispense'] ? $highestValue : 0.0,
            );
            $row['selling_value'] = (float) $split['selling_price'];
            $row['selling_unit'] = $qty > 0 ? round($row['selling_value'] / $qty, 4) : 0.0;
        }

        return $row;
    }
}
