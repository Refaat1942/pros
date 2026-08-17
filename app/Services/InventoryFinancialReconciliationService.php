<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\StockMovement;
use App\Support\CaseFinancialSummary;
use Carbon\Carbon;

/**
 * Bridges warehouse movements (WAC) with delivered revenue for finance review.
 */
class InventoryFinancialReconciliationService
{
    /**
     * @return array{
     *     from: Carbon,
     *     to: Carbon,
     *     inventory: array<string, float>,
     *     revenue: array<string, float>,
     *     bridge: array<string, float>
     * }
     */
    public function periodSummary(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $receivedValue = $this->movementValue(StockMovement::TYPE_RECEIVE, $from, $to);
        $issuedValue = $this->movementValue(StockMovement::TYPE_ISSUE, $from, $to);
        $returnedValue = $this->movementValue(StockMovement::TYPE_RETURN, $from, $to);

        $delivered = CaseRecord::query()
            ->where('stage_key', CaseRecord::STAGE_DELIVERED)
            ->whereBetween('delivered_at', [$from, $to])
            ->get(['id', 'patient_type', 'quote_total', 'military_selling_price', 'internal_cost', 'issue_cost']);

        $deliveredRevenue = round($delivered->sum(function (CaseRecord $case) {
            if ($case->patient_type === Patient::TYPE_MILITARY) {
                return (float) ($case->military_selling_price ?? 0);
            }

            return CaseFinancialSummary::totalCost($case);
        }), 2);

        $deliveredWacCost = round($delivered->sum(fn (CaseRecord $case) => $this->resolvedIssueCost($case)), 2);
        $grossMargin = round($deliveredRevenue - $deliveredWacCost, 2);
        $marginPct = $deliveredRevenue > 0
            ? round(($grossMargin / $deliveredRevenue) * 100, 2)
            : 0.0;

        $arPosted = round((float) CaseRecord::query()
            ->where('patient_type', Patient::TYPE_CIVILIAN)
            ->whereNotNull('ledger_posted_at')
            ->whereBetween('ledger_posted_at', [$from, $to])
            ->sum('quote_total'), 2);

        return [
            'from' => $from,
            'to' => $to,
            'inventory' => [
                'received_value' => $receivedValue,
                'issued_value' => $issuedValue,
                'returned_value' => $returnedValue,
                'net_outflow' => round($issuedValue - $returnedValue, 2),
            ],
            'revenue' => [
                'delivered_count' => $delivered->count(),
                'delivered_revenue' => $deliveredRevenue,
                'delivered_wac_cost' => $deliveredWacCost,
                'gross_margin' => $grossMargin,
                'margin_pct' => $marginPct,
                'civilian_ar_posted_at_dispense' => $arPosted,
            ],
            'bridge' => [
                'issue_vs_delivered_cost' => round($issuedValue - $deliveredWacCost, 2),
            ],
        ];
    }

    private function movementValue(string $type, Carbon $from, Carbon $to): float
    {
        return round((float) StockMovement::query()
            ->where('movement_type', $type)
            ->whereBetween('moved_at', [$from, $to])
            ->get(['quantity', 'unit_cost'])
            ->sum(fn (StockMovement $m) => abs((int) $m->quantity) * (float) $m->unit_cost), 2);
    }

    private function resolvedIssueCost(CaseRecord $case): float
    {
        $issueCost = (float) ($case->issue_cost ?? 0);

        if ($issueCost > 0) {
            return $issueCost;
        }

        return (float) ($case->internal_cost ?? 0);
    }
}
