<?php

namespace App\Http\Controllers\Adjustments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adjustments\StoreAdjustmentItemsRequest;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\StockItem;
use App\Services\AdjustmentsService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * مكتب المعدلات (الخطوة 4) — مراجعة بنود الفني (للقراءة) وإضافة مكوّنات قبل التكاليف.
 */
class AdjustmentsController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly AdjustmentsService $adjustmentsService)
    {
    }

    /**
     * الحالات الواردة في مرحلة المعدلات.
     */
    public function index(Request $request): JsonResponse
    {
        $cases = $this->fetchForDashboard(
            CaseRecord::inAdjustments()
                ->with([
                    'patient:id,patient_code,name,patient_type',
                    'techOrderSpec:id,case_id,tech_notes',
                    'bom:id,case_id,bom_no,stage',
                    'bom.items:id,bom_id,stock_item_code,name,source,qty',
                ])
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('case_no', 'like', "%{$s}%")
                      ->orWhere('order_ref', 'like', "%{$s}%")
                      ->orWhereHas('patient', fn ($q) => $q->where('name', 'like', "%{$s}%"));
                }))
                ->orderByDesc('updated_at')
        );

        return response()->json([
            'data'  => collect($cases)->map(fn (CaseRecord $c) => $this->formatCase($c))->values(),
            'total' => $cases->count(),
        ]);
    }

    /**
     * تفاصيل حالة المعدلات — بنود الفني (read-only) + كتالوج لإضافة بنود.
     */
    public function show(CaseRecord $case): JsonResponse
    {
        abort_unless($case->isInAdjustments(), 422, 'الحالة ليست في مرحلة المعدلات.');

        $case->load([
            'patient:id,patient_code,name,patient_type,company_name,sovereign_entity,rank',
            'techOrderSpec:id,case_id,tech_notes',
            'bom.items',
        ]);

        $stockCatalog = StockItem::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'qty', 'reserved'])
            ->map(fn (StockItem $item) => [
                'code'      => $item->code,
                'name'      => $item->name,
                'qty'       => (int) $item->qty,
                'reserved'  => (int) $item->reserved,
                'available' => $item->availableQty(),
            ]);

        return response()->json([
            'case'          => $this->formatCase($case),
            'stock_catalog' => $stockCatalog,
        ]);
    }

    /**
     * إضافة بنود مستشار المعدلات — لا مساس بالبنود الأصلية (الفني).
     */
    public function addItems(StoreAdjustmentItemsRequest $request, CaseRecord $case): JsonResponse
    {
        $bom = $this->adjustmentsService->addItems($case, $request->validated('items'));

        return response()->json([
            'message' => 'تمت إضافة البنود إلى قائمة المواد.',
            'bom'     => [
                'id'    => $bom->id,
                'items' => $bom->items->map(fn (BomItem $i) => $this->formatBomItem($i))->values(),
            ],
        ], 201);
    }

    /**
     * إغلاق المعدلات → دفع التكاليف → عرض السعر → مكتب التشغيل.
     */
    public function complete(CaseRecord $case): JsonResponse
    {
        $case = $this->adjustmentsService->complete($case);

        return response()->json([
            'message' => 'تم إغلاق المعدلات — الحالة في طابور التكاليف بانتظار التأكيد.',
            'case'    => $this->formatCase($case->load(['patient:id,patient_code,name', 'bom.items'])),
        ]);
    }

    private function formatCase(CaseRecord $case): array
    {
        return $case->only([
            'id', 'case_no', 'order_ref', 'stage_key', 'patient_type', 'path',
            'company_name', 'rank', 'sovereign_entity', 'created_at',
        ]) + [
            'pathway_label'  => $case->isMilitary() ? 'عسكري' : 'مدني',
            'display_entity' => $case->displayEntity(),
            'tech_notes'     => $case->resolvedTechNotes(),
            'rework'         => $case->reworkNoticeFor(CaseRecord::STAGE_ADJUSTMENTS),
            'patient' => $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name'])
                : null,
            'bom' => $case->relationLoaded('bom') && $case->bom
                ? [
                    'id'      => $case->bom->id,
                    'bom_no'  => $case->bom->bom_no,
                    'stage'   => $case->bom->stage,
                    'items'   => $case->bom->relationLoaded('items')
                        ? $case->bom->items->map(fn (BomItem $i) => $this->formatBomItem($i))->values()
                        : [],
                ]
                : null,
        ];
    }

    /**
     * بند BOM للعرض — بلا أي تكلفة/سعر (عمى مالي لمستشار المعدلات).
     * البنود الأصلية (الفني) محدّدة read_only=true.
     */
    private function formatBomItem(BomItem $item): array
    {
        return [
            'id'              => $item->id,
            'stock_item_code' => $item->stock_item_code,
            'name'            => $item->name,
            'qty'             => $item->qty,
            'source'          => $item->source,
            'read_only'       => $item->source === BomItem::SOURCE_SPEC,
        ];
    }
}
