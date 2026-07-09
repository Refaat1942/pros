<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ContractCompany;
use App\Models\MilitaryRank;
use App\Models\Patient;
use App\Models\VisitType;
use App\Services\Notifications\NotificationService;
use App\Support\ClinicTime;
use Illuminate\Support\Facades\DB;

/**
 * حجز المواعيد وتحديث حالتها — يغذّي طابور الطبيب.
 */
class AppointmentService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function book(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $payload = $this->resolvePatientFields($data);
            $payload = $this->assignQueueNumber($payload);

            $appointment = Appointment::create($payload);

            AuditService::log(
                action: 'create',
                description: "حجز موعد {$payload['patient_name']} — {$payload['appointment_date']}",
                tag: 'patients',
                after: $appointment->toArray(),
            );

            return $appointment->load('patient');
        });
    }

    public function correctReceptionEntry(Appointment $appointment, array $data): Appointment
    {
        $this->assertReceptionEditable($appointment);

        return DB::transaction(function () use ($appointment, $data) {
            $visitType = VisitType::query()->findOrFail($data['visit_type_id']);
            $before = $appointment->toArray();

            $companyName = $appointment->company_name;
            if ($appointment->isMilitary()) {
                $rankName = MilitaryRank::query()->whereKey($data['military_rank_id'] ?? null)->value('name');
                $companyName = $rankName ?: $appointment->company_name;
            } elseif (! empty($data['contract_company_id'])) {
                $companyName = ContractCompany::query()->whereKey($data['contract_company_id'])->value('name');
            } else {
                $companyName = null;
            }

            $appointment->update([
                'patient_name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'visit_type_id' => $visitType->id,
                'visit_type' => $visitType->name,
                'company_name' => $companyName,
            ]);

            if ($appointment->patient_id) {
                $patientUpdates = [
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'national_id' => $data['national_id'] ?? null,
                ];

                if ($appointment->isMilitary()) {
                    $patientUpdates['military_rank_id'] = $data['military_rank_id'] ?? null;
                    $patientUpdates['military_number'] = $data['military_number'] ?? null;
                    $patientUpdates['seniority_number'] = $data['seniority_number'] ?? null;
                    $patientUpdates['military_weapon'] = $data['military_weapon'] ?? null;
                    $patientUpdates['rank'] = MilitaryRank::query()
                        ->whereKey($data['military_rank_id'] ?? null)
                        ->value('name');
                    $patientUpdates['contract_company_id'] = null;
                    $patientUpdates['company_name'] = null;
                } else {
                    $patientUpdates['contract_company_id'] = $data['contract_company_id'] ?? null;
                    $patientUpdates['company_name'] = ! empty($data['contract_company_id'])
                        ? ContractCompany::query()->whereKey($data['contract_company_id'])->value('name')
                        : null;
                }

                Patient::query()->whereKey($appointment->patient_id)->update($patientUpdates);
            }

            AuditService::log(
                action: 'update',
                description: "تصحيح بيانات موعد #{$appointment->id} — {$data['name']}",
                tag: 'patients',
                before: $before,
                after: $appointment->fresh()->toArray(),
            );

            return $appointment->fresh()->load([
                'patient:id,patient_code,name,patient_type,rank,created_at,contract_company_id,company_name,sovereign_entity,national_id,military_rank_id,military_number,seniority_number,military_weapon',
                'patient.contractCompany:id,name,is_contracted',
                'visitTypeRecord:id,name',
            ]);
        });
    }

    public function removeReceptionEntry(Appointment $appointment): void
    {
        $this->assertReceptionEditable($appointment);

        DB::transaction(function () use ($appointment) {
            $patient = $appointment->patient;
            $snapshot = $appointment->toArray();

            $appointment->delete();

            AuditService::log(
                action: 'delete',
                description: "حذف موعد استقبال #{$snapshot['id']} — {$snapshot['patient_name']}",
                tag: 'patients',
                before: $snapshot,
            );

            if (! $patient) {
                return;
            }

            $hasOtherAppointments = $patient->appointments()->exists();
            $hasCases = $patient->cases()->exists();
            $hasRecords = $patient->medicalRecords()->exists();

            if ($hasOtherAppointments || $hasCases || $hasRecords) {
                return;
            }

            $patientSnapshot = $patient->only(['id', 'patient_code', 'name']);
            $patient->delete();

            AuditService::log(
                action: 'delete',
                description: "حذف ملف مريض {$patientSnapshot['patient_code']} — {$patientSnapshot['name']} (بيانات خاطئة)",
                tag: 'patients',
                before: $patientSnapshot,
            );
        });
    }

    public function reschedule(Appointment $appointment, array $data): Appointment
    {
        return DB::transaction(function () use ($appointment, $data) {
            $before = $appointment->only([
                'appointment_date',
                'appointment_time',
                'visit_type',
            ]);

            $appointment->update(array_intersect_key($data, array_flip([
                'appointment_date',
                'appointment_time',
                'visit_type',
            ])));

            AuditService::log(
                action: 'update',
                description: "تعديل موعد #{$appointment->id}",
                tag: 'patients',
                before: $before,
                after: $appointment->only(['appointment_date', 'appointment_time', 'visit_type']),
            );

            return $appointment->fresh()->load('patient');
        });
    }

    /**
     * تقدّم حالة الموعد: waiting → in_clinic → done
     */
    public function advanceStatus(Appointment $appointment, string $status): Appointment
    {
        $allowed = [
            Appointment::STATUS_WAITING => [Appointment::STATUS_IN_CLINIC],
            Appointment::STATUS_IN_CLINIC => [Appointment::STATUS_DONE],
            Appointment::STATUS_QUOTED => [Appointment::STATUS_DONE],
        ];

        $current = $appointment->status;

        if (! isset($allowed[$current]) || ! in_array($status, $allowed[$current], true)) {
            throw new \InvalidArgumentException(
                "لا يمكن تغيير حالة الموعد من «{$current}» إلى «{$status}»."
            );
        }

        return DB::transaction(function () use ($appointment, $status) {
            $before = ['status' => $appointment->status];
            $notifyDoctor = $status === Appointment::STATUS_IN_CLINIC
                && $before['status'] === Appointment::STATUS_WAITING;

            $updates = [
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'transferred_to_clinic' => $status === Appointment::STATUS_IN_CLINIC
                    || $appointment->transferred_to_clinic,
            ];

            if ($status === Appointment::STATUS_IN_CLINIC && ! $appointment->transferred_to_clinic_at) {
                $updates['transferred_to_clinic_at'] = now();
            }

            $appointment->update($updates);

            if ($appointment->patient_id) {
                Patient::where('id', $appointment->patient_id)
                    ->update(['last_visit_at' => ClinicTime::todayDateString()]);
            }

            AuditService::log(
                action: 'update',
                description: "تحديث حالة موعد #{$appointment->id} → {$status}",
                tag: 'patients',
                before: $before,
                after: ['status' => $appointment->status],
            );

            $appointment = $appointment->fresh()->load('patient');

            if ($notifyDoctor) {
                $this->notifications->notifyDoctorClinicTransfer($appointment);
            }

            return $appointment;
        });
    }

    private function assignQueueNumber(array $payload): array
    {
        if (! empty($payload['queue_number']) && ! empty($payload['clinic_day'])) {
            return $payload;
        }

        $clinicDay = ClinicTime::clinicDayDateString();

        $next = (int) Appointment::query()
            ->whereDate('clinic_day', $clinicDay)
            ->lockForUpdate()
            ->max('queue_number');

        $payload['clinic_day'] = $clinicDay;
        $payload['queue_number'] = $next + 1;

        if (empty($payload['appointment_date'])) {
            $payload['appointment_date'] = $clinicDay;
        }

        return $payload;
    }

    private function resolvePatientFields(array $data): array
    {
        $visitFields = $this->resolveVisitTypeFields($data);

        if (! empty($data['patient_id'])) {
            $patient = Patient::with('contractCompany')->findOrFail($data['patient_id']);

            return [
                'patient_id' => $patient->id,
                'appointment_date' => $data['appointment_date'] ?? ClinicTime::clinicDayDateString(),
                'appointment_time' => $data['appointment_time'] ?? ClinicTime::now()->format('H:i'),
                ...$visitFields,
                'patient_name' => $patient->name,
                'phone' => $patient->phone,
                'company_name' => $patient->isMilitary()
                    ? $patient->displayEntity()
                    : $patient->company_name,
                'patient_type' => $patient->patient_type,
                'status' => Appointment::STATUS_WAITING,
                'status_label' => $this->statusLabel(Appointment::STATUS_WAITING),
                'transferred_to_clinic' => false,
                'clinic_day' => $data['clinic_day'] ?? null,
                'queue_number' => $data['queue_number'] ?? null,
            ];
        }

        return [
            'patient_id' => null,
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'] ?? now()->format('H:i'),
            ...$visitFields,
            'patient_name' => $data['patient_name'],
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'patient_type' => $data['patient_type'] ?? Patient::TYPE_CIVILIAN,
            'status' => Appointment::STATUS_WAITING,
            'status_label' => $this->statusLabel(Appointment::STATUS_WAITING),
            'transferred_to_clinic' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{visit_type_id: int, visit_type: string}
     */
    private function resolveVisitTypeFields(array $data): array
    {
        $visitType = VisitType::query()->find($data['visit_type_id'] ?? null);

        if (! $visitType) {
            abort(422, 'نوع الزيارة غير صالح.');
        }

        return [
            'visit_type_id' => $visitType->id,
            'visit_type' => $visitType->name,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Appointment::STATUS_WAITING => 'في الانتظار',
            Appointment::STATUS_IN_CLINIC => 'في العيادة',
            Appointment::STATUS_QUOTED => 'تم التسعير',
            Appointment::STATUS_DONE => 'منتهٍ',
            default => $status,
        };
    }

    private function assertReceptionEditable(Appointment $appointment): void
    {
        if (! $appointment->isReceptionEditable()) {
            abort(422, 'لا يمكن تعديل أو حذف الموعد بعد تحويله للعيادة.');
        }
    }
}
