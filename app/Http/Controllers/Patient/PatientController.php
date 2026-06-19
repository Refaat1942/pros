<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\Patient;
use App\Services\PatientService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly PatientService $patientService)
    {
    }

    /**
     * قائمة المرضى — مرشَّحة ومُرقَّمة (بدون حقول مالية).
     */
    public function index(Request $request): JsonResponse
    {
        $patients = Patient::with('contractCompany:id,name,company_code,is_military')
            ->when($request->patient_type, fn ($q, $t) => $q->where('patient_type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->contract_company_id, fn ($q, $id) => $q->where('contract_company_id', $id))
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('patient_code', 'like', "%{$search}%")
                  ->orWhere('national_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('registered_at')
            ->paginate(20);

        return response()->json([
            'data'       => $patients->items(),
            'pagination' => $this->paginationModel($patients),
        ]);
    }

    /**
     * تسجيل مريض جديد.
     */
    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = $this->patientService->register($request->validated());

        return response()->json($this->formatPatientCard($patient), 201);
    }

    /**
     * بطاقة المريض مع QR وملخص آخر حالة (بدون أسعار).
     */
    public function show(Patient $patient): JsonResponse
    {
        $patient->load([
            'contractCompany:id,name,company_code,is_military',
            'cases' => fn ($q) => $q->latest()->limit(1),
        ]);

        $latestCase = $patient->cases->first();

        return response()->json([
            ...$this->formatPatientCard($patient),
            'latest_case' => $latestCase ? [
                'case_no'             => $latestCase->case_no,
                'stage_key'           => $latestCase->stage_key,
                'manufacturing_stage' => $latestCase->manufacturing_stage,
                'delivered_at'        => $latestCase->delivered_at?->toDateString(),
            ] : null,
        ]);
    }

    /**
     * تعديل الهاتف أو جهة التعاقد فقط.
     */
    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        $patient = $this->patientService->update($patient, $request->validated());

        return response()->json($this->formatPatientCard($patient));
    }

    /**
     * تنسيق بطاقة المريض — بدون أي حقول مالية.
     */
    private function formatPatientCard(Patient $patient): array
    {
        return $patient->only([
            'id',
            'patient_code',
            'patient_qr',
            'name',
            'phone',
            'national_id',
            'patient_type',
            'rank',
            'sovereign_entity',
            'contract_company_id',
            'company_name',
            'registered_at',
            'last_visit_at',
            'status',
        ]) + [
            'contract_company' => $patient->relationLoaded('contractCompany')
                ? $patient->contractCompany
                : null,
        ];
    }
}
