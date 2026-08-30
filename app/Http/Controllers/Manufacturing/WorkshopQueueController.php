<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PreparesDocumentPrint;
use App\Http\Requests\Manufacturing\AdvanceManufacturingStageRequest;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\WorkshopAssignmentService;
use App\Services\WorkshopSectionService;
use App\Services\WorkshopTrackingService;
use App\Support\IssueVoucherPresenter;
use App\Support\ManufacturingDeskCaseFormatter;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WorkshopQueueController extends Controller
{
    use PaginationTrait;
    use PreparesDocumentPrint;

    public function __construct(
        private readonly BomService $bomService,
        private readonly WorkshopAssignmentService $workshopAssignment,
        private readonly WorkshopSectionService $workshopSections,
        private readonly WorkshopTrackingService $workshopTracking,
    ) {}

    /**
     * طابور تخصيص الإنتاج — أوامر بعد اعتماد التشغيل وقبل صرف المخزن.
     */
    public function assignmentQueue(Request $request): JsonResponse
    {
        $cases = $this->fetchForDashboard(
            CaseRecord::workshopAssignmentQueue()
                ->with([
                    'patient:id,patient_code,name',
                    'workshopSection:id,name,code',
                    'assignedTechnician:id,name',
                    'workshopAssignments.workshopSection:id,name,code',
                    'workshopAssignments.assignedTechnician:id,name',
                    'bom:id,case_id,bom_no,stage',
                    'bom.items:id,bom_id,stock_item_code,name,qty',
                ])
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('case_no', 'like', "%{$s}%")
                        ->orWhere('order_ref', 'like', "%{$s}%")
                        ->orWhere('work_order_no', 'like', "%{$s}%");
                }))
                ->orderByDesc('updated_at')
        );

        $collection = collect($cases);

        return response()->json([
            'data' => $collection->map(fn ($c) => ManufacturingDeskCaseFormatter::format($c, 'workshop.work-order.print'))->values(),
            'total' => $collection->count(),
        ]);
    }

    /**
     * طابور قسم الإنتاج — أوامر بعد صرف المخزn (BOM wip).
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
                    'workshopAssignments.workshopSection:id,name,code',
                    'workshopAssignments.assignedTechnician:id,name',
                    'bom:id,case_id,bom_no,stage',
                    'bom.items:id,bom_id,stock_item_code,name,qty',
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
     * تخصيص قسم الإنتاج والفني — من لوحة قسم الإنتاج (وليس مكتب التشغيل).
     */
    public function assign(Request $request, CaseRecord $case): JsonResponse
    {
        $validated = $request->validate([
            'assignments' => ['sometimes', 'array', 'min:1'],
            'assignments.*.workshop_section_id' => ['required', 'integer', 'exists:workshop_sections,id'],
            'assignments.*.assigned_technician_id' => ['required', 'integer', 'exists:users,id'],
            'workshop_section_id' => ['nullable', 'integer', 'exists:workshop_sections,id'],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $case = $this->workshopAssignment->assignOnApprove(
            $case,
            $validated['workshop_section_id'] ?? null,
            $validated['assigned_technician_id'] ?? null,
            $validated['assignments'] ?? null,
        );

        return response()->json([
            'message' => 'تم تخصيص أمر الشغل — بانتظار اعتماد التخصيص.',
            'case' => ManufacturingDeskCaseFormatter::format(
                $case->load([
                    'patient:id,patient_code,name',
                    'workshopSection:id,name',
                    'assignedTechnician:id,name',
                    'workshopAssignments.workshopSection:id,name',
                    'workshopAssignments.assignedTechnician:id,name',
                    'bom.items',
                ]),
                'workshop.work-order.print',
            ),
        ]);
    }

    public function approveAssignment(Request $request, CaseRecord $case): JsonResponse
    {
        $validated = $request->validate([
            'assignments' => ['sometimes', 'array', 'min:1'],
            'assignments.*.workshop_section_id' => ['required', 'integer', 'exists:workshop_sections,id'],
            'assignments.*.assigned_technician_id' => ['required', 'integer', 'exists:users,id'],
            'workshop_section_id' => ['nullable', 'integer', 'exists:workshop_sections,id'],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if ($request->filled('assignments') || $request->filled('workshop_section_id') || $request->filled('assigned_technician_id')) {
            $case = $this->workshopAssignment->assignOnApprove(
                $case,
                $request->integer('workshop_section_id') ?: $case->workshop_section_id,
                $request->integer('assigned_technician_id') ?: $case->assigned_technician_id,
                $validated['assignments'] ?? null,
            );
        }

        $case = $this->workshopAssignment->approveAssignment($case->fresh());

        return response()->json([
            'message' => 'تم اعتماد التخصيص — يمكن للمخزن صرف المواد.',
            'case' => ManufacturingDeskCaseFormatter::format(
                $case->load([
                    'patient:id,patient_code,name',
                    'workshopSection:id,name',
                    'assignedTechnician:id,name',
                    'workshopAssignments.workshopSection:id,name',
                    'workshopAssignments.assignedTechnician:id,name',
                    'bom.items',
                ]),
                'workshop.work-order.print',
            ),
        ]);
    }

    /** أقسام الإنتاج + فنيين — لتخصيص أوامر الشغل. */
    public function assignmentOptions(): JsonResponse
    {
        if (! config('workshop.enabled', true)) {
            return response()->json(['sections' => []]);
        }

        return response()->json([
            'sections' => collect($this->workshopSections->listActive())->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'technicians' => $s->technicians->map(fn ($u) => $u->only(['id', 'name']))->values(),
            ])->values(),
        ]);
    }

    /** لوحة تتبع الفنيين — أوامر كل فني ونسب الإنجاز. */
    public function technicianBoard(): JsonResponse
    {
        $this->bomService->repairOrphanWipCases();

        return response()->json($this->workshopTracking->technicianBoard());
    }

    /** تحديث نسبة إنجاز أمر الشغل لدى الفني. */
    public function updateProgress(Request $request, CaseRecord $case): JsonResponse
    {
        $validated = $request->validate([
            'progress_pct' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $case = $this->workshopAssignment->updateProgress($case, (int) $validated['progress_pct']);

        return response()->json([
            'message' => 'تم تحديث نسبة الإنجاز.',
            'case' => ManufacturingDeskCaseFormatter::format(
                $case->load(['patient:id,patient_code,name', 'workshopSection:id,name', 'assignedTechnician:id,name', 'bom.items']),
                'workshop.work-order.print',
            ),
        ]);
    }

    /**
     * تقدم مرحلة التصنيع الفرعية داخل قسم الإنتاج.
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
     * إذن صرف / استلام من المخزن — للطباعة من قسم الإنتاج قبل أو بعد الاعتماد.
     */
    public function printIssueVoucher(CaseRecord $case): View
    {
        $case->load('bom');
        abort_unless($case->bom, 404, 'لا توجد قائمة مواد لهذه الحالة.');

        return view('prints.issue-voucher', [
            'voucher' => IssueVoucherPresenter::fromBom($case->bom),
            'autoPrint' => true,
            'documentTemplate' => $this->documentTemplateForPrint('issue_voucher', $case),
        ]);
    }

    /**
     * إذن شغل قسم الإنتاج — النموذج الرسمي.
     */
    public function printWorkOrder(CaseRecord $case): View
    {
        abort_unless($case->work_order_no, 404, 'لا يوجد أمر تشغيل لهذه الحالة.');

        $case->load(['patient', 'contractCompany', 'bom.items']);

        abort_unless($case->bom, 404, 'لا توجد BOM مرتبطة بهذه الحالة.');

        return view('prints.work-order', [
            'case' => $case,
            'autoPrint' => true,
            'documentTemplate' => $this->documentTemplateForPrint('work_order', $case),
        ]);
    }
}
