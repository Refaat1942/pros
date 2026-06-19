<?php

namespace App\Http\Controllers\MedicalRecord;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicalRecord\StoreMedicalRecordRequest;
use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Services\MedicalRecordService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly MedicalRecordService $medicalRecordService)
    {
    }

    /**
     * قائمة انتظار العيادة — مواعيد اليوم.
     */
    public function queue(Request $request): JsonResponse
    {
        $date = $request->date ?? now()->toDateString();

        $appointments = Appointment::with('patient:id,patient_code,name,national_id,patient_type,company_name')
            ->whereDate('appointment_date', $date)
            ->whereIn('status', [
                Appointment::STATUS_WAITING,
                Appointment::STATUS_IN_CLINIC,
            ])
            ->orderBy('appointment_time')
            ->orderBy('id')
            ->paginate(50);

        return response()->json([
            'date'       => $date,
            'data'       => $appointments->items(),
            'pagination' => $this->paginationModel($appointments),
        ]);
    }

    /**
     * بيانات نموذج الكشف — مريض + موعد (بدون حقول مالية).
     */
    public function create(Appointment $appointment): JsonResponse
    {
        $appointment->load('patient:id,patient_code,name,national_id,patient_type,company_name,phone');

        $draft = MedicalRecord::where('appointment_id', $appointment->id)
            ->where('locked', false)
            ->with('items')
            ->first();

        return response()->json([
            'appointment' => $appointment,
            'draft'       => $draft,
        ]);
    }

    /**
     * حفظ مسودة التقرير الطبي.
     */
    public function store(StoreMedicalRecordRequest $request): JsonResponse
    {
        $record = $this->medicalRecordService->saveDraft($request->validated());

        return response()->json($this->formatRecord($record), 201);
    }

    /**
     * اعتماد التقرير — قفل + إنشاء حالة + تحويل للتوصيف الفني.
     */
    public function lock(MedicalRecord $record): JsonResponse
    {
        $record = $this->medicalRecordService->lock($record);

        return response()->json([
            'record' => $this->formatRecord($record),
            'case'   => $this->formatCaseForDoctor($record->caseRecord),
        ]);
    }

    /**
     * أرشيف التقارير المعتمدة.
     */
    public function index(Request $request): JsonResponse
    {
        $records = MedicalRecord::with('items')
            ->where('locked', true)
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('patient_name', 'like', "%{$s}%")
                  ->orWhere('national_id', 'like', "%{$s}%");
            }))
            ->orderByDesc('record_date')
            ->paginate(20);

        return response()->json([
            'data'       => collect($records->items())->map(fn ($r) => $this->formatRecord($r)),
            'pagination' => $this->paginationModel($records),
        ]);
    }

    /**
     * الحالات المحوّلة للتوصيف الفني.
     */
    public function transfers(Request $request): JsonResponse
    {
        $cases = CaseRecord::with([
            'patient:id,patient_code,name,patient_type',
            'medicalRecords' => fn ($q) => $q->where('locked', true)->latest()->limit(1),
        ])
            ->where('stage_key', CaseRecord::STAGE_TECHNICAL)
            ->when($request->search, fn ($q, $s) => $q->whereHas(
                'patient',
                fn ($q) => $q->where('name', 'like', "%{$s}%")
            ))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data'       => collect($cases->items())->map(fn ($c) => $this->formatCaseForDoctor($c)),
            'pagination' => $this->paginationModel($cases),
        ]);
    }

    private function formatRecord(MedicalRecord $record): array
    {
        return $record->only([
            'id',
            'patient_id',
            'appointment_id',
            'case_id',
            'patient_name',
            'national_id',
            'company_name',
            'patient_type',
            'diagnosis',
            'prescription',
            'doctor_name',
            'record_date',
            'status',
            'locked',
        ]) + [
            'items' => $record->relationLoaded('items')
                ? $record->items->map->only(['stock_item_code', 'name', 'qty'])
                : [],
        ];
    }

    /**
     * تنسيق الحالة للطبيب — بدون quote_total / total_cost / paid.
     */
    private function formatCaseForDoctor(?CaseRecord $case): ?array
    {
        if (! $case) {
            return null;
        }

        return $case->only([
            'id',
            'case_no',
            'order_ref',
            'patient_id',
            'patient_type',
            'path',
            'stage_key',
            'manufacturing_stage',
            'company_name',
            'rank',
            'sovereign_entity',
            'created_at',
        ]);
    }
}
