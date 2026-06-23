<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\VisitType;
use Illuminate\Support\Facades\DB;

/**
 * حجز المواعيد وتحديث حالتها — يغذّي طابور الطبيب.
 */
class AppointmentService
{
    public function book(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $payload = $this->resolvePatientFields($data);

            $appointment = Appointment::create($payload);

            AuditService::log(
                action:      'create',
                description: "حجز موعد {$payload['patient_name']} — {$payload['appointment_date']}",
                tag:         'patients',
                after:       $appointment->toArray(),
            );

            return $appointment->load('patient');
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
                action:      'update',
                description: "تعديل موعد #{$appointment->id}",
                tag:         'patients',
                before:      $before,
                after:       $appointment->only(['appointment_date', 'appointment_time', 'visit_type']),
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
            Appointment::STATUS_WAITING   => [Appointment::STATUS_IN_CLINIC],
            Appointment::STATUS_IN_CLINIC => [Appointment::STATUS_DONE],
            Appointment::STATUS_QUOTED      => [Appointment::STATUS_DONE],
        ];

        $current = $appointment->status;

        if (! isset($allowed[$current]) || ! in_array($status, $allowed[$current], true)) {
            throw new \InvalidArgumentException(
                "لا يمكن تغيير حالة الموعد من «{$current}» إلى «{$status}»."
            );
        }

        return DB::transaction(function () use ($appointment, $status) {
            $before = ['status' => $appointment->status];

            $updates = [
                'status'                => $status,
                'status_label'          => $this->statusLabel($status),
                'transferred_to_clinic' => $status === Appointment::STATUS_IN_CLINIC
                    || $appointment->transferred_to_clinic,
            ];

            if ($status === Appointment::STATUS_IN_CLINIC && ! $appointment->transferred_to_clinic_at) {
                $updates['transferred_to_clinic_at'] = now();
            }

            $appointment->update($updates);

            if ($appointment->patient_id) {
                Patient::where('id', $appointment->patient_id)
                    ->update(['last_visit_at' => now()->toDateString()]);
            }

            AuditService::log(
                action:      'update',
                description: "تحديث حالة موعد #{$appointment->id} → {$status}",
                tag:         'patients',
                before:      $before,
                after:       ['status' => $appointment->status],
            );

            return $appointment->fresh()->load('patient');
        });
    }

    private function resolvePatientFields(array $data): array
    {
        $visitFields = $this->resolveVisitTypeFields($data);

        if (! empty($data['patient_id'])) {
            $patient = Patient::with('contractCompany')->findOrFail($data['patient_id']);

            return [
                'patient_id'        => $patient->id,
                'appointment_date'  => $data['appointment_date'],
                'appointment_time'  => $data['appointment_time'] ?? now()->format('H:i'),
                ...$visitFields,
                'patient_name'      => $patient->name,
                'phone'             => $patient->phone,
                'company_name'      => $patient->isMilitary()
                    ? $patient->displayEntity()
                    : $patient->company_name,
                'patient_type'      => $patient->patient_type,
                'status'            => Appointment::STATUS_WAITING,
                'status_label'      => $this->statusLabel(Appointment::STATUS_WAITING),
                'transferred_to_clinic' => false,
            ];
        }

        return [
            'patient_id'        => null,
            'appointment_date'  => $data['appointment_date'],
            'appointment_time'  => $data['appointment_time'] ?? now()->format('H:i'),
            ...$visitFields,
            'patient_name'      => $data['patient_name'],
            'phone'             => $data['phone'] ?? null,
            'company_name'      => $data['company_name'] ?? null,
            'patient_type'      => $data['patient_type'] ?? Patient::TYPE_CIVILIAN,
            'status'            => Appointment::STATUS_WAITING,
            'status_label'      => $this->statusLabel(Appointment::STATUS_WAITING),
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
            'visit_type'    => (string) $visitType->id,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Appointment::STATUS_WAITING   => 'في الانتظار',
            Appointment::STATUS_IN_CLINIC => 'في العيادة',
            Appointment::STATUS_QUOTED    => 'تم التسعير',
            Appointment::STATUS_DONE      => 'منتهٍ',
            default                       => $status,
        };
    }
}
