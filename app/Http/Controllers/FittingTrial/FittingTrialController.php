<?php

namespace App\Http\Controllers\FittingTrial;

use App\Http\Controllers\Controller;
use App\Http\Requests\FittingTrial\StoreFittingTrialRequest;
use App\Models\CaseRecord;
use App\Services\FittingTrialService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FittingTrialController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly FittingTrialService $fittingTrialService)
    {
    }

    /**
     * الحالات في مرحلة التصنيع/التركيب مع سجل التجربة إن وُجد.
     */
    public function index(Request $request): JsonResponse
    {
        $cases = CaseRecord::with([
            'patient:id,patient_code,name',
            'fittingTrial',
            'bom:id,case_id,bom_no,stage',
        ])
            ->whereIn('stage_key', [
                CaseRecord::STAGE_MANUFACTURING,
                CaseRecord::STAGE_READY_DELIVERY,
            ])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('case_no', 'like', "%{$s}%")
                  ->orWhereHas('patient', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            }))
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json([
            'data'       => collect($cases->items())->map(fn ($c) => $this->formatCase($c)),
            'pagination' => $this->paginationModel($cases),
        ]);
    }

    /**
     * إنشاء أو تحديث سجل تجربة تركيب.
     */
    public function store(StoreFittingTrialRequest $request): JsonResponse
    {
        $case = CaseRecord::findOrFail($request->validated('case_id'));

        $trial = $this->fittingTrialService->save($case, $request->safe()->only([
            'trial1_date', 'trial2_date', 'notes', 'status',
        ]));

        return response()->json([
            'message' => 'تم حفظ تجربة التركيب.',
            'trial'   => $trial->only([
                'id', 'case_id', 'trial1_date', 'trial2_date', 'notes', 'status',
            ]),
        ], 201);
    }

    private function formatCase(CaseRecord $case): array
    {
        return $case->only([
            'id', 'case_no', 'order_ref', 'stage_key', 'manufacturing_stage',
            'work_order_no', 'patient_type',
        ]) + [
            'patient' => $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name'])
                : null,
            'fitting_trial' => $case->relationLoaded('fittingTrial') && $case->fittingTrial
                ? $case->fittingTrial->only([
                    'id', 'trial1_date', 'trial2_date', 'notes', 'status',
                ])
                : null,
            'bom' => $case->relationLoaded('bom') && $case->bom
                ? $case->bom->only(['id', 'bom_no', 'stage'])
                : null,
        ];
    }
}
