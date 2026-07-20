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
        private readonly SpecEditRequestService $editRequestService,
        private readonly CostingSnapshotService $snapshotService,
    ) {}

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

            // تجميد لقطة التكاليف الأولية (بلا نمط بعد → سعر البيع = المواد).
            $pricingRequest = $this->snapshotService->refresh($pricingRequest);

            CaseRecord::where('id', $case->id)->update([
                'internal_cost' => (float) $pricingRequest->internal_total,
            ]);

            $this->militaryMarkupService->apply($case->fresh());

            AuditService::log(
                action: 'receive',
                description: "استلام التكاليف — {$case->case_no} — بانتظار التأكيد",
                tag: 'pricing',
                after: [
                    'case_id' => $case->id,
                    'pricing_request' => $pricingRequest->request_no,
                    'computed_total' => $pricingRequest->computed_total,
                    'internal_total' => $pricingRequest->internal_total,
                    'stage_key' => CaseRecord::STAGE_COST_CALC,
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

            $this->editRequestService->assertNoPendingForCase($case);

            $pricingRequest = $case->pricingRequest
                ?? $this->pricingService->createAndCalculateForCase($case);

            if ($case->bom && $pricingRequest) {
                $this->pricingService->syncItemsFromBom($case, $pricingRequest);
                $this->pricingService->refreshLinePrices($pricingRequest);
                $pricingRequest->refresh();
            }

            // إعادة تجميد اللقطة على المواد المحدّثة ثم اعتماد سعر البيع كقيمة العرض.
            $pricingRequest = $this->snapshotService->refresh($pricingRequest);
            $quoteAmount = (float) $pricingRequest->selling_price > 0
                ? (float) $pricingRequest->selling_price
                : (float) $pricingRequest->computed_total;

            $case = $case->fresh()->load('patient');

            if ($case->needsServicesApproval()) {
                $this->workflowService->advance($case, WorkflowEvent::ServicesApprovalRequired->value);
                app(ServicesApprovalService::class)->openForCase($case);

                AuditService::log(
                    action: 'confirm',
                    description: "تأكيد التكاليف — بانتظار تصديق إدارة الخدمات — {$case->case_no}",
                    tag: 'pricing',
                    after: [
                        'case_id' => $case->id,
                        'confirmed_by' => $confirmedBy ?? 'مكتب التكاليف',
                        'stage_key' => CaseRecord::STAGE_SERVICES_APPROVAL,
                    ],
                );

                return;
            }

            $this->workflowService->advance($case, WorkflowEvent::CostingCompleted->value);

            if (! $case->isMilitary()) {
                $this->quoteService->issue($pricingRequest, $quoteAmount);
            }

            $this->workflowService->advance($case->fresh(), WorkflowEvent::QuoteIssued->value);

            AuditService::log(
                action: 'confirm',
                description: "تأكيد التكاليف وإصدار عرض السعر — {$case->case_no}",
                tag: 'pricing',
                after: [
                    'case_id' => $case->id,
                    'confirmed_by' => $confirmedBy ?? 'مكتب التشغيل',
                    'stage_key' => CaseRecord::STAGE_OPERATIONS,
                ],
            );
        });

        $fresh = $case->fresh();

        if ($fresh->isMilitary() && $fresh->stage_key === CaseRecord::STAGE_OPERATIONS) {
            $fresh = $this->operationsService->approve($fresh, 'النظام — اعتماد تلقائي عسكري');
        }

        return $fresh->load(['patient', 'pricingRequest.items', 'quotes']);
    }
}
