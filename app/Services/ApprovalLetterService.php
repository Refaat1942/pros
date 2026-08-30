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
 * خطاب موافقة الجهة — رفع أرشيف + إدخال يدوي + تسجيل الموافقة.
 */
class ApprovalLetterService
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
     * }  $payload
     */
    public function confirm(array $payload): CaseRecord
    {
        $quote = Quote::with(['caseRecord.patient'])
            ->where('quote_no', $payload['quote_no'])
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
            if ($quote->status === Quote::STATUS_APPROVED) {
                return $case->fresh()->load('patient');
            }

            abort(422, 'يجب إصدار العرض للجهة قبل تسجيل خطاب الموافقة.');
        }

        $payload['letter_path'] = $this->sanitizeLetterPath($payload['letter_path'] ?? null);

        AuditService::log(
            action: 'approval_letter',
            description: "موافقة جهة — {$quote->quote_no}",
            tag: 'quotes',
            after: [
                'quote_no' => $quote->quote_no,
                'patient_name' => $payload['patient_name'] ?? null,
                'approved_amount' => $payload['approved_amount'] ?? null,
                'company_name' => $payload['company_name'] ?? null,
                'letter_path' => $payload['letter_path'] ?? null,
            ],
        );

        $case = $this->approvalService->confirm($case, $quote->quote_no);

        $this->archiveContract($case, $quote, $payload);

        return $case;
    }

    private function archiveContract(CaseRecord $case, Quote $quote, array $payload): void
    {
        DB::transaction(function () use ($case, $quote, $payload) {
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
                'patient_name' => $payload['patient_name'] ?? $quote->patient_name,
                'company_name' => $payload['company_name'] ?? $quote->company_name,
                'approved_amount' => $payload['approved_amount'] ?? QuotePrintPresenter::approvedAmount($quote),
                'approval_date' => now()->toDateString(),
                'work_order_no' => $case->work_order_no,
                'letter_path' => $payload['letter_path'] ?? null,
                'letter_ref' => $payload['letter_ref'] ?? null,
                'letter_date' => $payload['letter_date'] ?? null,
            ]);
        });
    }

    private function sanitizeLetterPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if (! str_starts_with($normalized, 'approval_letters/')
            || str_contains($normalized, '..')) {
            return null;
        }

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
