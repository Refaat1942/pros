<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\ContractCompanyDebt;
use App\Models\MilitaryDebt;
use App\Models\Patient;
use App\Models\StockItem;
use App\Models\Supplier;

/**
 * لوحات القيادة الخمس — استعلامات للقراءة فقط.
 */
class BiReportService
{
    public function __construct(private readonly StockPriceService $stockPriceService)
    {
    }

    /**
     * Board 1 — إدارة المرضى و SLA.
     */
    public function boardPatients(): array
    {
        $slaDays = config('erp.sla_days', 21);

        $avgTurnaround = CaseRecord::query()
            ->where('patient_type', Patient::TYPE_CIVILIAN)
            ->where('stage_key', CaseRecord::STAGE_DELIVERED)
            ->whereNotNull('quote_date')
            ->whereNotNull('delivered_at')
            ->selectRaw('AVG(DATEDIFF(delivered_at, quote_date)) as avg_days')
            ->value('avg_days');

        $slaBreachedCases = CaseRecord::query()
            ->with('patient:id,name')
            ->where('patient_type', Patient::TYPE_CIVILIAN)
            ->where('stage_key', '!=', CaseRecord::STAGE_DELIVERED)
            ->whereNotNull('quote_date')
            ->whereRaw('DATEDIFF(CURDATE(), quote_date) > ?', [$slaDays])
            ->orderByDesc('quote_date')
            ->limit(20)
            ->get(['id', 'case_no', 'order_ref', 'quote_date', 'stage_key', 'patient_id'])
            ->map(fn (CaseRecord $c) => [
                'case_no'    => $c->case_no,
                'order_ref'  => $c->order_ref,
                'patient'    => $c->patient?->name ?? '—',
                'quote_date' => $c->quote_date?->toDateString(),
                'days_open'  => $c->quote_date?->diffInDays(now()),
            ])
            ->all();

        return [
            'total_cases'        => CaseRecord::count(),
            'civilian_count'     => CaseRecord::where('patient_type', Patient::TYPE_CIVILIAN)->count(),
            'military_count'     => CaseRecord::where('patient_type', Patient::TYPE_MILITARY)->count(),
            'open_count'         => CaseRecord::where('stage_key', '!=', CaseRecord::STAGE_DELIVERED)->count(),
            'avg_turnaround'     => $avgTurnaround !== null ? round((float) $avgTurnaround, 1) : null,
            'sla_breached'       => count($slaBreachedCases),
            'sla_breached_cases' => $slaBreachedCases,
            'sla_days'           => $slaDays,
        ];
    }

    /**
     * Board 2 — المخزون و WAC.
     */
    public function boardInventory(): array
    {
        $stagnantCutoff = now()->subDays(180)->toDateString();

        $totalValue = StockItem::query()
            ->get(['code', 'qty', 'wac'])
            ->sum(fn (StockItem $i) => (int) $i->qty * $this->stockPriceService->wacUnitPrice($i->code));

        $stagnantItems = StockItem::query()
            ->whereNotNull('last_moved_at')
            ->where('last_moved_at', '<', $stagnantCutoff)
            ->orderBy('code')
            ->limit(50)
            ->get(['code', 'name', 'qty', 'last_moved_at'])
            ->map(fn (StockItem $i) => [
                'code'          => $i->code,
                'name'          => $i->name,
                'qty'           => $i->qty,
                'last_moved_at' => $i->last_moved_at?->toDateString(),
            ])
            ->all();

        return [
            'total_value'    => round($totalValue, 2),
            'item_count'     => StockItem::count(),
            'low_stock'      => StockItem::where('status', StockItem::STATUS_LOW)->count(),
            'stagnant_items' => $stagnantItems,
        ];
    }

    /**
     * Board 3 — العمليات وأوامر الشغل.
     */
    public function boardOperations(): array
    {
        return [
            'open_work_orders'   => CaseRecord::where('stage_key', CaseRecord::STAGE_MANUFACTURING)->count(),
            'awaiting_dispense'  => CaseRecord::where('stage_key', CaseRecord::STAGE_MANUFACTURING)
                ->whereHas('bom', fn ($q) => $q->where('stage', Bom::STAGE_RAW))
                ->count(),
            'in_workshop'        => CaseRecord::where('stage_key', CaseRecord::STAGE_MANUFACTURING)
                ->whereHas('bom', fn ($q) => $q->where('stage', Bom::STAGE_WIP))
                ->count(),
            'ready_for_delivery' => CaseRecord::where('stage_key', CaseRecord::STAGE_READY_DELIVERY)->count(),
        ];
    }

    /**
     * Board 4 — الجهات والتكاليف (مدني / عسكري منفصلان).
     */
    public function boardEntitiesAndCosts(): array
    {
        $civilianCumulative = (float) CaseRecord::query()
            ->where('patient_type', Patient::TYPE_CIVILIAN)
            ->sum('quote_total');

        $militaryAggregated = (float) CaseRecord::query()
            ->where('patient_type', Patient::TYPE_MILITARY)
            ->sum('total_cost');

        $militaryDebtPending = (float) MilitaryDebt::query()
            ->where('status', MilitaryDebt::STATUS_PENDING)
            ->sum('total_cost');

        $militaryDebtCollected = (float) MilitaryDebt::query()
            ->where('status', MilitaryDebt::STATUS_COLLECTED)
            ->sum('total_cost');

        $debts = ContractCompanyDebt::with('contractCompany:id,company_code,name,is_military')
            ->whereHas('contractCompany', fn ($q) => $q->where('is_military', false))
            ->orderByDesc('due')
            ->get();

        $companyDebts = $debts->map(function (ContractCompanyDebt $d) {
            $due       = (float) $d->due;
            $collected = (float) $d->collected;

            return [
                'company_code' => $d->contractCompany?->company_code,
                'company_name' => $d->contractCompany?->name,
                'is_military'  => (bool) $d->contractCompany?->is_military,
                'due'          => $due,
                'collected'    => $collected,
                'remaining'    => max(0, $due - $collected),
                'status'       => $d->status,
            ];
        })->all();

        $netDebts = collect($companyDebts)->sum('remaining');

        return [
            'civilian_cumulative_cost' => round($civilianCumulative, 2),
            'military_aggregated_cost' => round($militaryAggregated, 2),
            'military_debt_pending'    => round($militaryDebtPending, 2),
            'military_debt_collected'  => round($militaryDebtCollected, 2),
            'net_debts'                => round($netDebts, 2),
            'company_debts'            => $companyDebts,
        ];
    }

    /**
     * Board 5 — المشتريات: WAC مقابل أعلى سعر شراء.
     */
    public function boardPurchasing(): array
    {
        $topN = (int) config('erp.bi_purchasing_top_n', 10);

        $comparison = StockItem::query()
            ->orderBy('code')
            ->get(['code', 'name', 'wac'])
            ->map(function (StockItem $item) {
                $wac     = (float) ($item->wac ?? 0);
                $highest = $this->stockPriceService->highestUnitPrice($item->code);
                $diff    = round($highest - $wac, 2);

                return [
                    'code'                  => $item->code,
                    'name'                  => $item->name,
                    'wac'                   => $wac,
                    'highest_purchase_price' => $highest,
                    'diff'                  => $diff,
                    'margin_erosion'        => $diff > 0,
                ];
            })
            ->sortByDesc('diff')
            ->take($topN)
            ->values()
            ->all();

        return [
            'supplier_count'   => Supplier::count(),
            'price_comparison' => $comparison,
        ];
    }
}
