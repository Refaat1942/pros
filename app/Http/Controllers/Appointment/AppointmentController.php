<?php

namespace App\Http\Controllers\Appointment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Support\ClinicTime;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly AppointmentService $appointmentService)
    {
    }

    /**
     * قائمة المواعيد — افتراضياً مواعيد اليوم.
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->date ?? ClinicTime::todayDateString();

        $query = Appointment::with([
            'patient:id,patient_code,name,patient_type,rank,created_at',
            'visitTypeRecord:id,name',
        ])
            ->whereDate('appointment_date', $date)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->visit_type, fn ($q, $t) => $q->where('visit_type', $t))
            ->when($request->patient_type, fn ($q, $t) => $q->whereHas(
                'patient',
                fn ($p) => $p->where('patient_type', $t)
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $appointments = $this->fetchForDashboard($query);

        return response()->json([
            'date'  => $date,
            'data'  => collect($appointments)->map(fn (Appointment $a) => $this->formatForReceptionList($a))->values(),
            'total' => $appointments->count(),
        ]);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $appointment = $this->appointmentService->book($request->validated());

        return response()->json($this->formatForReceptionList($appointment->load([
            'patient:id,patient_code,name,patient_type,rank,created_at',
            'visitTypeRecord:id,name',
        ])), 201);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $appointment = $this->appointmentService->reschedule($appointment, $request->validated());

        return response()->json($appointment);
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): JsonResponse
    {
        try {
            $appointment = $this->appointmentService->advanceStatus(
                $appointment,
                $request->validated('status')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->formatForReceptionList($appointment->load([
            'patient:id,patient_code,name,patient_type,rank,created_at',
            'visitTypeRecord:id,name',
        ])));
    }

    private function formatForReceptionList(Appointment $appointment): array
    {
        return $appointment->toArray() + [
            'queue_number'            => $appointment->patient_id,
            'patient_type'            => $appointment->patient_type ?? $appointment->patient?->patient_type,
            'patient_type_label'      => ($appointment->patient_type ?? $appointment->patient?->patient_type) === Patient::TYPE_MILITARY ? 'عسكري' : 'مدني',
            'registered_at_formatted' => $appointment->registeredAtFormatted(),
            'wait_label'              => $appointment->receptionDeskWaitLabel(),
            'wait_started_at'         => $appointment->registrationMoment()?->toIso8601String(),
            'wait_frozen_at'          => ($appointment->transferred_to_clinic && $appointment->transferredAt())
                ? $appointment->transferredAt()->toIso8601String()
                : null,
        ];
    }
}
