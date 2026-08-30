<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;

/** سياق التقرير الطبي — تشخيص، وصفة، توصيات. */
class MedicalContextService
{
    public function __construct(private readonly DoctorTransferService $doctorTransfer) {}

    public function resolveRecord(CaseRecord $case): ?MedicalRecord
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
    public function formatForCase(CaseRecord $case): array
    {
        $case->loadMissing(['patient', 'recommendations', 'techOrderSpec']);
        $record = $this->resolveRecord($case);
        $doctorMessage = $this->buildDoctorMessage($record);

        return [
            'diagnosis' => $record?->diagnosis,
            'prescription' => $record?->prescription,
            'doctor_name' => $record?->doctor_name ?? $case->techOrderSpec?->doctor_name,
            'doctor_message' => $doctorMessage,
            'has_clinical_notes' => $doctorMessage !== null,
            'recommendations' => $this->doctorTransfer->resolveRecommendations($case, $record),
            'items' => $record?->items?->map->only(['stock_item_code', 'name', 'qty'])->values()->all() ?? [],
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
}
