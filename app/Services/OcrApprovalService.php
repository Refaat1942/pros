<?php

namespace App\Services;

use App\Exceptions\OcrMismatchException;
use App\Models\ApprovalContract;
use App\Models\CaseRecord;
use App\Models\Quote;

/**
 * معالجة خطاب الموافقة — استخراج OCR ومطابقة عرض السعر المجمّد.
 */
class OcrApprovalService
{
    public function __construct(private readonly ApprovalService $approvalService)
    {
    }

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

        if ($case->patient_type === \App\Models\Patient::TYPE_MILITARY) {
            abort(422, 'المسار العسكري لا يتطلب خطاب موافقة OCR.');
        }

        if ($case->stage_key !== CaseRecord::STAGE_WAITING_RETURN) {
            abort(422, 'الحالة ليست في مرحلة انتظار رجوع الموافقة.');
        }

        if ($quote->status !== Quote::STATUS_ISSUED) {
            abort(422, 'يجب إصدار العرض للجهة قبل معالجة خطاب الموافقة.');
        }

        $this->assertOcrMatchesQuote($quote, $extracted);

        AuditService::log(
            action:      'ocr',
            description: "OCR مطابق — {$quote->quote_no}",
            tag:         'quotes',
            after:       [
                'quote_no'        => $quote->quote_no,
                'patient_name'    => $extracted['patient_name'] ?? null,
                'approved_amount' => $extracted['approved_amount'] ?? null,
                'company_name'    => $extracted['company_name'] ?? null,
                'letter_path'     => $extracted['letter_path'] ?? null,
            ],
        );

        $case = $this->approvalService->confirm($case, $quote->quote_no);

        $this->archiveContract($case, $quote, $extracted);

        return $case;
    }

    private function archiveContract(CaseRecord $case, Quote $quote, array $extracted): void
    {
        $year   = now()->year;
        $prefix = "CNT-{$year}-";

        $last = ApprovalContract::where('contract_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('contract_no')
            ->value('contract_no');

        $num = $last
            ? ((int) substr($last, strlen($prefix)) + 1)
            : 1;

        ApprovalContract::create([
            'contract_no'     => sprintf('%s%04d', $prefix, $num),
            'case_id'         => $case->id,
            'quote_id'        => $quote->id,
            'patient_name'    => $extracted['patient_name'] ?? $quote->patient_name,
            'company_name'    => $extracted['company_name'] ?? $quote->company_name,
            'approved_amount' => $extracted['approved_amount'] ?? $quote->total,
            'approval_date'   => now()->toDateString(),
            'work_order_no'   => $case->work_order_no,
            'letter_path'     => $extracted['letter_path'] ?? null,
            'letter_ref'      => $extracted['letter_ref'] ?? null,
            'letter_date'     => $extracted['letter_date'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    private function assertOcrMatchesQuote(Quote $quote, array $extracted): void
    {
        $case    = $quote->caseRecord;
        $patient = $case->patient;

        if ($patient && ! empty($extracted['patient_name'])) {
            if (! $this->textMatches((string) $extracted['patient_name'], $patient->name)) {
                throw OcrMismatchException::forField('اسم المريض', 'لا يطابق ملف المريض');
            }
        }

        if (isset($extracted['approved_amount'])) {
            $ocrAmount      = round((float) $extracted['approved_amount'], 2);
            $expectedAmount = round((float) $quote->total, 2);

            if (abs($ocrAmount - $expectedAmount) >= 0.01) {
                throw OcrMismatchException::forField(
                    'القيمة المالية',
                    "المستخرج {$ocrAmount} ≠ عرض السعر {$expectedAmount}"
                );
            }
        }

        if (! empty($extracted['company_name']) && $case->company_name) {
            if (! $this->textMatches((string) $extracted['company_name'], $case->company_name)) {
                throw OcrMismatchException::forField('جهة التعاقد', 'لا تطابق السجل');
            }
        }
    }

    private function textMatches(string $a, string $b): bool
    {
        $normalize = static fn (string $s) => preg_replace('/\s+/u', '', mb_strtolower(trim($s)));

        return $normalize($a) === $normalize($b);
    }
}
