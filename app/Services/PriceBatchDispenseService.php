<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\ReturnNote;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
/**
 * صرف وارتجاع مخزون حسب دفعات الأسعار — طلب التوريد أولاً ثم استنفاد كل سعر بترتيب الاستلام.
 */
class PriceBatchDispenseService
{
    /**
     * تخصيص كمية الصرف على دفعات الأسعار (طلب التوريد ثم استنفاد كل سعر بترتيب الاستلام).
     *
     * @return list<array{batch_id: int, qty: float, unit_price: float}>
     */
    public function allocateForDispense(StockItem $item, float $qtyNeeded): array
    {
        if ($qtyNeeded <= 0) {
            return [];
        }

        $remaining = $qtyNeeded;
        $allocations = [];

        foreach ($this->orderedConsumableBatches($item) as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = max(0.0, (float) $batch->qty);
            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);
            $allocations[] = [
                'batch_id' => (int) $batch->id,
                'qty' => $take,
                'unit_price' => (float) $batch->amount,
            ];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            $fallback = $this->fallbackBatchForBackorder($item);
            $allocations[] = [
                'batch_id' => (int) $fallback->id,
                'qty' => $remaining,
                'unit_price' => (float) $fallback->amount,
            ];
        }

        return $allocations;
    }

    /**
     * @param  list<array{batch_id: int, qty: float, unit_price: float}>  $allocations
     */
    public function applyDecrements(array $allocations): void
    {
        foreach ($allocations as $row) {
            StockItemPrice::whereKey($row['batch_id'])
                ->decrement('qty', $row['qty']);
        }
    }

    /**
     * @param  list<array{batch_id: int, qty: float, unit_price: float}>  $allocations
     */
    public function applyIncrements(array $allocations): void
    {
        foreach ($allocations as $row) {
            StockItemPrice::whereKey($row['batch_id'])
                ->increment('qty', $row['qty']);
        }
    }

    /**
     * تخصيص ارتجاع — LIFO على دفعات الصرف الأصلية لنفس BOM.
     *
     * @return list<array{batch_id: int, qty: float, unit_price: float}>
     */
    public function allocateForReturn(Bom $bom, StockItem $item, float $qtyReturn): array
    {
        if ($qtyReturn <= 0) {
            return [];
        }

        $issuedByBatch = $this->issuedQtyByBatchForBom($bom->id, $item->id);
        $returnedByBatch = $this->returnedQtyByBatchForBom($bom->id, $item->id);

        $batchOrder = StockMovement::query()
            ->where('stock_item_id', $item->id)
            ->where('movement_type', StockMovement::TYPE_ISSUE)
            ->where('reference_type', 'bom')
            ->where('reference_id', $bom->id)
            ->whereNotNull('stock_item_price_id')
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->pluck('stock_item_price_id')
            ->unique()
            ->values();

        $remaining = $qtyReturn;
        $allocations = [];

        foreach ($batchOrder as $batchId) {
            if ($remaining <= 0) {
                break;
            }

            $issued = (float) ($issuedByBatch[$batchId] ?? 0);
            $returned = (float) ($returnedByBatch[$batchId] ?? 0);
            $restorable = max(0.0, $issued - $returned);

            if ($restorable <= 0) {
                continue;
            }

            $take = min($restorable, $remaining);
            $batch = StockItemPrice::find($batchId);
            $unitPrice = (float) ($batch?->amount ?? 0);

            $issueMovement = StockMovement::query()
                ->where('stock_item_id', $item->id)
                ->where('movement_type', StockMovement::TYPE_ISSUE)
                ->where('reference_type', 'bom')
                ->where('reference_id', $bom->id)
                ->where('stock_item_price_id', $batchId)
                ->orderByDesc('id')
                ->first();

            if ($issueMovement && (float) $issueMovement->unit_cost > 0) {
                $unitPrice = (float) $issueMovement->unit_cost;
            }

            $allocations[] = [
                'batch_id' => (int) $batchId,
                'qty' => $take,
                'unit_price' => $unitPrice,
            ];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            $fallback = $this->fallbackBatchForBackorder($item);
            $allocations[] = [
                'batch_id' => (int) $fallback->id,
                'qty' => $remaining,
                'unit_price' => (float) $fallback->amount,
            ];
        }

        return $allocations;
    }

    /**
     * ملخص دفعات الأسعار النشطة لعرض تنبيه المعدلات.
     *
     * @return null|array{
     *     stock_item_code: string,
     *     tiers: list<array{amount: float, qty: float, from_supply: bool}>,
     *     message: string
     * }
     */
    public function multiPriceAlertForCode(string $stockItemCode): ?array
    {
        $item = StockItem::findByOperationalCode($stockItemCode);
        if (! $item) {
            return null;
        }

        return $this->multiPriceAlertForItem($item);
    }

    /**
     * @return null|array{
     *     stock_item_code: string,
     *     tiers: list<array{amount: float, qty: float, from_supply: bool}>,
     *     message: string
     * }
     */
    public function multiPriceAlertForItem(StockItem $item): ?array
    {
        $tiers = $this->activePriceTiers($item);

        if (count($tiers) < 2) {
            return null;
        }

        $amounts = array_map(fn (array $t) => number_format($t['amount'], 2, '.', ''), $tiers);
        $qtys = array_map(fn (array $t) => $this->formatTierQty($t['qty']), $tiers);

        $message = sprintf(
            'الصنف %s له %d أسعار شراء (%s) وأرصدة (%s). عند الصرف: طلب التوريد أولاً ثم استنفاد رصيد كل سعر بترتيب الاستلام — كل وحدة بسعر دفعتها.',
            $item->operationalCode(),
            count($tiers),
            implode('، ', $amounts),
            implode('، ', $qtys),
        );

        return [
            'stock_item_code' => $item->operationalCode(),
            'tiers' => $tiers,
            'message' => $message,
        ];
    }

    /**
     * @return list<array{amount: float, qty: float, from_supply: bool}>
     */
    public function activePriceTiers(StockItem $item): array
    {
        $batches = StockItemPrice::query()
            ->where('stock_item_id', $item->id)
            ->where('amount', '>', 0)
            ->orderBy('amount')
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $tiers = [];
        $seenAmounts = [];

        foreach ($batches as $batch) {
            $amount = round((float) $batch->amount, 2);
            $qty = (float) $batch->qty;

            if ($qty <= 0 && $batch->supply_request_line_id === null) {
                continue;
            }

            if (isset($seenAmounts[$amount])) {
                $tiers[$seenAmounts[$amount]]['qty'] += $qty;
                if ($batch->supply_request_line_id !== null) {
                    $tiers[$seenAmounts[$amount]]['from_supply'] = true;
                }

                continue;
            }

            $seenAmounts[$amount] = count($tiers);
            $tiers[] = [
                'amount' => $amount,
                'qty' => $qty,
                'from_supply' => $batch->supply_request_line_id !== null,
            ];
        }

        return $tiers;
    }

    /**
     * ترتيب الدفعات: طلب التوريد أولاً، ثم مستويات السعر بأول تاريخ استلام لكل سعر،
     * ثم FIFO داخل نفس السعر حتى ينفد رصيده قبل الانتقال للسعر التالي.
     *
     * @return Collection<int, StockItemPrice>
     */
    private function orderedConsumableBatches(StockItem $item): Collection
    {
        $batches = StockItemPrice::query()
            ->where('stock_item_id', $item->id)
            ->where('qty', '>', 0)
            ->lockForUpdate()
            ->get();

        $supplyBatches = $batches
            ->filter(fn (StockItemPrice $batch) => $batch->supply_request_line_id !== null)
            ->sortBy(fn (StockItemPrice $batch) => [$batch->received_at, $batch->id])
            ->values();

        $regularBatches = $batches
            ->filter(fn (StockItemPrice $batch) => $batch->supply_request_line_id === null);

        $tierGroups = $regularBatches->groupBy(
            fn (StockItemPrice $batch) => round((float) $batch->amount, 2)
        );

        $sortedTiers = $tierGroups->sortBy(function (Collection $group) {
            $first = $group->sortBy(fn (StockItemPrice $batch) => [$batch->received_at, $batch->id])->first();

            return [$first->received_at, $first->id];
        });

        $orderedRegular = collect();
        foreach ($sortedTiers as $tierBatches) {
            $orderedRegular = $orderedRegular->merge(
                $tierBatches
                    ->sortBy(fn (StockItemPrice $batch) => [$batch->received_at, $batch->id])
                    ->values()
            );
        }

        return $supplyBatches->merge($orderedRegular);
    }

    private function fallbackBatchForBackorder(StockItem $item): StockItemPrice
    {
        $batch = StockItemPrice::query()
            ->where('stock_item_id', $item->id)
            ->orderByDesc('amount')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($batch) {
            return $batch;
        }

        return StockItemPrice::create([
            'stock_item_id' => $item->id,
            'price_ref' => sprintf('PR-%s-FALLBACK', $item->code),
            'amount' => max((float) $item->price, (float) $item->wac),
            'qty' => 0,
            'received_at' => now()->toDateString(),
        ]);
    }

    /** @return array<int, float> */
    private function issuedQtyByBatchForBom(int $bomId, int $stockItemId): array
    {
        return StockMovement::query()
            ->where('stock_item_id', $stockItemId)
            ->where('movement_type', StockMovement::TYPE_ISSUE)
            ->where('reference_type', 'bom')
            ->where('reference_id', $bomId)
            ->whereNotNull('stock_item_price_id')
            ->selectRaw('stock_item_price_id, SUM(ABS(quantity)) as total')
            ->groupBy('stock_item_price_id')
            ->pluck('total', 'stock_item_price_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /** @return array<int, float> */
    private function returnedQtyByBatchForBom(int $bomId, int $stockItemId): array
    {
        $returnNoteIds = ReturnNote::query()
            ->where('bom_id', $bomId)
            ->pluck('id');

        if ($returnNoteIds->isEmpty()) {
            return [];
        }

        return StockMovement::query()
            ->where('stock_item_id', $stockItemId)
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->where('reference_type', 'return_note')
            ->whereIn('reference_id', $returnNoteIds)
            ->whereNotNull('stock_item_price_id')
            ->selectRaw('stock_item_price_id, SUM(quantity) as total')
            ->groupBy('stock_item_price_id')
            ->pluck('total', 'stock_item_price_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    private function formatTierQty(float $qty): string
    {
        if (abs($qty - round($qty)) < 0.0001) {
            return (string) (int) round($qty);
        }

        return rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
    }

    /**
     * متوسط تكلفة الوحدة من تخصيصات الدفعات.
     *
     * @param  list<array{batch_id: int, qty: float, unit_price: float}>  $allocations
     */
    public function weightedUnitCost(array $allocations): float
    {
        $totalQty = 0.0;
        $totalValue = 0.0;

        foreach ($allocations as $row) {
            $totalQty += $row['qty'];
            $totalValue += $row['qty'] * $row['unit_price'];
        }

        if ($totalQty <= 0) {
            return 0.0;
        }

        return round($totalValue / $totalQty, 4);
    }
}
