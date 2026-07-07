<?php

namespace App\Http\Controllers\Patient;

use App\Enums\CaseStage;
use App\Http\Controllers\Controller;
use App\Models\CaseRecord;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;

/**
 * استعلام حالة الطلب — عام بدون مصادقة.
 * لا يُعرَض اسم المريض ولا أي بيانات مالية.
 */
class SelfServiceController extends Controller
{
    public function status(string $qr): JsonResponse
    {
        $patient = Patient::where('patient_qr', $qr)->first();

        if (! $patient) {
            abort(404);
        }

        $case = $patient->cases()
            ->where('stage_key', '!=', CaseRecord::STAGE_DELIVERED)
            ->latest()
            ->first()
            ?? $patient->cases()->latest()->first();

        if (! $case) {
            return response()->json([
                'stage_label' => 'لم يُسجَّل طلب بعد',
                'queue_position' => null,
                'expected_delivery' => null,
            ]);
        }

        $queuePosition = null;

        if ($case->stage_key === CaseRecord::STAGE_MANUFACTURING) {
            $queuePosition = CaseRecord::where('stage_key', CaseRecord::STAGE_MANUFACTURING)
                ->where('created_at', '<', $case->created_at)
                ->count() + 1;
        }

        return response()->json([
            'stage_label' => CaseStage::labelFor($case->stage_key),
            'queue_position' => $queuePosition,
            'expected_delivery' => null,
        ]);
    }
}
