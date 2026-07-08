<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

/**
 * تسجيل موافقة جهة التعاقد عبر مسح QR — مسار مدني فقط.
 *
 * في الهيكلة الجديدة: مسح خطاب الموافقة في الاستقبال يُسجِّل الموافقة فقط
 * (يعلّم عرض السعر «معتمد من الجهة»)، وتبقى الحالة في مكتب التشغيل بانتظار
 * إصدار أمر الشغل من مكتب التشغيل (OperationsService::approve).
 */
class ApprovalService
{
    /**
     * معالجة مسح QR من خطاب الموافقة — يُسجِّل موافقة الجهة دون إصدار أمر الشغل.
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

        if ($case->stage_key !== CaseRecord::STAGE_OPERATIONS) {
            abort(422, 'الحالة ليست بانتظار اعتماد موافقة الجهة.');
        }

        $before = [
            'stage_key' => $case->stage_key,
            'quote_status' => $quote->status,
        ];

        DB::transaction(function () use ($quote) {
            $quote->update([
                'status' => Quote::STATUS_APPROVED,
                'status_label' => 'معتمد من الجهة',
            ]);
        });

        AuditService::log(
            action: 'scan',
            description: "مسح موافقة الجهة — {$quote->quote_no} — بانتظار إصدار أمر الشغل من مكتب التشغيل",
            tag: 'quotes',
            before: $before,
            after: [
                'stage_key' => $case->stage_key,
                'quote_status' => Quote::STATUS_APPROVED,
            ],
        );

        return $case->fresh()->load('patient');
    }
}
