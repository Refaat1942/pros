<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\PricingRequestItem;
use App\Models\StockItem;
use App\Support\ClinicTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * إحصائيات البيع حسب مستوى السعر — من بنود طلبات التسعير للحالات المُسلَّمة.
 */
class StockItemSalesStatsService
{
    /** @return array{from: Carbon|null, to: Carbon|null} */
    public function parseDateRange(?string $from, ?string $to): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate   = $to ? Carbon::parse($to)->endOfDay() : null;

        if ($fromDate && $toDate && $fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        return ['from' => $fromDate, 'to' => $toDate];
    }

    /**
     * @return array{
     *     item_code: string,
     *     item_name: string,
     *     highest_registered: float,
     *     total_sold_qty: int,
     *     total_sale_times: int,
     *     period_label: string,
     *     rows: list<array{unit_price: float, registered: bool, sale_times: int, sold_qty: int}>
     * }
     */
    public function breakdownForItem(StockItem $item, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $item->loadMissing('prices:id,stock_item_id,amount,label');

        $salesByPrice = $this->salesAggregates($from, $to, $item->code);
        $registered   = $this->registeredPriceAmounts($item);
        $rows         = $this->mergePriceRows($registered, $salesByPrice);

        return [
            'item_code'          => $item->code,
            'item_name'          => $item->name,
            'highest_registered' => $registered !== [] ? max($registered) : (float) $item->price,
            'total_sold_qty'     => (int) $rows->sum('sold_qty'),
            'total_sale_times'   => $this->distinctDeliveredCases($from, $to, $item->code),
            'period_label'       => $this->periodLabel($from, $to),
            'rows'               => $rows->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function report(?Carbon $from = null, ?Carbon $to = null, ?string $search = null): array
    {
        $query = StockItem::query()
            ->with('prices:id,stock_item_id,amount')
            ->when($search, fn ($q, $term) => $q->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
            }))
            ->orderBy('code');

        $salesByCode = $this->salesAggregatesByItem($from, $to);

        return $query->get()
            ->flatMap(function (StockItem $item) use ($salesByCode) {
                $registered = $this->registeredPriceAmounts($item);
                $sales      = $salesByCode->get($item->code, collect());
                $rows       = $this->mergePriceRows($registered, $sales);

                if ($rows->every(fn (array $row) => $row['sale_times'] === 0 && $row['sold_qty'] === 0)) {
                    return [];
                }

                return $rows->map(fn (array $row) => [
                    'item_code'  => $item->code,
                    'item_name'  => $item->name,
                    'unit_price' => $row['unit_price'],
                    'registered' => $row['registered'],
                    'sale_times' => $row['sale_times'],
                    'sold_qty'   => $row['sold_qty'],
                ])->all();
            })
            ->values()
            ->all();
    }

    /**
     * @return array{title: string, period_label: string, headers: list<string>, rows: list<list<string>>}
     */
    public function exportReport(?Carbon $from = null, ?Carbon $to = null, ?string $search = null): array
    {
        $rows = $this->report($from, $to, $search);

        return [
            'title'        => 'إحصائيات البيع حسب مستوى السعر',
            'period_label' => $this->periodLabel($from, $to),
            'headers'      => [
                'كود الصنف',
                'اسم الصنف',
                'السعر (ج.م)',
                'مسجّل في الدليل',
                'مرات البيع',
                'الكمية المباعة',
            ],
            'rows' => array_map(fn (array $row) => [
                $row['item_code'],
                $row['item_name'],
                number_format((float) $row['unit_price'], 2, '.', ''),
                $row['registered'] ? 'نعم' : 'لا',
                (string) $row['sale_times'],
                (string) $row['sold_qty'],
            ], $rows),
        ];
    }

    /** @return list<float> */
    private function registeredPriceAmounts(StockItem $item): array
    {
        $amounts = [];

        if ((float) $item->price > 0) {
            $amounts[$this->priceKey((float) $item->price)] = (float) $item->price;
        }

        foreach ($item->prices as $priceRow) {
            $amount = (float) $priceRow->amount;
            if ($amount > 0) {
                $amounts[$this->priceKey($amount)] = $amount;
            }
        }

        $values = array_values($amounts);
        rsort($values);

        return $values;
    }

    /** @return Collection<string, object{unit_price: string, sold_qty: string, sale_times: string}> */
    private function salesAggregates(?Carbon $from, ?Carbon $to, ?string $stockItemCode = null): Collection
    {
        return $this->baseSalesQuery($from, $to, $stockItemCode)
            ->select([
                'pricing_request_items.unit_price',
                DB::raw('SUM(pricing_request_items.qty) as sold_qty'),
                DB::raw('COUNT(DISTINCT cases.id) as sale_times'),
            ])
            ->groupBy('pricing_request_items.unit_price')
            ->get()
            ->keyBy(fn ($row) => $this->priceKey((float) $row->unit_price));
    }

    /** @return Collection<string, Collection<string, object>> */
    private function salesAggregatesByItem(?Carbon $from, ?Carbon $to): Collection
    {
        return $this->baseSalesQuery($from, $to)
            ->select([
                'pricing_request_items.stock_item_code',
                'pricing_request_items.unit_price',
                DB::raw('SUM(pricing_request_items.qty) as sold_qty'),
                DB::raw('COUNT(DISTINCT cases.id) as sale_times'),
            ])
            ->groupBy('pricing_request_items.stock_item_code', 'pricing_request_items.unit_price')
            ->get()
            ->groupBy('stock_item_code')
            ->map(fn (Collection $group) => $group->keyBy(
                fn ($row) => $this->priceKey((float) $row->unit_price)
            ));
    }

    private function baseSalesQuery(?Carbon $from, ?Carbon $to, ?string $stockItemCode = null)
    {
        return PricingRequestItem::query()
            ->join('pricing_requests', 'pricing_requests.id', '=', 'pricing_request_items.pricing_request_id')
            ->join('cases', 'cases.id', '=', 'pricing_requests.case_id')
            ->where('cases.stage_key', CaseRecord::STAGE_DELIVERED)
            ->where('pricing_request_items.unit_price', '>', 0)
            ->when($stockItemCode, fn ($q, $code) => $q->where('pricing_request_items.stock_item_code', $code))
            ->when($from, fn ($q, Carbon $start) => $q->where('cases.delivered_at', '>=', $start))
            ->when($to, fn ($q, Carbon $end) => $q->where('cases.delivered_at', '<=', $end));
    }

    private function distinctDeliveredCases(?Carbon $from, ?Carbon $to, string $stockItemCode): int
    {
        return (int) $this->baseSalesQuery($from, $to, $stockItemCode)
            ->distinct('cases.id')
            ->count('cases.id');
    }

    /**
     * @param  list<float>  $registered
     * @param  Collection<string, object>  $salesByPrice
     * @return Collection<int, array{unit_price: float, registered: bool, sale_times: int, sold_qty: int}>
     */
    private function mergePriceRows(array $registered, Collection $salesByPrice): Collection
    {
        $rows   = collect();
        $seen   = [];

        foreach ($registered as $amount) {
            $key = $this->priceKey($amount);
            $sale = $salesByPrice->get($key);
            $rows->push([
                'unit_price'  => $amount,
                'registered'  => true,
                'sale_times'  => (int) ($sale->sale_times ?? 0),
                'sold_qty'    => (int) ($sale->sold_qty ?? 0),
            ]);
            $seen[$key] = true;
        }

        foreach ($salesByPrice as $key => $sale) {
            if (isset($seen[$key])) {
                continue;
            }

            $rows->push([
                'unit_price'  => (float) $sale->unit_price,
                'registered'  => false,
                'sale_times'  => (int) $sale->sale_times,
                'sold_qty'    => (int) $sale->sold_qty,
            ]);
        }

        return $rows->sortByDesc('unit_price')->values();
    }

    private function priceKey(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }

    private function periodLabel(?Carbon $from, ?Carbon $to): string
    {
        if (! $from && ! $to) {
            return 'كل الفترات — الحالات المُسلَّمة فقط';
        }

        $fromLabel = $from ? ClinicTime::format($from, 'd/m/Y') : '—';
        $toLabel   = $to ? ClinicTime::format($to, 'd/m/Y') : '—';

        return "الفترة: {$fromLabel} — {$toLabel} (حالات مُسلَّمة)";
    }
}
