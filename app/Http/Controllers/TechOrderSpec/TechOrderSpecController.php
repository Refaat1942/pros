<?php

namespace App\Http\Controllers\TechOrderSpec;

use App\Exceptions\InvalidSpecItemException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TechOrderSpec\StoreTechOrderSpecRequest;
use App\Http\Requests\TechOrderSpec\UpdateTechOrderSpecRequest;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\PricingRequest;
use App\Models\StockItem;
use App\Models\TechOrderSpec;
use App\Services\SpecOrdersService;
use App\Services\SpecService;
use App\Support\CaseDisplayStatus;
use App\Support\ExportCsvFormat;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechOrderSpecController extends Controller
{
    use PaginationTrait;

    public function __construct(
        private readonly SpecService $specService,
        private readonly SpecOrdersService $ordersService,
    ) {
    }

    /**
     * الحالات الواردة في مرحلة التوصيف الفني.
     */
    public function index(Request $request): JsonResponse
    {
        $range  = $this->ordersService->parseDateRange($request->query('from'), $request->query('to'));
        $from   = $range['from'] ?? null;
        $to     = $range['to'] ?? null;
        $search = $request->query('search');

        $cases = $this->ordersService->list($from, $to, $search);
        $stats = $this->ordersService->stats($from, $to, $search);

        return response()->json([
            'data'        => $cases->map(fn ($c) => $this->formatCase($c))->values(),
            'stats'       => $stats,
            'date_from'   => $from?->toDateString(),
            'date_to'     => $to?->toDateString(),
            'export_rows' => $cases->map(fn ($c) => $this->ordersService->exportRow($c))->values(),
            'total'       => $cases->count(),
        ]);
    }

    /**
     * تصدير طلبات التوصيف حسب الفلتر (CSV).
     */
    public function exportOrders(Request $request): StreamedResponse
    {
        $range  = $this->ordersService->parseDateRange($request->query('from'), $request->query('to'));
        $from   = $range['from'] ?? null;
        $to     = $range['to'] ?? null;
        $search = $request->query('search');

        $report = $this->ordersService->exportReport($from, $to, $search);

        $suffix = ($from && $to)
            ? $from->format('Y-m-d') . '_' . $to->format('Y-m-d')
            : 'all';
        $filename = 'spec-orders-' . $suffix . '.csv';

        $callback = function () use ($report) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, [$report['title']]);
            fputcsv($out, [$report['period_label']]);
            fputcsv($out, []);
            fputcsv($out, ExportCsvFormat::row($report['headers']));
            foreach ($report['rows'] as $row) {
                fputcsv($out, ExportCsvFormat::row($row));
            }
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * نموذج التوصيف — بيانات الحالة + catalog الأصناف (بدون qty/أسعار).
     */
    public function create(CaseRecord $case): JsonResponse
    {
        abort_unless($case->stage_key === CaseRecord::STAGE_TECHNICAL, 422, 'الحالة ليست في مرحلة التوصيف الفني.');

        app(SpecService::class)->reopenForRework($case);

        $case->load('patient:id,patient_code,name,patient_type,company_name,sovereign_entity,rank');

        $medicalRecord = MedicalRecord::where('case_id', $case->id)
            ->where('locked', true)
            ->with('items')
            ->latest()
            ->first();

        $draft = TechOrderSpec::where('case_id', $case->id)
            ->where('locked', false)
            ->with('items')
            ->first();

        $submittedSpec = TechOrderSpec::where('case_id', $case->id)
            ->where('locked', true)
            ->with('items')
            ->first();

        $stockCatalog = StockItem::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'spec', 'qty', 'reserved', 'uom'])
            ->map(fn ($item) => [
                'code'          => $item->code,
                'name'          => $item->name,
                'spec'          => $item->spec,
                'uom'           => $item->uom,
                'available_max' => $item->availableQty(),
            ]);

        return response()->json([
            'case'           => $this->formatCase($case),
            'medical_record' => $medicalRecord ? [
                'diagnosis'    => $medicalRecord->diagnosis,
                'prescription' => $medicalRecord->prescription,
                'doctor_name'  => $medicalRecord->doctor_name,
                'items'        => $medicalRecord->items->map->only(['stock_item_code', 'name', 'qty']),
            ] : null,
            'draft'          => $draft ? $this->formatSpec($draft) : null,
            'submitted_spec' => $submittedSpec ? $this->formatSpec($submittedSpec) : null,
            'stock_catalog'  => $stockCatalog,
        ]);
    }

    public function store(StoreTechOrderSpecRequest $request): JsonResponse
    {
        $spec = $this->specService->saveDraft($request->validated());

        return response()->json($this->formatSpec($spec), 201);
    }

    public function update(UpdateTechOrderSpecRequest $request, TechOrderSpec $spec): JsonResponse
    {
        $spec = $this->specService->updateDraft($spec, $request->validated());

        return response()->json($this->formatSpec($spec));
    }

    public function submit(TechOrderSpec $spec): JsonResponse
    {
        try {
            $case = $this->specService->submit($spec);
        } catch (InvalidSpecItemException $e) {
            return response()->json([
                'message'          => $e->getMessage(),
                'stock_item_code'  => $e->stockItemCode,
            ], 422);
        }

        return response()->json([
            'message' => 'تم إرسال التوصيف إلى مرحلة المعدلات.',
            'case'    => $this->formatCase($case->load('patient')),
            'spec'    => $this->formatSpec($spec->fresh()->load('items')),
        ]);
    }

    /**
     * معاينة التوصيف بعد الإرسال — للقراءة فقط.
     */
    public function preview(TechOrderSpec $spec): JsonResponse
    {
        abort_unless($spec->locked, 403, 'التوصيف لم يُرسَل بعد.');

        $spec->load('items', 'caseRecord');

        return response()->json($this->formatSpec($spec));
    }

    /**
     * طباعة تقرير التوصيف الفني — A4 مع شعار المؤسسة.
     */
    public function print(TechOrderSpec $spec, Request $request): Response
    {
        abort_unless($spec->locked, 403, 'التوصيف لم يُرسَل بعد.');

        $spec->load(['items', 'caseRecord.patient']);

        return response()->view('spec.print', [
            'spec'      => $spec,
            'case'      => $spec->caseRecord,
            'autoPrint' => ! $request->boolean('embed'),
        ]);
    }

    /**
     * حالات أُرسل توصيفها — مع حالة طلب التسعير.
     */
    public function pricingStatus(Request $request): JsonResponse
    {
        $requests = $this->fetchForDashboard(
            PricingRequest::with([
                'caseRecord:id,case_no,order_ref,stage_key,patient_type,manufacturing_stage',
                'items',
            ])
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('request_no', 'like', "%{$s}%")
                      ->orWhere('patient_name', 'like', "%{$s}%")
                      ->orWhere('order_ref', 'like', "%{$s}%");
                }))
                ->orderByDesc('request_date')
        );

        return response()->json([
            'data'  => collect($requests)->map(fn ($r) => $this->formatPricingRequest($r, forSpec: true))->values(),
            'total' => $requests->count(),
        ]);
    }

    private function formatCase(CaseRecord $case): array
    {
        return $case->only([
            'id',
            'case_no',
            'order_ref',
            'patient_id',
            'patient_type',
            'path',
            'stage_key',
            'company_name',
            'rank',
            'sovereign_entity',
            'created_at',
        ]) + [
            'display_entity' => $case->displayEntity(),
            'rework'         => $case->reworkNoticeFor(CaseRecord::STAGE_TECHNICAL),
            'patient' => $case->relationLoaded('patient') ? $case->patient : null,
            'spec'    => $case->relationLoaded('techOrderSpec') ? $case->techOrderSpec : null,
        ];
    }

    private function formatSpec(TechOrderSpec $spec): array
    {
        return $spec->only([
            'id',
            'order_ref',
            'case_id',
            'patient_name',
            'company_name',
            'doctor_name',
            'tech_notes',
            'submitted_at',
            'locked',
        ]) + [
            'items' => $spec->relationLoaded('items')
                ? $spec->items->map->only(['stock_item_code', 'name', 'qty'])
                : [],
            'print_url' => $spec->locked
                ? route('spec.spec.print', ['spec' => $spec->id])
                : null,
        ];
    }

    private function formatPricingRequest(PricingRequest $request, bool $forSpec = false): array
    {
        $display = CaseDisplayStatus::forPricingRequest($request);

        $data = $request->only([
            'id',
            'request_no',
            'order_ref',
            'case_id',
            'patient_name',
            'company_name',
            'request_date',
            'items_count',
            'doctor_name',
            'patient_type',
            'status_key',
            'step',
            'status_label',
            'display_status_label',
            'display_status_badge_class',
        ]) + [
            'display_status' => $display->toArray(),
            'items' => $request->relationLoaded('items')
                ? $request->items->map->only(['stock_item_code', 'name', 'qty'])
                : [],
            'case' => $request->relationLoaded('caseRecord') && $request->caseRecord
                ? $request->caseRecord->only(['id', 'case_no', 'order_ref', 'stage_key', 'patient_type', 'manufacturing_stage'])
                : null,
        ];

        if (! $forSpec) {
            $data['computed_total'] = $request->computed_total;
        }

        return $data;
    }
}
