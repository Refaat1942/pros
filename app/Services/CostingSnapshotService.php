<?php

namespace App\Services;

use App\Models\PricingRequest;
use App\Support\CostingEngine;

/**
 * لقطة التكاليف — تحسب سعر البيع تلقائياً من البنود الحيّة وتُجمّد النتيجة على طلب التسعير.
 *
 * لا يوجد «نمط» يُختار يدوياً بعد الآن:
 *   - بنود «صنف صرف سريع» (is_quick_dispense) → 40% مباشرة بلا مكوّنات.
 *   - باقي البنود (الطرف الصناعي) → مواد + مكوّنات + 95%.
 * سعر البيع = مجموع الجزأين، ويصبح قيمة عرض السعر.
 */
class CostingSnapshotService
{
    public function __construct(
        private readonly CostingModeService $modeService,
        private readonly CostingEngine $engine,
    ) {}

    /**
     * حساب التفصيل الحالي (بدون حفظ) لطلب تسعير من بنوده الحيّة.
     *
     * @return array<string, mixed>
     */
    public function breakdown(PricingRequest $request): array
    {
        [$baseMaterials, $quickMaterials] = $this->splitMaterials($request);

        return $this->engine->calculateSplit(
            $this->modeService->limbProfile(),
            $this->modeService->quickProfile(),
            $baseMaterials,
            $quickMaterials,
        );
    }

    /**
     * إعادة احتساب اللقطة من البنود الحالية وحفظها.
     */
    public function refresh(PricingRequest $request): PricingRequest
    {
        return $this->persist($request);
    }

    private function persist(PricingRequest $request): PricingRequest
    {
        $result = $this->breakdown($request);

        $request->update([
            'costing_mode' => $result['mode_key'],
            'components_total' => $result['components_total'],
            'total_cost' => $result['total_cost'],
            'profit_rate' => $result['profit_rate'],
            'selling_price' => $result['selling_price'],
            'components_snapshot' => json_encode($result, JSON_UNESCAPED_UNICODE),
        ]);

        return $request->fresh();
    }

    /**
     * تقسيم إجمالي المواد إلى (طرف صناعي، صرف سريع) حسب علم الصنف.
     *
     * @return array{0: float, 1: float}
     */
    private function splitMaterials(PricingRequest $request): array
    {
        $request->load('items.stockItem');

        if ($request->items->isEmpty()) {
            // لا بنود مُفصّلة — نعامل الإجمالي كطرف صناعي (توافق خلفي).
            return [(float) $request->computed_total, 0.0];
        }

        $base = 0.0;
        $quick = 0.0;

        foreach ($request->items as $item) {
            $lineTotal = (float) $item->line_total;

            if ($item->stockItem?->is_quick_dispense) {
                $quick += $lineTotal;
            } else {
                $base += $lineTotal;
            }
        }

        return [round($base, 2), round($quick, 2)];
    }
}
