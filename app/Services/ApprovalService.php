<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

/**
 * تأكيد موافقة جهة التعاقد عبر مسح QR — مسار مدني فقط.
 *
 * في الهيكلة الجديدة تهبط الموافقات في مكتب التشغيل (الخطوة 7)؛ مسح QR هنا هو
 * أحد محفّزات اعتماد مكتب التشغيل ويُفوّض الحجز/التحويل لـ OperationsService.
 */
class ApprovalService
{
    public function __construct(
        private readonly OperationsService $operationsService,
    ) {
    }

    /**
     * معالجة مسح QR من خطاب الموافقة — يعتمد الحالة في مكتب التشغيل ويفتح الصرف.
     */
    public function confirm(CaseRecord $case, string $scannedQr): CaseRecord
    {
        $case = CaseRecord::findOrFail($case->id);

        if ($case->patient_type === Patient::TYPE_MILITARY) {
            abort(422, 'المسار العسكري لا يتطلب موافقة عرض سعر.');
        }

        $quote = Quote::where('case_id', $case->id)->firstOrFail();

        if (! hash_equals($quote->quote_no, $scannedQr)) {
            abort(422, 'رمز QR لا يطابق عرض السعر المرتبط بهذه الحالة.');
        }

        if ($quote->status !== Quote::STATUS_ISSUED) {
            abort(422, 'يجب إصدار العرض للجهة قبل مسح الموافقة.');
        }

        if (! $this->caseAwaitingEntityApproval($case)) {
            abort(422, 'الحالة ليست بانتظار اعتماد موافقة الجهة.');
        }

        $before = [
            'stage_key'    => $case->stage_key,
            'quote_status' => $quote->status,
        ];

        // إن لم يُعتمد الصرف بعد (مسار قديم) — حجز فوري + أمر شغل + تحويل للمخزن.
        if ($case->stage_key === CaseRecord::STAGE_OPERATIONS) {
            $case = $this->operationsService->approve($case, 'موافقة الجهة (QR)');
        }

        DB::transaction(function () use ($quote) {
            $quote->update([
                'status'       => Quote::STATUS_APPROVED,
                'status_label' => 'معتمد من الجهة',
            ]);
        });

        AuditService::log(
            action:      'scan',
            description: "مسح موافقة الجهة — {$quote->quote_no} — {$case->work_order_no}",
            tag:         'quotes',
            before:      $before,
            after:       [
                'stage_key'     => $case->stage_key,
                'work_order_no' => $case->work_order_no,
                'quote_status'  => Quote::STATUS_APPROVED,
            ],
        );

        return $case->fresh()->load('patient');
    }

    private function caseAwaitingEntityApproval(CaseRecord $case): bool
    {
        if ($case->stage_key === CaseRecord::STAGE_OPERATIONS) {
            return true;
        }

        return $case->stage_key === CaseRecord::STAGE_MANUFACTURING
            && $case->manufacturing_stage === CaseRecord::MFG_WAREHOUSE;
    }
}
