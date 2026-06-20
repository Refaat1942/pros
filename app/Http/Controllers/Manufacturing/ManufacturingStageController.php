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
        $this->bomService->repairOrphanWipCases();

        $cases = $this->fetchForDashboard(
            CaseRecord::with([
                'patient:id,patient_code,name',
                'bom:id,case_id,bom_no,stage',
                'bom.items:id,bom_id,stock_item_code,qty',
            ])
                ->where('stage_key', CaseRecord::STAGE_MANUFACTURING)
                ->when($request->manufacturing_stage, fn ($q, $s) => $q->where('manufacturing_stage', $s))
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('case_no', 'like', "%{$s}%")
                      ->orWhere('order_ref', 'like', "%{$s}%")
                      ->orWhere('work_order_no', 'like', "%{$s}%");
                }))
                ->orderByDesc('updated_at')
        );

        return response()->json([
            'data'  => collect($cases)->map(fn ($c) => $this->formatCase($c))->values(),
            'total' => $cases->count(),
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
            'case'    => $this->formatCase($case->load(['patient:id,patient_code,name', 'bom.items:id,bom_id'])),
        ]);
    }

    /**
     * فحص جودة نهائي — إغلاق BOM بعد مرحلة التشطيب.
     */
    public function finishQuality(CaseRecord $case): JsonResponse
    {
        $case->load('bom');

        if (! $case->bom) {
            abort(422, 'لا توجد BOM مرتبطة بهذه الحالة.');
        }

        $bom = $this->bomService->finish($case->bom);

        $case->refresh()->load(['patient:id,patient_code,name', 'bom']);

        return response()->json([
            'message' => 'تم فحص الجودة — BOM تام والحالة جاهزة للتسليم.',
            'case'    => $this->formatCase($case),
            'bom'     => $bom->only(['id', 'bom_no', 'stage', 'finished_at']),
        ]);
    }

    private function formatCase(CaseRecord $case): array
    {
        $bomItemsCount = $case->relationLoaded('bom') && $case->bom?->relationLoaded('items')
            ? $case->bom->items->count()
            : 0;

        return $case->only([
            'id', 'case_no', 'order_ref', 'stage_key', 'manufacturing_stage',
            'work_order_no', 'patient_type', 'path', 'quote_no', 'company_name',
        ]) + [
            'pathway_label' => $case->isMilitary() ? 'عسكري' : 'مدني',
            'patient' => $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name'])
                : null,
            'bom' => $case->relationLoaded('bom') && $case->bom
                ? $case->bom->only(['id', 'bom_no', 'stage']) + ['items_count' => $bomItemsCount]
                : null,
        ];
    }
}
