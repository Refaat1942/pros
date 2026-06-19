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
use Illuminate\Http\RedirectResponse;
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

        $baseQuery = Appointment::query()
            ->whereDate('appointment_date', $date)
            ->where('transferred_to_clinic', true);

        $appointments = $this->fetchForDashboard(
            (clone $baseQuery)
                ->with('patient:id,patient_code,name,national_id,patient_type,company_name,created_at')
                ->where('status', Appointment::STATUS_IN_CLINIC)
                ->orderByDesc('transferred_to_clinic_at')
                ->orderByDesc('id')
        );

        $examinedCount = (clone $baseQuery)
            ->where('status', Appointment::STATUS_DONE)
            ->count();

        return response()->json([
            'date'            => $date,
            'data'            => collect($appointments)->map(fn (Appointment $a) => $this->formatQueueAppointment($a))->values(),
            'total'           => $appointments->count(),
            'waiting_count'   => $appointments->count(),
            'examined_count'  => $examinedCount,
            'today_total'     => $appointments->count() + $examinedCount,
        ]);
    }

    /**
     * بيانات نموذج الكشف — مريض + موعد (بدون حقول مالية).
     */
    public function create(Appointment $appointment): JsonResponse
    {
        abort_unless(
            $appointment->status === Appointment::STATUS_IN_CLINIC && $appointment->transferred_to_clinic,
            422,
            'يجب تحويل المريض من الاستقبال قبل بدء الكشف.'
        );

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
    public function store(StoreMedicalRecordRequest $request): RedirectResponse|JsonResponse
    {
        $record = $this->medicalRecordService->saveDraft($request->validated());

        if ($request->boolean('lock')) {
            $record = $this->medicalRecordService->lock($record);
        }

        if ($request->expectsJson()) {
            return response()->json($this->formatRecord($record), 201);
        }

        return redirect()
            ->route('doctor.queue')
            ->with('success', 'تم حفظ واعتماد التقرير الطبي وتحويل الحالة للتوصيف الفني.');
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
        $records = $this->fetchForDashboard(
            MedicalRecord::with('items')
                ->where('locked', true)
                ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                    $q->where('patient_name', 'like', "%{$s}%")
                      ->orWhere('national_id', 'like', "%{$s}%");
                }))
                ->orderByDesc('record_date')
        );

        return response()->json([
            'data'  => collect($records)->map(fn ($r) => $this->formatRecord($r))->values(),
            'total' => $records->count(),
        ]);
    }

    /**
     * الحالات المحوّلة للتوصيف الفني.
     */
    public function transfers(Request $request): JsonResponse
    {
        $cases = $this->fetchForDashboard(
            CaseRecord::with([
                'patient:id,patient_code,name,patient_type',
                'medicalRecords' => fn ($q) => $q->where('locked', true)->latest()->limit(1),
            ])
                ->where('stage_key', CaseRecord::STAGE_TECHNICAL)
                ->when($request->search, fn ($q, $s) => $q->whereHas(
                    'patient',
                    fn ($q) => $q->where('name', 'like', "%{$s}%")
                ))
                ->orderByDesc('created_at')
        );

        return response()->json([
            'data'  => collect($cases)->map(fn ($c) => $this->formatCaseForDoctor($c))->values(),
            'total' => $cases->count(),
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

    private function formatQueueAppointment(Appointment $appointment): array
    {
        return $appointment->only([
            'id',
            'patient_id',
            'patient_name',
            'company_name',
            'patient_type',
            'status',
            'transferred_to_clinic',
            'transferred_to_clinic_at',
        ]) + [
            'transferred_at' => $appointment->transferredAt()?->toIso8601String(),
            'wait_label'     => $appointment->receptionWaitLabel(),
            'patient'        => $appointment->relationLoaded('patient') && $appointment->patient
                ? $appointment->patient->only(['id', 'patient_code', 'name', 'national_id', 'patient_type', 'company_name'])
                : null,
        ];
    }
}
