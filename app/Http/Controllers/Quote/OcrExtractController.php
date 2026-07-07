<?php

namespace App\Http\Controllers\Quote;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Services\OcrLetterExtractionService;
use App\Support\QuotePrintPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * استخراج بيانات خطاب الموافقة (OCR) من PDF/صورة.
 */
class OcrExtractController extends Controller
{
    public function __construct(private readonly OcrLetterExtractionService $extractionService)
    {
    }

    /**
     * رفع الخطاب واستخراج البيانات للمراجعة البشرية (Human Override).
     */
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'quote_no'    => ['required', 'string', 'max:50'],
            'letter_file' => [
                'required',
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
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

        if ($quote->caseRecord->patient_type === \App\Models\Patient::TYPE_MILITARY) {
            return response()->json(['message' => 'المسار العسكري لا يتطلب خطاب موافقة.'], 422);
        }

        if ($quote->status !== Quote::STATUS_ISSUED) {
            return response()->json(['message' => 'يجب أن يكون العرض صادراً للجهة قبل رفع خطاب الموافقة.'], 422);
        }

        $file     = $request->file('letter_file');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        // قرص خاص (غير منشور على /storage) — الوصول عبر مسار مُصادَق عليه فقط.
        $path     = $file->storeAs('approval_letters', $filename, 'local');

        $extracted = $this->extractionService->extractFromUpload($file, $quote);
        $printTotals = QuotePrintPresenter::fromQuote($quote);

        return response()->json([
            'stored_path' => $path,
            'extracted'   => [
                'patient_name'    => $extracted['patient_name'],
                'approved_amount' => $extracted['approved_amount'],
                'company_name'    => $extracted['company_name'],
                'letter_ref'      => $extracted['letter_ref'],
                'letter_date'     => $extracted['letter_date'],
            ],
            'meta' => [
                'ocr_engine'      => $extracted['ocr_engine'],
                'raw_text_length' => $extracted['raw_text_length'],
            ],
            'quote' => [
                'quote_no'      => $quote->quote_no,
                'total'         => (float) $quote->total,
                'display_total' => $printTotals['display_total'],
                'gross_total'   => $printTotals['gross_total'],
                'status'        => $quote->status,
            ],
        ]);
    }
}
