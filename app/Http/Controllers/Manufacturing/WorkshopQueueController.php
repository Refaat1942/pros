<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manufacturing\AdvanceManufacturingStageRequest;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Support\ManufacturingDeskCaseFormatter;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WorkshopQueueController extends Controller
{
    use PaginationTrait;

    public function __construct(
        private readonly BomService $bomService,
    ) {}

    /**
     * طابور ورشة التصنيع — أوامر بعد صرف المخزن (BOM wip).
     */
    public function index(Request $request): JsonResponse
    {
        $this->bomService->repairOrphanWipCases();

        $cases = $this->fetchForDashboard(
            CaseRecord::workshopDeskQueue()
                ->with([
                    'patient:id,patient_code,name',
                    'workshopSection:id,name,code',
                    'assignedTechnician:id,name',
                    'bom:id,case_id,bom_no,stage',
                    'bom.items:id,bom_id,stock_item_code,name,qty,source',
                ])
                ->when($request->filter === 'mine' && Auth::id(), fn ($q) => $q->where('assigned_technician_id', Auth::id()))
                ->when($request->filter === 'section' && $request->section_id, fn ($q) => $q->where('workshop_section_id', $request->integer('section_id')))
                ->when($request->manufacturing_stage, fn ($q, $s) => $q->where('manufacturing_stage', $s))
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('case_no', 'like', "%{$s}%")
                        ->orWhere('order_ref', 'like', "%{$s}%")
                        ->orWhere('work_order_no', 'like', "%{$s}%");
                }))
                ->orderByDesc('updated_at')
        );

        $collection = collect($cases);
        $summary = ManufacturingDeskCaseFormatter::workshopSummary($collection);

        return response()->json([
            'data' => $collection->map(fn ($c) => ManufacturingDeskCaseFormatter::format($c, 'workshop.work-order.print'))->values(),
            'total' => $collection->count(),
            'summary' => $summary,
        ]);
    }

    /**
     * تقدم مرحلة التصنيع الفرعية داخل الورشة.
     */
    public function advance(AdvanceManufacturingStageRequest $request, CaseRecord $case): JsonResponse
    {
        $case = $this->bomService->advanceManufacturingStage(
            $case,
            $request->validated('manufacturing_stage'),
        );

        return response()->json([
            'message' => 'تم تقدم مرحلة التصنيع.',
            'case' => ManufacturingDeskCaseFormatter::format(
                $case->load(['patient:id,patient_code,name', 'bom.items:id,bom_id']),
                'workshop.work-order.print',
            ),
        ]);
    }

    /**
     * إتمام التصنيع — إغلاق BOM وتحويل الحالة للمخزن (جاهزة للتسليم).
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
            'message' => 'تم التصنيع — يُرجى توجيه العميل إلى المخزن للتسليم.',
            'case' => ManufacturingDeskCaseFormatter::format($case, 'workshop.work-order.print'),
            'bom' => $bom->only(['id', 'bom_no', 'stage', 'finished_at']),
        ]);
    }

    /**
     * إذن شغل الورشة — النموذج الرسمي.
     */
    public function printWorkOrder(CaseRecord $case): View
    {
        abort_unless($case->work_order_no, 404, 'لا يوجد أمر تشغيل لهذه الحالة.');

        $case->load(['patient', 'contractCompany', 'bom.items']);

        abort_unless($case->bom, 404, 'لا توجد BOM مرتبطة بهذه الحالة.');

        return view('prints.work-order', [
            'case' => $case,
            'autoPrint' => true,
        ]);
    }
}
