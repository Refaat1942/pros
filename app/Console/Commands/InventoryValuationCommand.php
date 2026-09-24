<?php

namespace App\Console\Commands;

use App\Models\StockItem;
use App\Services\InventoryValuationService;
use Illuminate\Console\Command;

/**
 * فحص قيمة المخزون — نفس الأرقام الظاهرة في النظرة العامة، مع تفصيل الأصناف والطبقات.
 */
class InventoryValuationCommand extends Command
{
    protected $signature = 'prosthetics:inventory-valuation
        {--top=20 : عدد الأصناف الأعلى قيمة في التفصيل}
        {--sync-wac : تحديث WAC المخزّن لكل صنف ليساوي متوسط تكلفة رصيده الفعلي (FIFO)}';

    protected $description = 'Show inventory value at FIFO cost and selling price, per item layers; optionally refresh stale WAC';

    public function handle(InventoryValuationService $valuation): int
    {
        if ($this->option('sync-wac')) {
            $updated = 0;
            StockItem::query()->where('qty', '>', 0)->orderBy('id')->chunkById(200, function ($items) use ($valuation, &$updated) {
                foreach ($items as $item) {
                    $before = (float) $item->wac;
                    $valuation->refreshStoredWac($item);
                    if (abs((float) $item->fresh()->wac - $before) >= 0.00005) {
                        $updated++;
                    }
                }
            });
            $this->info("تم تحديث WAC لـ {$updated} صنف.");
        }

        $summary = $valuation->summary();
        $money = fn (float $v) => number_format(round($v)).' ج.م';

        $this->table(['البند', 'القيمة'], [
            ['عدد الأصناف', (string) $summary['item_count']],
            ['أصناف برصيد', (string) $summary['stocked_items']],
            ['إجمالي الكميات', rtrim(rtrim(number_format($summary['total_qty'], 4), '0'), '.')],
            ['قيمة المخزون — التكلفة (FIFO)', $money($summary['cost_value'])],
            ['  منها طبقات شراء/استلام', $money($summary['layered_cost_value'])],
            ['  منها رصيد أول المدة (شيت الأصناف)', $money($summary['opening_cost_value'])],
            ['قيمة المخزون — سعر البيع', $money($summary['selling_value'])],
            ['الهامش المتوقع', $money($summary['expected_margin'])],
            ['قيمة المخزون (WAC المخزّن)', $money($summary['wac_value'])],
        ]);

        $top = max(0, (int) $this->option('top'));
        if ($top === 0) {
            return self::SUCCESS;
        }

        $rows = collect($valuation->rows())
            ->filter(fn (array $r) => $r['qty'] > 0)
            ->sortByDesc('cost_value')
            ->take($top)
            ->map(function (array $r) {
                $layers = collect($r['layers'])
                    ->map(fn (array $l) => ($l['opening'] ? 'أول المدة ' : '').number_format($l['amount'], 2).'×'.$l['qty'])
                    ->when($r['opening_qty'] > 0, fn ($c) => $c->push('أول المدة '.number_format($r['opening_unit_cost'], 2).'×'.$r['opening_qty']))
                    ->implode(' · ');

                return [
                    $r['code'],
                    mb_strimwidth($r['name'], 0, 40, '…'),
                    $r['qty'],
                    $layers !== '' ? $layers : '—',
                    number_format($r['cost_value'], 2),
                    number_format($r['wac_unit'], 2),
                    number_format($r['selling_value'], 2),
                ];
            })
            ->values()
            ->all();

        $this->table(['الكود', 'الصنف', 'الرصيد', 'طبقات التكلفة', 'التكلفة', 'WAC المخزّن', 'سعر البيع'], $rows);

        return self::SUCCESS;
    }
}
