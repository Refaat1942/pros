<?php

namespace App\Services;

use App\Models\StockItem;
use Illuminate\Support\Collection;

/**
 * تقارير مستويات أسعار الشراء وأرصدة الدفعات (صرف FIFO متعدد الأسعار).
 */
class PriceTierReportService
{
    public function __construct(
        private readonly StockCatalogService $catalogService,
    ) {}

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     tier_count: int,
     *     tiers_summary: string,
     *     warehouse_qty: int
     * }>
     */
    public function multiPriceItemRows(): array
    {
        $rows = [];

        StockItem::query()
            ->select('id', 'code', 'name', 'qty')
            ->orderBy('code')
            ->chunkById(200, function (Collection $items) use (&$rows) {
                foreach ($items as $item) {
                    $tiers = $this->catalogService->aggregatePriceTiers($item);
                    if (count($tiers) < 2) {
                        continue;
                    }

                    $parts = [];
                    foreach ($tiers as $tier) {
                        $parts[] = number_format((float) $tier['amount'], 2).' ج.م × '.$this->formatQty((float) $tier['qty']);
                    }

                    $rows[] = [
                        'code' => $item->code ?? '—',
                        'name' => $item->name ?? '—',
                        'tier_count' => count($tiers),
                        'tiers_summary' => implode(' · ', $parts),
                        'warehouse_qty' => (int) $item->qty,
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     amount: float,
     *     qty: float,
     *     from_supply: bool,
     *     first_received: string
     * }>
     */
    public function tierBalanceRows(): array
    {
        $rows = [];

        StockItem::query()
            ->select('id', 'code', 'name')
            ->orderBy('code')
            ->chunkById(200, function (Collection $items) use (&$rows) {
                foreach ($items as $item) {
                    foreach ($this->catalogService->aggregatePriceTiers($item) as $tier) {
                        $rows[] = [
                            'code' => $item->code ?? '—',
                            'name' => $item->name ?? '—',
                            'amount' => (float) $tier['amount'],
                            'qty' => (float) $tier['qty'],
                            'from_supply' => (bool) $tier['from_supply'],
                            'first_received' => $tier['first_received'] ?? '—',
                        ];
                    }
                }
            });

        return $rows;
    }

    private function formatQty(float $qty): string
    {
        if (abs($qty - round($qty)) < 0.0001) {
            return (string) (int) round($qty);
        }

        return rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
    }
}
