<?php

namespace App\Http\Controllers\Quote;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * استخراج بيانات خطاب الموافقة (OCR).
 *
 * يستقبل ملف الخطاب المرفوع، يُخزّنه مؤقتاً، ويُعيد البيانات
 * المُستخرجة للموظف كحقول قابلة للتعديل (Human Override).
 *
 * ملاحظة: لا يوجد محرك OCR حقيقي مُدمج — السيستم يعيد بيانات عرض
 * السعر المعتمد مسبقاً كقيم ابتدائية ليراجعها الموظف ويُعدّلها يدوياً
 * عند الحاجة، مما يضمن دقة 100 % بدلاً من الاعتماد على قراءة آلية غير مراجعة.
 */
class OcrExtractController extends Controller
{
    /**
     * رفع الخطاب واستخراج البيانات المبدئية.
     */
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'quote_no'   => ['required', 'string', 'max:50'],
            'letter_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $quote = Quote::with(['caseRecord.patient'])
            ->where('quote_no', $request->input('quote_no'))
            ->first();

        if (! $quote || ! $quote->caseRecord) {
            return response()->json(['message' => 'عرض السعر غير موجود.'], 422);
        }

        if ($quote->caseRecord->patient_type === \App\Models\Patient::TYPE_MILITARY) {
            return response()->json(['message' => 'المسار العسكري لا يتطلب خطاب موافقة.'], 422);
        }

        $file     = $request->file('letter_file');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs('approval_letters', $filename, 'public');

        $case    = $quote->caseRecord;
        $patient = $case->patient;

        return response()->json([
            'stored_path'     => $path,
            'extracted'       => [
                'patient_name'    => $patient?->name ?? $quote->patient_name,
                'approved_amount' => (float) $quote->total,
                'company_name'    => $case->company_name ?? $quote->company_name,
            ],
            'quote' => [
                'quote_no'   => $quote->quote_no,
                'total'      => (float) $quote->total,
                'status'     => $quote->status,
            ],
        ]);
    }
}
