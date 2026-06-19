<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

/**
 * تأكيد موافقة جهة التعاقد عبر مسح QR — مسار مدني فقط.
 */
class ApprovalService
{
    public function __construct(
        private readonly WorkOrderService $workOrderService,
        private readonly WorkflowService $workflowService,
    ) {
    }

    /**
     * معالجة مسح QR من خطاب الموافقة — يُفتح مسار التصنيع.
     */
    public function confirm(CaseRecord $case, string $scannedQr): CaseRecord
    {
        return DB::transaction(function () use ($case, $scannedQr) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            if ($case->patient_type === Patient::TYPE_MILITARY) {
                abort(422, 'المسار العسكري لا يتطلب موافقة عرض سعر.');
            }

            if ($case->stage_key !== CaseRecord::STAGE_WAITING_RETURN) {
                abort(422, 'الحالة ليست في مرحلة انتظار رجوع الموافقة.');
            }

            $quote = Quote::where('case_id', $case->id)->firstOrFail();

            if (! hash_equals($quote->quote_no, $scannedQr)) {
                abort(422, 'رمز QR لا يطابق عرض السعر المرتبط بهذه الحالة.');
            }

            if ($quote->status !== Quote::STATUS_ISSUED) {
                abort(422, 'يجب إصدار العرض للجهة قبل مسح الموافقة.');
            }

            $before = [
                'stage_key'             => $case->stage_key,
                'approval_date'         => $case->approval_date?->toDateString(),
                'approval_confirmed_at' => $case->approval_confirmed_at?->toIso8601String(),
                'work_order_no'         => $case->work_order_no,
                'quote_status'          => $quote->status,
            ];

            $case->update([
                'approval_date'         => now()->toDateString(),
                'approval_confirmed_at' => now(),
            ]);

            $workOrderNo = $this->workOrderService->generate($case->fresh());

            $quote->update([
                'status'       => Quote::STATUS_APPROVED,
                'status_label' => 'معتمد من الجهة',
            ]);

            $this->workflowService->advance($case->fresh(), WorkflowEvent::ApprovalScanned->value);

            AuditService::log(
                action:      'scan',
                description: "مسح موافقة الجهة — {$quote->quote_no} — {$workOrderNo}",
                tag:         'quotes',
                before:      $before,
                after:       [
                    'stage_key'             => CaseRecord::STAGE_MANUFACTURING,
                    'approval_confirmed_at' => now()->toIso8601String(),
                    'work_order_no'         => $workOrderNo,
                    'quote_status'          => Quote::STATUS_APPROVED,
                ],
            );

            return $case->fresh()->load('patient');
        });
    }
}
