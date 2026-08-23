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

        if ($quote->status !== Quote::STATUS_ISSUED) {
            return response()->json(['message' => 'يجب أن يكون العرض صادراً للجهة قبل رفع خطاب الموافقة.'], 422);
        }

        $file = $request->file('letter_file');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('approval_letters', $filename, 'local');

        $case = $quote->caseRecord;
        $patient = $case->patient;
        $printTotals = QuotePrintPresenter::fromQuote($quote);

        return response()->json([
            'stored_path' => $path,
            'defaults' => [
                'patient_name' => $patient?->name ?? $quote->patient_name ?? '',
                'approved_amount' => QuotePrintPresenter::approvedAmount($quote),
                'company_name' => $case->company_name ?? $quote->company_name ?? '',
                'letter_ref' => null,
                'letter_date' => null,
            ],
            'hints' => [
                'expected_net' => (float) $printTotals['display_total'],
                'expected_gross' => (float) $printTotals['gross_total'],
                'has_contract_discount' => (bool) $printTotals['has_discount'],
            ],
            'quote' => [
                'quote_no' => $quote->quote_no,
                'total' => (float) $quote->total,
                'display_total' => $printTotals['display_total'],
                'gross_total' => $printTotals['gross_total'],
                'status' => $quote->status,
            ],
        ]);
    }

    public function confirm(ProcessApprovalLetterRequest $request): JsonResponse
    {
        $case = $this->approvalLetterService->confirm($request->validated());

        return response()->json([
            'message' => 'تم تسجيل موافقة الجهة — بانتظار إصدار أمر الشغل من مكتب التشغيل.',
            'case' => $case->only([
                'id', 'case_no', 'stage_key', 'manufacturing_stage',
                'work_order_no', 'approval_date', 'approval_confirmed_at',
            ]),
            'work_order_no' => $case->work_order_no,
            'unfrozen' => true,
        ]);
    }
}
