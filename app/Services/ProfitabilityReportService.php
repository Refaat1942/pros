<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Support\CaseFinancialSummary;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * مراجعة التكاليف والربحية — يقارن الإيراد بالتكلفة الداخلية (WAC) للحالات
 * المُسلَّمة خلال فترة، ويحسب مجمل الربح ونسبته، مجمّعاً حسب نوع المريض
 * وجهة التعاقد وكل حالة على حدة.
 */
class ProfitabilityReportService
{
    /**
     * @return array{
     *     from: Carbon, to: Carbon,
     *     cases: list<array<string, mixed>>,
     *     by_patient_type: array<string, array<string, mixed>>,
     *     by_company: list<array<string, mixed>>,
     *     totals: array<string, mixed>
     * }
     */
    public function report(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $cases = CaseRecord::query()
            ->with('patient:id,name')
            ->where('stage_key', CaseRecord::STAGE_DELIVERED)
            ->whereBetween('delivered_at', [$from, $to])
            ->orderByDesc('delivered_at')
            ->get();

        $rows = $cases->map(fn (CaseRecord $case) => $this->caseRow($case));

        return [
            'from' => $from,
            'to' => $to,
            'cases' => $rows->values()->all(),
            'by_patient_type' => $this->groupByPatientType($rows),
            'by_company' => $this->groupByCompany($rows),
            'totals' => $this->aggregate($rows),
        ];
    }

    /** @return array<string, mixed> */
    private function caseRow(CaseRecord $case): array
    {
        $isMilitary = $case->patient_type === Patient::TYPE_MILITARY;

        $revenue = $isMilitary
            ? (float) $case->military_selling_price
            : CaseFinancialSummary::totalCost($case);

        $cost = (float) $case->internal_cost;
        $margin = round($revenue - $cost, 2);

        return [
            'case_no' => $case->case_no ?? '—',
            'patient_name' => $case->patient?->name ?? '—',
            'patient_type' => $case->patient_type,
            'company' => $case->company_name ?: 'نقدي / بدون جهة',
            'revenue' => round($revenue, 2),
            'cost' => round($cost, 2),
            'margin' => $margin,
            'margin_pct' => $this->marginPct($revenue, $margin),
            'delivered_at' => $case->delivered_at,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function groupByPatientType(Collection $rows): array
    {
        $result = [];

        foreach ([Patient::TYPE_CIVILIAN, Patient::TYPE_MILITARY] as $type) {
            $subset = $rows->where('patient_type', $type)->values();

            if ($subset->isEmpty()) {
                continue;
            }

            $result[$type] = $this->aggregate($subset);
        }

        return $result;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function groupByCompany(Collection $rows): array
    {
        return $rows
            ->groupBy('company')
            ->map(function (Collection $group, string $company) {
                return array_merge(['company' => $company], $this->aggregate($group->values()));
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function aggregate(Collection $rows): array
    {
        $revenue = round((float) $rows->sum('revenue'), 2);
        $cost = round((float) $rows->sum('cost'), 2);
        $margin = round($revenue - $cost, 2);

        return [
            'count' => $rows->count(),
            'revenue' => $revenue,
            'cost' => $cost,
            'margin' => $margin,
            'margin_pct' => $this->marginPct($revenue, $margin),
        ];
    }

    private function marginPct(float $revenue, float $margin): float
    {
        if ($revenue <= 0.0) {
            return 0.0;
        }

        return round(($margin / $revenue) * 100, 2);
    }
}
