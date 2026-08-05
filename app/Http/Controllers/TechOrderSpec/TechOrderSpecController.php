<?php

namespace App\Http\Controllers\TechOrderSpec;

use App\Enums\WorkflowEvent;
use App\Exceptions\InvalidSpecItemException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TechOrderSpec\StoreTechOrderSpecRequest;
use App\Http\Requests\TechOrderSpec\UpdateTechOrderSpecRequest;
use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\PricingRequest;
use App\Support\StockCatalogPicker;
use App\Models\TechOrderSpec;
use App\Services\DoctorTransferService;
use App\Services\PathwayTransitionMessageService;
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
        private readonly PathwayTransitionMessageService $transitions,
        private readonly DoctorTransferService $doctorTransfer,
    ) {}

    /**
     * الحالات الواردة في مرحلة التوصيف الفني.
     */
    public function index(Request $request): JsonResponse
    {
        $range = $this->ordersService->parseDateRange($request->query('from'), $request->query('to'));
        $from = $range['from'] ?? null;
        $to = $range['to'] ?? null;
        $search = $request->query('search');

        $cases = $this->ordersService->list($from, $to, $search);
        $stats = $this->ordersService->stats($from, $to, $search);

        return response()->json([
            'data' => $cases->map(fn ($c) => $this->formatCase($c))->values(),
            'stats' => $stats,
            'date_from' => $from?->toDateString(),
            'date_to' => $to?->toDateString(),
            'export_rows' => $cases->map(fn ($c) => $this->ordersService->exportRow($c))->values(),
            'total' => $cases->count(),
        ]);
    }

    /**
     * تصدير طلبات التوصيف حسب الفلتر (CSV).
     */
    public function exportOrders(Request $request): StreamedResponse
    {
        $range = $this->ordersService->parseDateRange($request->query('from'), $request->query('to'));
        $from = $range['from'] ?? null;
        $to = $range['to'] ?? null;
        $search = $request->query('search');

        $report = $this->ordersService->exportReport($from, $to, $search);

        $suffix = ($from && $to)
            ? $from->format('Y-m-d').'_'.$to->format('Y-m-d')
            : 'all';
        $filename = 'spec-orders-'.$suffix.'.csv';

        $callback = function () use ($report) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
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
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
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

        $medicalRecord = $this->resolveMedicalRecord($case);

        $draft = TechOrderSpec::where('case_id', $case->id)
            ->where('locked', false)
            ->with('items')
            ->first();

        $submittedSpec = TechOrderSpec::where('case_id', $case->id)
            ->where('locked', true)
            ->with('items')
            ->first();

        $case->loadMissing('recommendations');
        $bootstrapCodes = $this->collectSpecCatalogCodes($medicalRecord, $draft, $submittedSpec, $case);
        $stockCatalog = StockCatalogPicker::specBootstrapRows($bootstrapCodes);

        return response()->json([
            'case' => $this->formatCase($case),
            'medical_record' => $this->formatMedicalContext($case, $medicalRecord),
            'draft' => $draft ? $this->formatSpec($draft) : null,
            'submitted_spec' => $submittedSpec ? $this->formatSpec($submittedSpec) : null,
            'stock_catalog' => $stockCatalog,
            'spec_group_matcher' => \App\Support\StockKitGroups::forClientMatcher(),
        ]);
    }

    /**
     * بحث الأصناف والأطقم من قاعدة البيانات — للنافذة المنبثقة في التوصيف.
     */
    public function searchCatalog(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        abort_if($q === '', 422, 'أدخل نص البحث.');

        $limit = min(60, max(10, (int) $request->input('limit', 40)));

        return response()->json([
            'data' => StockCatalogPicker::search($q, $limit),
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
                'message' => $e->getMessage(),
                'stock_item_code' => $e->stockItemCode,
            ], 422);
        }

        return response()->json([
            'message' => 'تم إرسال التوصيف إلى مرحلة المعدلات.',
            'case' => $this->formatCase($case->load('patient')),
            'spec' => $this->formatSpec($spec->fresh()->load('items')),
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
            'spec' => $spec,
            'case' => $spec->caseRecord,
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
            'data' => collect($requests)->map(fn ($r) => $this->formatPricingRequest($r, forSpec: true))->values(),
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
            'rework' => $case->reworkNoticeFor(CaseRecord::STAGE_TECHNICAL),
            'patient' => $case->relationLoaded('patient') ? $case->patient : null,
            'spec' => $case->relationLoaded('techOrderSpec') ? $case->techOrderSpec : null,
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
            'written_items',
            'submitted_at',
            'locked',
        ]) + [
            'items' => $spec->relationLoaded('items')
                ? $spec->items->map->only(['stock_item_code', 'name', 'qty', 'group_label'])
                : [],
            'print_url' => $spec->locked
                ? route('spec.spec.print', ['spec' => $spec->id])
                : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function collectSpecCatalogCodes(
        ?MedicalRecord $medicalRecord,
        ?TechOrderSpec $draft,
        ?TechOrderSpec $submittedSpec,
        CaseRecord $case,
    ): array {
        $codes = [];

        foreach ([$medicalRecord?->items, $draft?->items, $submittedSpec?->items, $case->recommendations] as $items) {
            if (! $items) {
                continue;
            }

            foreach ($items as $item) {
                $code = trim((string) ($item->stock_item_code ?? ''));
                if ($code !== '') {
                    $codes[] = $code;
                }
            }
        }

        return array_values(array_unique($codes));
    }

    private function resolveMedicalRecord(CaseRecord $case): ?MedicalRecord
    {
        $byCase = MedicalRecord::where('case_id', $case->id)
            ->with('items')
            ->orderByDesc('locked')
            ->orderByDesc('id')
            ->get();

        $locked = $byCase->firstWhere('locked', true);
        if ($locked) {
            return $locked;
        }

        $withDiagnosis = $byCase->first(
            fn (MedicalRecord $record) => filled(trim((string) $record->diagnosis))
        );
        if ($withDiagnosis) {
            return $withDiagnosis;
        }

        if ($byCase->isNotEmpty()) {
            return $byCase->first();
        }

        if ($case->patient_id) {
            $appointmentIds = Appointment::query()
                ->where('patient_id', $case->patient_id)
                ->orderByDesc('updated_at')
                ->limit(8)
                ->pluck('id');

            if ($appointmentIds->isNotEmpty()) {
                $byAppointment = MedicalRecord::query()
                    ->whereIn('appointment_id', $appointmentIds)
                    ->with('items')
                    ->orderByDesc('locked')
                    ->orderByDesc('id')
                    ->get()
                    ->sortBy(fn (MedicalRecord $record) => $appointmentIds->search($record->appointment_id))
                    ->values();

                $lockedAppt = $byAppointment->firstWhere('locked', true);
                if ($lockedAppt) {
                    return $lockedAppt;
                }

                $diagnosisAppt = $byAppointment->first(
                    fn (MedicalRecord $record) => filled(trim((string) $record->diagnosis))
                );
                if ($diagnosisAppt) {
                    return $diagnosisAppt;
                }
            }

            return MedicalRecord::where('patient_id', $case->patient_id)
                ->where('locked', true)
                ->with('items')
                ->latest('id')
                ->first();
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function formatMedicalContext(CaseRecord $case, ?MedicalRecord $record): array
    {
        $case->loadMissing(['patient', 'recommendations']);

        $recommendations = $this->doctorTransfer->resolveRecommendations($case, $record);
        $transferMessage = $this->transitions->transferMessage(
            $case,
            WorkflowEvent::ExamApproved->value,
            CaseRecord::STAGE_EXAM,
        );

        $items = $record?->items?->map->only(['stock_item_code', 'name', 'qty']) ?? collect();
        $doctorMessage = $this->buildDoctorMessage($record);

        return [
            'diagnosis' => $record?->diagnosis,
            'prescription' => $record?->prescription,
            'doctor_name' => $record?->doctor_name,
            'doctor_message' => $doctorMessage,
            'transfer_message' => $transferMessage,
            'has_clinical_notes' => $doctorMessage !== null,
            'items' => $items->values()->all(),
            'recommendations' => $recommendations,
        ];
    }

    private function buildDoctorMessage(?MedicalRecord $record): ?string
    {
        if (! $record) {
            return null;
        }

        $parts = array_values(array_filter([
            filled(trim((string) $record->diagnosis)) ? trim((string) $record->diagnosis) : null,
            filled(trim((string) $record->prescription)) ? trim((string) $record->prescription) : null,
        ]));

        return $parts === [] ? null : implode("\n\n", $parts);
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
