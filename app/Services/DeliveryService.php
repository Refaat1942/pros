<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Exceptions\DeliveryNotReadyException;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Support\CaseFinancialSummary;
use Illuminate\Support\Facades\DB;

/**
 * إغلاق الحالة بالتسليم — فاتورة + أرشفة + ترحيل مالي.
 */
class DeliveryService
{
    public function __construct(
        private readonly BomService $bomService,
        private readonly WorkflowService $workflowService,
        private readonly FinancialPostingService $financialPostingService,
        private readonly InvoiceService $invoiceService,
        private readonly PatientArchiveService $patientArchiveService,
    ) {}

    public function canDeliver(CaseRecord $case): bool
    {
        return $this->bomService->canDeliver($case);
    }

    /**
     * إغلاق الحالة بعد تأكيد التسليم من الاستقبال — حالة مغلقة (delivered).
     */
    public function close(CaseRecord $case): CaseRecord
    {
        return DB::transaction(function () use ($case) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            if (! $this->canDeliver($case)) {
                throw DeliveryNotReadyException::withReason($this->notReadyReason($case));
            }

            $case->load('patient');

            $before = [
                'stage_key' => $case->stage_key,
                'delivered_at' => $case->delivered_at?->toDateString(),
            ];

            $this->workflowService->advance($case->fresh(), WorkflowEvent::Delivered->value);

            $case->refresh();

            $invoice = $this->invoiceService->issueFinalInvoice($case->fresh());

            $this->financialPostingService->post($case->fresh());

            $this->patientArchiveService->archiveOnDelivery($case->patient);

            CaseFinancialSummary::syncOnDelivery($case->fresh());

            $case->refresh();

            AuditService::log(
                action: 'deliver',
                description: 'تسليم الطرف للمريض — إغلاق الحالة',
                tag: 'delivery',
                before: $before,
                after: [
                    'stage_key' => $case->stage_key,
                    'delivered_at' => $case->delivered_at?->toDateString(),
                    'invoice_no' => $invoice['invoice_no'],
                    'invoice_total' => $invoice['invoice_total'],
                ],
            );

            return $case->fresh()->load([
                'patient:id,patient_code,name,status,archived_at',
                'bom:id,bom_no,stage',
            ]);
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

        if ($case->bom->stage !== Bom::STAGE_FINISHED) {
            return 'BOM لم تُغلَق بعد — التصنيع غير مكتمل.';
        }

        return 'الحالة غير جاهزة للتسليم.';
    }
}
