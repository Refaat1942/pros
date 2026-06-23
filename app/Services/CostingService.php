<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use Illuminate\Support\Facades\DB;

/**
 * محرك التكاليف (الخطوة 5) — توقف صريح عند cost_calc للمراجعة اليدوية.
 *
 * بعد إغلاق المعدلات تُحتسب التكلفة وتتوقف الحالة هنا.
 * تأكيد التكاليف يُصدر عرض السعر (مدني) ويُحوّل لمكتب التشغيل.
 */
class CostingService
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly PricingService $pricingService,
        private readonly QuoteService $quoteService,
        private readonly OperationsService $operationsService,
        private readonly MilitaryMarkupService $militaryMarkupService,
    ) {
    }

    /**
     * استقبال الحالة من المعدلات — احتساب التكلفة والتوقف عند cost_calc.
     */
    public function receiveFromAdjustments(CaseRecord $case): CaseRecord
    {
        if ($case->stage_key !== CaseRecord::STAGE_ADJUSTMENTS) {
            abort(422, 'الحالة ليست في مرحلة المعدلات.');
        }

        DB::transaction(function () use ($case) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $this->workflowService->advance($case, WorkflowEvent::AdjustmentsCompleted->value);

            $pricingRequest = $this->pricingService->createAndCalculateForCase($case->fresh());

            CaseRecord::where('id', $case->id)->update([
                'internal_cost' => (float) $pricingRequest->internal_total,
            ]);

            $this->militaryMarkupService->apply($case->fresh());

            AuditService::log(
                action:      'receive',
                description: "استلام التكاليف — {$case->case_no} — بانتظار التأكيد",
                tag:         'pricing',
                after:       [
                    'case_id'        => $case->id,
                    'pricing_request'=> $pricingRequest->request_no,
                    'computed_total' => $pricingRequest->computed_total,
                    'internal_total' => $pricingRequest->internal_total,
                    'stage_key'      => CaseRecord::STAGE_COST_CALC,
                ],
            );
        });

        return $case->fresh()->load(['patient', 'pricingRequest.items', 'bom.items']);
    }

    /**
     * تأكيد التكاليف وإصدار عرض السعر — cost_calc → quote → operations.
     * المسار العسكري: بدون عرض سعر، اعتماد صامت تلقائي → المخزن.
     */
    public function confirmAndIssueQuote(CaseRecord $case, ?string $confirmedBy = null): CaseRecord
    {
        if ($case->stage_key !== CaseRecord::STAGE_COST_CALC) {
            abort(422, 'الحالة ليست في مرحلة التكاليف — لا يمكن التأكيد.');
        }

        DB::transaction(function () use ($case, $confirmedBy) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $pricingRequest = $case->pricingRequest
                ?? $this->pricingService->createAndCalculateForCase($case);

            $this->workflowService->advance($case->fresh(), WorkflowEvent::CostingCompleted->value);

            if (! $case->fresh()->isMilitary()) {
                $this->quoteService->issue($pricingRequest, (float) $pricingRequest->computed_total);
            }

            $this->workflowService->advance($case->fresh(), WorkflowEvent::QuoteIssued->value);

            AuditService::log(
                action:      'confirm',
                description: "تأكيد التكاليف وإصدار عرض السعر — {$case->case_no}",
                tag:         'pricing',
                after:       [
                    'case_id'      => $case->id,
                    'confirmed_by' => $confirmedBy ?? 'مكتب التشغيل',
                    'stage_key'    => CaseRecord::STAGE_OPERATIONS,
                ],
            );
        });

        $fresh = $case->fresh();

        if ($fresh->isMilitary()) {
            $fresh = $this->operationsService->approve($fresh, 'النظام — اعتماد تلقائي عسكري');
        }

        return $fresh->load(['patient', 'pricingRequest.items', 'quotes']);
    }
}
