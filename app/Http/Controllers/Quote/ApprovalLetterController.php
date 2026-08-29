<?php

namespace App\Http\Controllers\Quote;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quote\ProcessApprovalLetterRequest;
use App\Models\Patient;
use App\Models\Quote;
use App\Services\ApprovalLetterService;
use App\Support\QuotePrintPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * رفع خطاب موافقة الجهة وإدخال البيانات يدوياً (بدون OCR).
 */
class ApprovalLetterController extends Controller
{
    public function __construct(private readonly ApprovalLetterService $approvalLetterService) {}

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'quote_no' => ['required', 'string', 'max:50'],
            'letter_file' => [
                'required',
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $mime = strtolower($value->getMimeType() ?? '');
                    if (str_starts_with($mime, 'image/') || str_contains($mime, 'pdf')) {
                        return;
                    }

                    $ext = strtolower($value->getClientOriginalExtension());
                    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tif', 'tiff', 'heic', 'heif', 'avif', 'ico'];

                    if (in_array($ext, $imageExts, true) || $ext === 'pdf') {
                        return;
                    }

                    $fail('نوع الملف غير مدعوم. يُرجى رفع صورة بأي صيغة مدعومة أو PDF.');
                },
            ],
        ]);

        $quote = Quote::with(['caseRecord.patient', 'caseRecord.contractCompany'])
            ->where('quote_no', $request->input('quote_no'))
            ->first();

        if (! $quote || ! $quote->caseRecord) {
            return response()->json(['message' => 'عرض السعر غير موجود.'], 422);
        }

        if ($quote->caseRecord->patient_type === Patient::TYPE_MILITARY) {
            return response()->json(['message' => 'المسار العسكري لا يتطلب خطاب موافقة.'], 422);
        }

        if ($quote->status === Quote::STATUS_APPROVED) {
            return response()->json([
                'message' => 'تم اعتماد هذا العرض مسبقاً — بانتظار إصدار أمر الشغل من مكتب التشغيل.',
                'defaults' => $this->approvalLetterDefaults($quote),
                'hints' => $this->approvalLetterHints($quote),
                'quote' => $this->approvalLetterQuoteMeta($quote),
                'already_approved' => true,
            ]);
        }

        if ($quote->status !== Quote::STATUS_ISSUED) {
            return response()->json(['message' => 'يجب أن يكون العرض صادراً للجهة قبل رفع خطاب الموافقة.'], 422);
        }

        $file = $request->file('letter_file');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('approval_letters', $filename, 'local');

        return response()->json([
            'stored_path' => $path,
            'defaults' => $this->approvalLetterDefaults($quote),
            'hints' => $this->approvalLetterHints($quote),
            'quote' => $this->approvalLetterQuoteMeta($quote),
        ]);
    }

    /**
     * بيانات افتراضية لاعتماد الجهة بدون رفع خطاب — تخطي خطاب الموافقة.
     */
    public function defaults(Request $request): JsonResponse
    {
        $request->validate([
            'quote_no' => ['required', 'string', 'max:50'],
        ]);

        $quote = Quote::with(['caseRecord.patient', 'caseRecord.contractCompany'])
            ->where('quote_no', $request->input('quote_no'))
            ->first();

        if (! $quote || ! $quote->caseRecord) {
            return response()->json(['message' => 'عرض السعر غير موجود.'], 422);
        }

        if ($quote->caseRecord->patient_type === Patient::TYPE_MILITARY) {
            return response()->json(['message' => 'المسار العسكري لا يتطلب خطاب موافقة.'], 422);
        }

        if ($quote->status === Quote::STATUS_APPROVED) {
            return response()->json([
                'message' => 'تم اعتماد هذا العرض مسبقاً — بانتظار إصدار أمر الشغل من مكتب التشغيل.',
                'defaults' => $this->approvalLetterDefaults($quote),
                'hints' => $this->approvalLetterHints($quote),
                'quote' => $this->approvalLetterQuoteMeta($quote),
                'already_approved' => true,
            ]);
        }

        if ($quote->status !== Quote::STATUS_ISSUED) {
            return response()->json(['message' => 'يجب أن يكون العرض صادراً للجهة قبل تسجيل الموافقة.'], 422);
        }

        return response()->json([
            'defaults' => $this->approvalLetterDefaults($quote),
            'hints' => $this->approvalLetterHints($quote),
            'quote' => $this->approvalLetterQuoteMeta($quote),
        ]);
    }

    /** @return array{patient_name: string, approved_amount: float, company_name: string, letter_ref: null, letter_date: null} */
    private function approvalLetterDefaults(Quote $quote): array
    {
        $case = $quote->caseRecord;
        $patient = $case?->patient;

        return [
            'patient_name' => $patient?->name ?? $quote->patient_name ?? '',
            'approved_amount' => QuotePrintPresenter::approvedAmount($quote),
            'company_name' => $case->company_name ?? $quote->company_name ?? '',
            'letter_ref' => null,
            'letter_date' => null,
        ];
    }

    /** @return array{expected_net: float, expected_gross: float, has_contract_discount: bool} */
    private function approvalLetterHints(Quote $quote): array
    {
        $printTotals = QuotePrintPresenter::fromQuote($quote);

        return [
            'expected_net' => (float) $printTotals['display_total'],
            'expected_gross' => (float) $printTotals['gross_total'],
            'has_contract_discount' => (bool) $printTotals['has_discount'],
        ];
    }

    /** @return array{quote_no: string, total: float, display_total: float, gross_total: float, status: string} */
    private function approvalLetterQuoteMeta(Quote $quote): array
    {
        $printTotals = QuotePrintPresenter::fromQuote($quote);

        return [
            'quote_no' => $quote->quote_no,
            'total' => (float) $quote->total,
            'display_total' => $printTotals['display_total'],
            'gross_total' => $printTotals['gross_total'],
            'status' => $quote->status,
        ];
    }

    public function confirm(ProcessApprovalLetterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $quote = Quote::where('quote_no', $validated['quote_no'])->first();
        $wasAlreadyApproved = $quote && $quote->status === Quote::STATUS_APPROVED;

        $case = $this->approvalLetterService->confirm($validated);

        return response()->json([
            'message' => $wasAlreadyApproved
                ? 'تم اعتماد هذا العرض مسبقاً — بانتظار إصدار أمر الشغل من مكتب التشغيل.'
                : 'تم تسجيل موافقة الجهة — بانتظار إصدار أمر الشغل من مكتب التشغيل.',
            'case' => $case->only([
                'id', 'case_no', 'stage_key', 'manufacturing_stage',
                'work_order_no', 'approval_date', 'approval_confirmed_at',
            ]),
            'work_order_no' => $case->work_order_no,
            'unfrozen' => true,
        ]);
    }
}
