<?php

namespace App\Services;

use App\Models\ApprovalContract;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\Quote;
use App\Support\QuotePrintPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * معالجة خطاب الموافقة — رفع + مراجعة يدوية + تسجيل موافقة الجهة.
 */
class OcrApprovalService
{
    public function __construct(private readonly ApprovalService $approvalService) {}

    /**
     * @param  array{
     *   quote_no: string,
     *   patient_name?: string,
     *   approved_amount?: float|int|string,
     *   company_name?: string,
     *   letter_ref?: string,
     *   letter_date?: string,
     *   letter_path?: string,
     * }  $extracted
     */
    public function process(array $extracted): CaseRecord
    {
        $quote = Quote::with(['caseRecord.patient'])
            ->where('quote_no', $extracted['quote_no'])
            ->first();

        if (! $quote || ! $quote->caseRecord) {
            abort(422, 'عرض السعر غير موجود.');
        }

        $case = $quote->caseRecord;

        if ($case->patient_type === Patient::TYPE_MILITARY) {
            abort(422, 'المسار العسكري لا يتطلب خطاب موافقة.');
        }

        if (! $this->caseAwaitingEntityApproval($case)) {
            abort(422, 'الحالة ليست بانتظار اعتماد موافقة الجهة.');
        }

        if ($quote->status !== Quote::STATUS_ISSUED) {
            abort(422, 'يجب إصدار العرض للجهة قبل معالجة خطاب الموافقة.');
        }

        // H-2: لا نثق بمسار الملف القادم من العميل — نقبله فقط إن كان خطاب موافقة
        // فعلياً مرفوعاً تحت approval_letters/ على القرص الخاص. غير ذلك نتجاهله
        // (نمنع إرفاق أي ملف آخر داخل جذر التخزين بحالة عقد اعتماد).
        $extracted['letter_path'] = $this->sanitizeLetterPath($extracted['letter_path'] ?? null);

        AuditService::log(
            action: 'approval_letter',
            description: "موافقة جهة — {$quote->quote_no}",
            tag: 'quotes',
            after: [
                'quote_no' => $quote->quote_no,
                'patient_name' => $extracted['patient_name'] ?? null,
                'approved_amount' => $extracted['approved_amount'] ?? null,
                'company_name' => $extracted['company_name'] ?? null,
                'letter_path' => $extracted['letter_path'] ?? null,
            ],
        );

        $case = $this->approvalService->confirm($case, $quote->quote_no);

        $this->archiveContract($case, $quote, $extracted);

        return $case;
    }

    private function archiveContract(CaseRecord $case, Quote $quote, array $extracted): void
    {
        // معاملة حتى يصبح قفل ترقيم العقد فعّالاً ويمنع تكرار contract_no.
        DB::transaction(function () use ($case, $quote, $extracted) {
            $year = now()->year;
            $prefix = "CNT-{$year}-";

            $last = ApprovalContract::where('contract_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('contract_no')
                ->value('contract_no');

            $num = $last
                ? ((int) substr($last, strlen($prefix)) + 1)
                : 1;

            ApprovalContract::create([
                'contract_no' => sprintf('%s%04d', $prefix, $num),
                'case_id' => $case->id,
                'quote_id' => $quote->id,
                'patient_name' => $extracted['patient_name'] ?? $quote->patient_name,
                'company_name' => $extracted['company_name'] ?? $quote->company_name,
                'approved_amount' => $extracted['approved_amount'] ?? QuotePrintPresenter::approvedAmount($quote),
                'approval_date' => now()->toDateString(),
                'work_order_no' => $case->work_order_no,
                'letter_path' => $extracted['letter_path'] ?? null,
                'letter_ref' => $extracted['letter_ref'] ?? null,
                'letter_date' => $extracted['letter_date'] ?? null,
            ]);
        });
    }

    /**
     * H-2: يتحقق أن مسار الخطاب مسار خطاب موافقة مشروع (approval_letters/ على القرص
     * الخاص) — يمنع الوصول لملفات أخرى داخل جذر التخزين عبر مسار يتحكم فيه العميل.
     * يعيد المسار إن كان صالحاً، وإلا null (يُؤرشَف العقد بلا خطاب مرفق بدل رفض العملية).
     */
    private function sanitizeLetterPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        // منع أي تجاوز مسار أو مسار مطلق — يجب أن يبدأ حصراً بـ approval_letters/.
        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if (! str_starts_with($normalized, 'approval_letters/')
            || str_contains($normalized, '..')) {
            return null;
        }

        // يجب أن يكون الملف موجوداً فعلاً على القرص الخاص (حيث تُرفع الخطابات).
        if (! Storage::disk('local')->exists($normalized)) {
            return null;
        }

        return $normalized;
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
