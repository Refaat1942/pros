<?php

namespace App\Http\Controllers\Delivery;

use App\Exceptions\DeliveryNotReadyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\ScanDeliveryRequest;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Services\DeliveryService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تسليم الطرف — الاستقبال (بدون مبالغ مالية).
 */
class DeliveryController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly DeliveryService $deliveryService)
    {
    }

    /**
     * الحالات الجاهزة للتسليم (ready_delivery + BOM finished).
     */
    public function index(Request $request): JsonResponse
    {
        $cases = CaseRecord::with([
            'patient:id,patient_code,name,patient_type',
            'bom:id,case_id,bom_no,stage,finished_at',
        ])
            ->where('stage_key', CaseRecord::STAGE_READY_DELIVERY)
            ->whereHas('bom', fn ($q) => $q->where('stage', \App\Models\Bom::STAGE_FINISHED))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('case_no', 'like', "%{$s}%")
                  ->orWhere('order_ref', 'like', "%{$s}%")
                  ->orWhere('work_order_no', 'like', "%{$s}%")
                  ->orWhereHas('patient', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            }))
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json([
            'data'       => collect($cases->items())->map(fn ($c) => $this->formatSummary($c)),
            'pagination' => $this->paginationModel($cases),
        ]);
    }

    /**
     * مسح بطاقة المريض — إغلاق الحالة.
     */
    public function scan(ScanDeliveryRequest $request): JsonResponse
    {
        $patient = Patient::where('patient_qr', $request->validated('scanned_qr'))->first();

        if (! $patient) {
            return response()->json(['message' => 'بطاقة المريض غير موجودة.'], 422);
        }

        $case = CaseRecord::where('patient_id', $patient->id)
            ->where('stage_key', CaseRecord::STAGE_READY_DELIVERY)
            ->orderByDesc('id')
            ->first();

        if (! $case) {
            return response()->json(['message' => 'لا توجد حالة جاهزة للتسليم لهذا المريض.'], 422);
        }

        try {
            $case = $this->deliveryService->close($case, $request->validated('scanned_qr'));
        } catch (DeliveryNotReadyException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تم تسليم الطرف وإغلاق الحالة بنجاح.',
            'case'    => $this->formatSummary($case),
        ]);
    }

    /**
     * تفاصيل التسليم — بدون مبالغ مالية.
     */
    public function show(CaseRecord $case): JsonResponse
    {
        abort_unless(
            in_array($case->stage_key, [
                CaseRecord::STAGE_READY_DELIVERY,
                CaseRecord::STAGE_DELIVERED,
            ], true),
            404
        );

        $case->load([
            'patient:id,patient_code,name,patient_type,company_name',
            'bom:id,case_id,bom_no,stage,finished_at,released_at',
        ]);

        return response()->json($this->formatDetail($case));
    }

    private function formatSummary(CaseRecord $case): array
    {
        return $case->only([
            'id', 'case_no', 'order_ref', 'stage_key',
            'work_order_no', 'patient_type', 'company_name', 'delivered_at',
        ]) + [
            'patient' => $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name'])
                : null,
            'bom' => $case->relationLoaded('bom') && $case->bom
                ? $case->bom->only(['id', 'bom_no', 'stage', 'finished_at'])
                : null,
        ];
    }

    private function formatDetail(CaseRecord $case): array
    {
        return $this->formatSummary($case) + [
            'quote_no' => $case->quote_no,
        ];
    }
}
