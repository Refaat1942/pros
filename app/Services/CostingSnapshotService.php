<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\PricingRequest;
use App\Support\CostingEngine;

/**
 * لقطة التكاليف — تربط النمط المختار بمحرك التكاليف وتُجمّد النتيجة على طلب التسعير.
 *
 * إجمالي المواد = computed_total (أعلى سعر شراء). سعر البيع الناتج يصبح عرض السعر.
 * عند غياب النمط: selling_price = المواد (سلوك متوافق مع ما قبل الأنماط).
 */
class CostingSnapshotService
{
    public function __construct(
        private readonly CostingModeService $modeService,
        private readonly CostingEngine $engine,
    ) {}

    /**
     * حساب التفصيل الحالي (بدون حفظ) لطلب تسعير حسب نمطه المخزّن.
     *
     * @return array{mode_key:string|null, mode_label:string|null, materials_total:float, components:list<array{label:string, rate:float, amount:float}>, components_total:float, total_cost:float, profit_rate:float, profit_amount:float, selling_price:float}
     */
    public function breakdown(PricingRequest $request): array
    {
        $mode = $this->modeService->find($request->costing_mode);

        return $this->engine->calculate($mode, (float) $request->computed_total);
    }

    /**
     * إعادة احتساب اللقطة من النمط المخزّن + المواد الحالية، وحفظها.
     */
    public function refresh(PricingRequest $request): PricingRequest
    {
        return $this->persist($request, $request->costing_mode);
    }

    /**
     * ضبط نمط التكاليف لحالة في مرحلة التكاليف ثم إعادة الاحتساب والحفظ.
     */
    public function applyMode(CaseRecord $case, ?string $modeKey): PricingRequest
    {
        abort_unless($case->isInCostCalc(), 422, 'الحالة ليست في مرحلة التكاليف.');

        $request = $case->pricingRequest;
        abort_unless($request !== null, 422, 'لا يوجد طلب تسعير لهذه الحالة.');

        if ($modeKey !== null && $modeKey !== '' && $this->modeService->find($modeKey) === null) {
            abort(422, 'نمط التكاليف غير معروف.');
        }

        $before = $request->only(['costing_mode', 'selling_price']);
        $request = $this->persist($request, $modeKey);

        AuditService::log(
            action: 'update',
            description: "ضبط نمط التكاليف — {$request->request_no}",
            tag: 'pricing',
            before: $before,
            after: $request->only(['costing_mode', 'total_cost', 'profit_rate', 'selling_price']),
        );

        return $request;
    }

    private function persist(PricingRequest $request, ?string $modeKey): PricingRequest
    {
        $mode = $this->modeService->find($modeKey);
        $result = $this->engine->calculate($mode, (float) $request->computed_total);

        $request->update([
            'costing_mode' => $result['mode_key'],
            'components_total' => $result['components_total'],
            'total_cost' => $result['total_cost'],
            'profit_rate' => $result['profit_rate'],
            'selling_price' => $result['selling_price'],
            'components_snapshot' => json_encode($result['components'], JSON_UNESCAPED_UNICODE),
        ]);

        return $request->fresh();
    }
}
