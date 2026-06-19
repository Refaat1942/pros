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
use App\Services\SpecService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechOrderSpecController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly SpecService $specService)
    {
    }

    /**
     * الحالات الواردة في مرحلة التوصيف الفني.
     */
    public function index(Request $request): JsonResponse
    {
        $cases = $this->fetchForDashboard(
            CaseRecord::with([
                'patient:id,patient_code,name,patient_type',
                'techOrderSpec:id,case_id,locked,submitted_at',
            ])
                ->where('stage_key', CaseRecord::STAGE_TECHNICAL)
                ->when($request->search, fn ($q, $s) => $q->whereHas(
                    'patient',
                    fn ($q) => $q->where('name', 'like', "%{$s}%")
                        ->orWhere('patient_code', 'like', "%{$s}%")
                ))
                ->orderByDesc('created_at')
        );

        return response()->json([
            'data'  => collect($cases)->map(fn ($c) => $this->formatCase($c))->values(),
            'total' => $cases->count(),
        ]);
    }

    /**
     * نموذج التوصيف — بيانات الحالة + catalog الأصناف (بدون qty/أسعار).
     */
    public function create(CaseRecord $case): JsonResponse
    {
        abort_unless($case->stage_key === CaseRecord::STAGE_TECHNICAL, 422, 'الحالة ليست في مرحلة التوصيف الفني.');

        $case->load('patient:id,patient_code,name,patient_type,company_name');

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
            ->get(['code', 'name', 'spec', 'category', 'uom']);

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
            $pricingRequest = $this->specService->submit($spec);
        } catch (InvalidSpecItemException $e) {
            return response()->json([
                'message'          => $e->getMessage(),
                'stock_item_code'  => $e->stockItemCode,
            ], 422);
        }

        return response()->json([
            'pricing_request' => $this->formatPricingRequest($pricingRequest, forSpec: false),
            'spec'            => $this->formatSpec($spec->fresh()->load('items')),
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
     * حالات أُرسل توصيفها — مع حالة طلب التسعير.
     */
    public function pricingStatus(Request $request): JsonResponse
    {
        $requests = $this->fetchForDashboard(
            PricingRequest::with([
                'caseRecord:id,case_no,order_ref,stage_key,patient_type',
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
        ];
    }

    private function formatPricingRequest(PricingRequest $request, bool $forSpec = false): array
    {
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
        ]) + [
            'items' => $request->relationLoaded('items')
                ? $request->items->map->only(['stock_item_code', 'name', 'qty'])
                : [],
            'case' => $request->relationLoaded('caseRecord') && $request->caseRecord
                ? $request->caseRecord->only(['id', 'case_no', 'order_ref', 'stage_key', 'patient_type'])
                : null,
        ];

        if (! $forSpec) {
            $data['computed_total'] = $request->computed_total;
        }

        return $data;
    }
}
