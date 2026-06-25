<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manufacturing\AdvanceManufacturingStageRequest;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\DeliveryService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManufacturingStageController extends Controller
{
    use PaginationTrait;

    public function __construct(
        private readonly BomService $bomService,
        private readonly DeliveryService $deliveryService,
    ) {
    }

    /**
     * الحالات في مرحلة التصنيع مع المرحلة الفرعية.
     */
    public function index(Request $request): JsonResponse
    {
        $this->bomService->repairOrphanWipCases();

        $cases = $this->fetchForDashboard(
            CaseRecord::operationsDeskQueue()
                ->with([
                    'patient:id,patient_code,name',
                    'bom:id,case_id,bom_no,stage',
                    'bom.items:id,bom_id,stock_item_code,name,qty,source',
                ])
                ->when($request->manufacturing_stage, fn ($q, $s) => $q->where('manufacturing_stage', $s))
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('case_no', 'like', "%{$s}%")
                      ->orWhere('order_ref', 'like', "%{$s}%")
                      ->orWhere('work_order_no', 'like', "%{$s}%");
                }))
                ->orderByDesc('updated_at')
        );

        return response()->json([
            'data'    => collect($cases)->map(fn ($c) => $this->formatCase($c))->values(),
            'total'   => $cases->count(),
            'summary' => $this->buildSummary($cases),
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
     * إتمام التصنيع — إغلاق BOM وتحويل الحالة للتسليم.
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
            'message' => 'تم التصنيع — الحالة جاهزة للتسليم.',
            'case'    => $this->formatCase($case),
            'bom'     => $bom->only(['id', 'bom_no', 'stage', 'finished_at']),
        ]);
    }

    /**
     * تسليم الطرف وإغلاق الحالة — من مكتب التشغيل (بديل الاستقبال).
     */
    public function deliver(CaseRecord $case): JsonResponse
    {
        $case->load('patient');

        if (! $case->patient?->patient_qr) {
            abort(422, 'بطاقة المريض غير متاحة — لا يمكن إتمام التسليم.');
        }

        try {
            $case = $this->deliveryService->close($case, $case->patient->patient_qr);
        } catch (\App\Exceptions\DeliveryNotReadyException $e) {
            return response()->json(['message' => $e->getMessage(), 'blocked' => true], 422);
        } catch (\App\Exceptions\InvalidPatientQrException $e) {
            return response()->json([
                'message'  => $e->getMessage(),
                'blocked'  => true,
                'security' => true,
            ], 422);
        }

        return response()->json([
            'message'    => 'تم تسليم الطرف وإغلاق الطلب بنجاح.',
            'case'       => $this->formatCase($case),
            'invoice_no' => $case->invoice_no,
            'closed'     => true,
        ]);
    }

    /**
     * إذن شغل / أمر الإنتاج — النموذج الرسمي (1.jpeg).
     */
    public function printWorkOrder(CaseRecord $case): View
    {
        abort_unless($case->work_order_no, 404, 'لا يوجد أمر تشغيل لهذه الحالة.');

        $case->load(['patient', 'bom.items']);

        abort_unless($case->bom, 404, 'لا توجد BOM مرتبطة بهذه الحالة.');

        return view('prints.work-order', [
            'case'      => $case,
            'autoPrint' => true,
        ]);
    }

    private function buildSummary($cases): array
    {
        $collection = collect($cases);
        $wipCount   = $collection->filter(fn ($c) => $c->bom?->stage === Bom::STAGE_WIP)->count();
        $readyCount = $collection->filter(fn ($c) => $c->stage_key === CaseRecord::STAGE_READY_DELIVERY)->count();

        return [
            'raw'            => 0,
            'wip'            => $wipCount,
            'ready_delivery' => $readyCount,
            'done'           => CaseRecord::countDeliveredByOps(),
            'total_active'   => $collection->count(),
        ];
    }

    private function formatCase(CaseRecord $case): array
    {
        $bom = null;
        if ($case->relationLoaded('bom') && $case->bom) {
            $bom = $case->bom->only(['id', 'bom_no', 'stage']) + [
                'items_count' => $case->bom->relationLoaded('items') ? $case->bom->items->count() : 0,
                'items'       => $case->bom->relationLoaded('items')
                    ? $case->bom->items->map(fn ($item) => [
                        'stock_item_code' => $item->stock_item_code,
                        'name'            => $item->name,
                        'qty'             => $item->qty,
                        'source'          => $item->source,
                    ])->values()->all()
                    : [],
            ];
        }

        return $case->only([
            'id', 'case_no', 'order_ref', 'stage_key', 'manufacturing_stage',
            'work_order_no', 'patient_type', 'path', 'quote_no', 'company_name',
        ]) + [
            'pathway_label' => $case->isMilitary() ? 'عسكري' : 'مدني',
            'work_order_print_url' => route('operations.work-order.print', $case),
            'patient' => $case->relationLoaded('patient') && $case->patient
                ? $case->patient->only(['id', 'patient_code', 'name'])
                : null,
            'bom' => $bom,
        ];
    }
}
