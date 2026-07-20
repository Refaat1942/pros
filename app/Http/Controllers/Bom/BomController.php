<?php

namespace App\Http\Controllers\Bom;

use App\Enums\WorkflowEvent;
use App\Exceptions\BarcodeDispenseMismatchException;
use App\Exceptions\InvalidWorkflowTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bom\DispenseBomRequest;
use App\Http\Requests\Bom\StoreBomRequest;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\PricingRequestItem;
use App\Models\Quote;
use App\Models\StockItem;
use App\Models\TechOrderSpecItem;
use App\Services\BomService;
use App\Services\PathwayTransitionMessageService;
use App\Support\BomItemAggregator;
use App\Support\IssueVoucherPresenter;
use App\Support\StockItemUomLookup;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BomController extends Controller
{
    use PaginationTrait;

    public function __construct(
        private readonly BomService $bomService,
        private readonly PathwayTransitionMessageService $transitions,
    ) {}

    /**
     * قائمة BOM — مرشَّحة حسب المرحلة (raw / wip / finished).
     */
    public function index(Request $request): JsonResponse
    {
        $boms = $this->fetchForDashboard(
            Bom::with([
                'caseRecord:id,case_no,stage_key,manufacturing_stage,work_order_no,patient_type',
                'items',
            ])
                ->when($request->stage, fn ($q, $s) => $q->where('stage', $s))
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('bom_no', 'like', "%{$s}%")
                        ->orWhere('order_ref', 'like', "%{$s}%")
                        ->orWhere('patient_name', 'like', "%{$s}%");
                }))
                ->orderByDesc('created_at')
        );

        return response()->json([
            'data' => collect($boms)->map(fn ($b) => $this->formatSummary($b))->values(),
            'total' => $boms->count(),
        ]);
    }

    /**
     * نموذج إنشاء BOM — يملأ من بنود طلب التسعير أو التوصيف الفني.
     */
    public function create(CaseRecord $case): JsonResponse
    {
        abort_unless($case->stage_key === CaseRecord::STAGE_MANUFACTURING, 422, 'الحالة ليست في مرحلة التصنيع.');

        if (! $case->isMilitary() && empty($case->work_order_no)) {
            abort(422, 'لا يمكن إنشاء BOM — أمر الشغل غير موجود.');
        }

        if ($case->bom) {
            abort(422, 'توجد BOM لهذه الحالة بالفعل.');
        }

        $case->load('pricingRequest.items', 'techOrderSpec.items');

        $prefill = $this->prefillItems($case);

        return response()->json([
            'case' => $this->formatCase($case),
            'prefill_items' => $prefill,
        ]);
    }

    /**
     * إنشاء BOM وحجز الكميات.
     */
    public function store(StoreBomRequest $request): JsonResponse
    {
        $case = CaseRecord::findOrFail($request->validated('case_id'));

        $bom = $this->bomService->create($case, $request->validated('items'));

        return response()->json([
            'message' => 'تم إنشاء BOM بنجاح.',
            'bom' => $this->formatDetail($bom),
        ], 201);
    }

    /**
     * تفاصيل BOM مع الكميات المصروفة والمرتجعة.
     */
    public function show(Bom $bom): JsonResponse
    {
        $bom->load(['items', 'caseRecord:id,case_no,stage_key,manufacturing_stage,work_order_no']);

        return response()->json($this->formatDetail($bom));
    }

    /**
     * صرف BOM بالباركود — raw → wip (أو طلب معلّق عند تفعيل اعتماد الإدارة).
     */
    public function scanDispense(DispenseBomRequest $request, Bom $bom): JsonResponse
    {
        $case = $bom->caseRecord;
        $fromStage = $case?->stage_key ?? CaseRecord::STAGE_MANUFACTURING;
        $barcodes = $request->validated('scanned_barcodes');

        if (config('inventory.dispense_requires_approval', true)) {
            $dispenseRequest = app(\App\Services\StockDispenseRequestService::class)->submit(
                $bom,
                $barcodes,
                $request->user(),
            );

            return response()->json([
                'message' => 'تم إرسال طلب الصرف — بانتظار اعتماد الإدارة.',
                'pending_approval' => true,
                'dispense_request' => $dispenseRequest->only(['id', 'status', 'work_order_no']),
            ], 202);
        }

        try {
            $bom = $this->bomService->releaseToWip($bom, $barcodes);
        } catch (BarcodeDispenseMismatchException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'blocked' => true,
                'alarm' => true,
            ], 422);
        }

        $case = $bom->caseRecord?->load('patient');

        return response()->json([
            'message' => $case
                ? $this->transitions->transferMessage($case, WorkflowEvent::BomDispensed->value, $fromStage)
                : 'تم صرف الأصناف بنجاح.',
            'bom' => $this->formatDetail($bom),
        ]);
    }

    /**
     * إذن صرف المخازن — للمسار العسكري (بدون عرض سعر) أو عند غياب الاقتباس.
     */
    public function printIssueVoucher(Bom $bom): View
    {
        abort_unless($bom->case_id, 404, 'لا توجد حالة مرتبطة بهذه القائمة.');

        return view('prints.issue-voucher', [
            'voucher' => IssueVoucherPresenter::fromBom($bom),
            'autoPrint' => true,
        ]);
    }

    /**
     * إغلاق BOM — wip → finished، الحالة → ready_delivery.
     */
    public function closeFinished(Bom $bom): JsonResponse
    {
        $case = $bom->caseRecord;
        $fromStage = $case?->stage_key ?? CaseRecord::STAGE_MANUFACTURING;

        try {
            $bom = $this->bomService->finish($bom);
        } catch (InvalidWorkflowTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $case = $bom->caseRecord?->load('patient');

        return response()->json([
            'message' => $case
                ? $this->transitions->transferMessage($case, WorkflowEvent::BomFinished->value, $fromStage)
                : 'تم إغلاق BOM — الحالة جاهزة للتسليم.',
            'bom' => $this->formatDetail($bom),
            'can_deliver' => $this->bomService->canDeliver($bom->caseRecord),
        ]);
    }

    private function prefillItems(CaseRecord $case): array
    {
        if ($case->pricingRequest?->items->isNotEmpty()) {
            return $case->pricingRequest->items->map(fn (PricingRequestItem $i) => [
                'stock_item_code' => $i->stock_item_code,
                'name' => $i->name,
                'qty' => $i->qty,
            ])->values()->all();
        }

        if ($case->techOrderSpec?->items->isNotEmpty()) {
            return $case->techOrderSpec->items->map(fn (TechOrderSpecItem $i) => [
                'stock_item_code' => $i->stock_item_code,
                'name' => $i->name,
                'qty' => $i->qty,
            ])->values()->all();
        }

        return [];
    }

    private function formatCase(CaseRecord $case): array
    {
        return $case->only([
            'id', 'case_no', 'order_ref', 'stage_key', 'manufacturing_stage',
            'work_order_no', 'patient_type', 'quote_no',
        ]);
    }

    private function formatSummary(Bom $bom): array
    {
        $quote = Quote::where('case_id', $bom->case_id)->orderByDesc('id')->first();
        $patientType = $bom->caseRecord?->patient_type;
        $isMilitary = $patientType === Patient::TYPE_MILITARY;

        return $bom->only([
            'id', 'bom_no', 'case_id', 'order_ref', 'quote_no',
            'patient_name', 'stage', 'released_at', 'finished_at',
        ]) + [
            'quote_id' => $quote?->id,
            'patient_type' => $patientType,
            'path' => $isMilitary ? 'military' : 'civilian',
            'path_label' => $isMilitary ? '🪖 عسكري' : '🌐 مدني',
            'issue_voucher_print_url' => IssueVoucherPresenter::printUrl($bom),
            'items_count' => $bom->relationLoaded('items')
                ? BomItemAggregator::uniqueCodeCount($bom->items)
                : 0,
            'case' => $bom->relationLoaded('caseRecord') && $bom->caseRecord
                ? $this->formatCase($bom->caseRecord)
                : null,
        ];
    }

    private function formatDetail(Bom $bom): array
    {
        $barcodes = $bom->relationLoaded('items') && $bom->items->isNotEmpty()
            ? StockItem::whereIn('code', $bom->items->pluck('stock_item_code'))
                ->pluck('barcode', 'code')
            : collect();

        $uomMap = $bom->relationLoaded('items') && $bom->items->isNotEmpty()
            ? StockItemUomLookup::forCodes($bom->items->pluck('stock_item_code')->all())
            : [];

        return $this->formatSummary($bom) + [
            'items' => $bom->relationLoaded('items')
                ? collect(BomItemAggregator::byStockCode($bom->items))
                    ->map(function (array $item) use ($barcodes, $uomMap) {
                        return $item + [
                            'expected_barcode' => $barcodes[$item['stock_item_code']] ?? null,
                            'uom' => $uomMap[$item['stock_item_code']] ?? 'قطعة',
                        ];
                    })
                    ->values()
                : [],
        ];
    }
}
