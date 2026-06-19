<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manufacturing\AdvanceManufacturingStageRequest;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufacturingStageController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly BomService $bomService)
    {
    }

    /**
     * الحالات في مرحلة التصنيع مع المرحلة الفرعية.
     */
    public function index(Request $request): JsonResponse
    {
        $cases = CaseRecord::with([
            'patient:id,patient_code,name',
            'bom:id,case_id,bom_no,stage',
        ])
            ->where('stage_key', CaseRecord::STAGE_MANUFACTURING)
            ->when($request->manufacturing_stage, fn ($q, $s) => $q->where('manufacturing_stage', $s))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('case_no', 'like', "%{$s}%")
                  ->orWhere('order_ref', 'like', "%{$s}%")
                  ->orWhere('work_order_no', 'like', "%{$s}%");
            }))
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json([
            'data'       => collect($cases->items())->map(fn ($c) => $this->formatCase($c)),
            'pagination' => $this->paginationModel($cases),
        ]);
    }

    /**
     * تقدم مرحلة التصنيع الفرعية.
     */
    public function advance(AdvanceManufacturingStageRequest $request, CaseRecord $case): JsonResponse
    {
        $case = $this->bomService->advanceManufacturingStage(
            $case,
            $request->validated('manufacturing_stage'),
        );

        return response()->json([
            'message' => 'تم تقدم مرحلة التصنيع.',
            'case'    => $this->formatCase($case),
        ]);
    }

    private function formatCase(CaseRecord $case): array
    {
        return $case->only([
            'id', 'case_no', 'order_ref', 'stage_key', 'manufacturing_stage',
            'work_order_no', 'patient_type', 'quote_no',
        ]) + [
            'patient' => $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name'])
                : null,
            'bom' => $case->relationLoaded('bom') && $case->bom
                ? $case->bom->only(['id', 'bom_no', 'stage'])
                : null,
        ];
    }
}
