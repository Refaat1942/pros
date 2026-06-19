<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Exceptions\DeliveryNotReadyException;
use App\Models\CaseRecord;
use Illuminate\Support\Facades\DB;

/**
 * إغلاق الحالة بالتسليم — مسح QR + ترحيل مالي.
 */
class DeliveryService
{
    public function __construct(
        private readonly BomService $bomService,
        private readonly PatientQrService $patientQrService,
        private readonly WorkflowService $workflowService,
        private readonly FinancialPostingService $financialPostingService,
    ) {
    }

    /**
     * يتحقق من جاهزية التسليم — يفوِّض إلى BomService.
     */
    public function canDeliver(CaseRecord $case): bool
    {
        return $this->bomService->canDeliver($case);
    }

    /**
     * إغلاق الحالة بعد مسح بطاقة المريض.
     */
    public function close(CaseRecord $case, string $scannedQr): CaseRecord
    {
        return DB::transaction(function () use ($case, $scannedQr) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            if (! $this->canDeliver($case)) {
                throw DeliveryNotReadyException::withReason($this->notReadyReason($case));
            }

            $case->load('patient');

            if (! $case->patient || ! $this->patientQrService->validate($scannedQr, $case->patient)) {
                abort(422, 'رمز QR لا يطابق بطاقة المريض.');
            }

            $before = [
                'stage_key'    => $case->stage_key,
                'delivered_at' => $case->delivered_at?->toDateString(),
            ];

            $this->workflowService->advance($case->fresh(), WorkflowEvent::Delivered->value);

            $case->refresh();

            $this->financialPostingService->post($case);

            AuditService::log(
                action:      'deliver',
                description: 'تسليم الطرف للمريض',
                tag:         'delivery',
                before:      $before,
                after:       [
                    'stage_key'    => $case->stage_key,
                    'delivered_at' => $case->delivered_at?->toDateString(),
                ],
            );

            return $case->fresh()->load(['patient:id,patient_code,name', 'bom:id,bom_no,stage']);
        });
    }

    private function notReadyReason(CaseRecord $case): string
    {
        $case->loadMissing('bom');

        if ($case->stage_key !== CaseRecord::STAGE_READY_DELIVERY) {
            return 'الحالة ليست في مرحلة جاهز للتسليم.';
        }

        if (! $case->bom) {
            return 'لا توجد قائمة مواد تشغيل مرتبطة بالحالة.';
        }

        if ($case->bom->stage !== \App\Models\Bom::STAGE_FINISHED) {
            return 'BOM لم تُغلَق بعد — التصنيع غير مكتمل.';
        }

        return 'الحالة غير جاهزة للتسليم.';
    }
}
