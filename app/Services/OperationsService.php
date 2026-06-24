<?php

namespace App\Services;

use App\Enums\PricingRequestStatus;
use App\Enums\WorkflowEvent;
use App\Exceptions\InsufficientStockException;
use App\Models\CaseRecord;
use App\Models\PricingRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * مكتب التشغيل (الخطوة 7) — مركز القرار المركزي.
 *
 * تهبط هنا عروض الأسعار والموافقات المعلّقة بعد محرك التكاليف.
 *   - اعتماد  → حجز فوري للمواد + توليد أمر شغل + تحويل للمخزن (المرحلة 8).
 *   - رفض/تعديل → إعادة الحالة للمعدلات أو للتوصيف.
 *
 * المسار العسكري يمر بنفس المراحل لكن باعتماد صامت تلقائي (بدون بوابة مالية بشرية).
 */
class OperationsService
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly WorkOrderService $workOrderService,
        private readonly BomService $bomService,
    ) {
    }

    /**
     * اعتماد الحالة من مكتب التشغيل — حجز فوري وتحويل للمخزن للصرف.
     */
    public function approve(CaseRecord $case, ?string $approvedBy = null): CaseRecord
    {
        try {
            return $this->doApprove($case, $approvedBy);
        } catch (InsufficientStockException $e) {
            // يعمل خارج الـ transaction المُلغاة — هذا الحفظ ينجح.
            if ($e->pricingRequestId) {
                PricingRequest::where('id', $e->pricingRequestId)
                    ->update(['status_key' => PricingRequestStatus::Insufficient->value]);

                AuditService::log(
                    action:      'insufficient',
                    description: "فشل حجز المخزون عند اعتماد التشغيل — الصنف: {$e->stockItemCode}",
                    tag:         'warehouse',
                    after:       [
                        'pricing_request_id' => $e->pricingRequestId,
                        'missing_code'       => $e->stockItemCode,
                        'available'          => $e->available,
                        'required'           => $e->required,
                    ],
                );
            }

            abort(422, $e->getMessage());
        }
    }

    private function doApprove(CaseRecord $case, ?string $approvedBy): CaseRecord
    {
        return DB::transaction(function () use ($case, $approvedBy) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            if ($case->stage_key !== CaseRecord::STAGE_OPERATIONS) {
                abort(422, 'الحالة ليست في مكتب التشغيل — لا يمكن الاعتماد.');
            }

            $before = ['stage_key' => $case->stage_key];

            // الحجز الفوري في سجل المخزون (Reserved) — قبل أي تحويل.
            $this->bomService->reserveForCase($case);

            // توليد أمر الشغل (WO-*) إن لم يوجد.
            $this->workOrderService->generate($case->fresh());

            // اعتماد طلب التسعير المرتبط (انتقال الحالة المالية).
            if ($case->pricing_request_id) {
                PricingRequest::where('id', $case->pricing_request_id)->update([
                    'approved_at'  => now(),
                    'approved_by'  => $approvedBy ?? 'مكتب التشغيل',
                    'step'         => PricingRequest::STEP_QUOTE_READY,
                    'status_key'   => PricingRequestStatus::SentToReception->value,
                ]);
            }

            // عسكري: التكلفة المعتمدة = التكلفة الداخلية (WAC) للمديونية السيادية.
            if ($case->isMilitary()) {
                $case->update(['total_cost' => (float) $case->internal_cost]);
            } else {
                $case->update([
                    'approval_date'         => now()->toDateString(),
                    'approval_confirmed_at' => now(),
                ]);
            }

            $this->workflowService->advance($case->fresh(), WorkflowEvent::OperationsApproved->value);

            AuditService::log(
                action:      'approve',
                description: "اعتماد مكتب التشغيل — {$case->case_no} — تحويل للمخزن",
                tag:         'operations',
                before:      $before,
                after:       [
                    'stage_key'           => CaseRecord::STAGE_MANUFACTURING,
                    'manufacturing_stage' => CaseRecord::MFG_WAREHOUSE,
                    'work_order_no'       => $case->fresh()->work_order_no,
                    'approved_by'         => $approvedBy ?? 'مكتب التشغيل',
                ],
            );

            return $case->fresh()->load('patient');
        });
    }

    /**
     * رفض/طلب تعديل من مكتب التشغيل — إعادة للمعدلات أو للتوصيف.
     */
    public function returnForRework(CaseRecord $case, string $target, ?string $reason = null): CaseRecord
    {
        $event = match ($target) {
            CaseRecord::STAGE_TECHNICAL   => WorkflowEvent::ReturnedToTechnical->value,
            CaseRecord::STAGE_ADJUSTMENTS => WorkflowEvent::ReturnedToAdjustments->value,
            default                       => abort(422, 'وجهة الإعادة غير صالحة.'),
        };

        return DB::transaction(function () use ($case, $event, $target, $reason) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            if ($case->stage_key !== CaseRecord::STAGE_OPERATIONS) {
                abort(422, 'الحالة ليست في مكتب التشغيل — لا يمكن الإعادة.');
            }

            $before = ['stage_key' => $case->stage_key];

            $this->workflowService->advance($case, $event);

            CaseRecord::where('id', $case->id)->update([
                'rework_reason'      => $reason,
                'rework_target'      => $target,
                'rework_returned_at' => now(),
                'rework_returned_by' => Auth::user()?->name ?? 'مكتب التشغيل',
            ]);

            if ($target === CaseRecord::STAGE_TECHNICAL) {
                app(SpecService::class)->reopenForRework($case->fresh());
            }

            AuditService::log(
                action:      'return',
                description: "إعادة من مكتب التشغيل إلى {$target} — {$case->case_no}",
                tag:         'operations',
                before:      $before,
                after:       ['stage_key' => $target, 'reason' => $reason],
            );

            return $case->fresh()->load('patient');
        });
    }
}
