<?php

namespace App\Services;

use App\Enums\CaseStage;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\VisitType;
use App\Support\PatientEntityPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * مسار المرضى النشطين — لوحة نظرة عامة للإدارة (شريط تقدم + مرحلة حالية).
 */
class AdminPatientTrackService
{
    public function __construct(
        private readonly PublicTrackingService $publicTracking,
        private readonly AdminPatientJourneyService $journey,
        private readonly PathwayConfigService $pathwayConfig,
    ) {}

    /** @return list<array{value: string, label: string}> */
    public static function stageFilterOptions(): array
    {
        return array_map(
            fn (CaseStage $stage) => ['value' => $stage->value, 'label' => $stage->label()],
            CaseStage::cases()
        );
    }

    /** @return list<array{value: int, label: string}> */
    public static function visitFilterOptions(): array
    {
        return VisitType::query()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (VisitType $type) => ['value' => $type->id, 'label' => $type->name])
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function list(
        ?string $search = null,
        ?string $stage = null,
        ?string $patientType = null,
        ?string $visitType = null,
        int $limit = 100,
    ): Collection {
        $term = trim((string) $search);
        $stageFilter = trim((string) $stage);
        $typeFilter = trim((string) $patientType);
        $visitTypeId = trim((string) $visitType) !== '' ? (int) $visitType : null;
        $visitTypeName = $visitTypeId
            ? VisitType::query()->whereKey($visitTypeId)->value('name')
            : null;

        $patients = Patient::query()
            ->with([
                'contractCompany:id,name,company_code,is_military,is_contracted',
                'militaryRank:id,name',
                'cases' => fn ($q) => $q
                    ->with([
                        'quotes.items',
                        'pricingRequest',
                        'bom.items',
                        'techOrderSpec.items',
                        'medicalRecords',
                    ])
                    ->orderByDesc('id'),
                'appointments' => fn ($q) => $q
                    ->orderByDesc('appointment_date')
                    ->orderByDesc('id'),
                'appointments.visitTypeRecord:id,name',
            ])
            ->where(function (Builder $q) {
                $q->whereHas('cases')
                    ->orWhereHas('appointments', fn (Builder $a) => $a
                        ->whereIn('status', [Appointment::STATUS_WAITING, Appointment::STATUS_IN_CLINIC])
                        ->whereDate('appointment_date', '<=', now()));
            })
            ->when($typeFilter === Patient::TYPE_CIVILIAN, fn (Builder $q) => $q->where('patient_type', Patient::TYPE_CIVILIAN))
            ->when($typeFilter === Patient::TYPE_MILITARY, fn (Builder $q) => $q->where('patient_type', Patient::TYPE_MILITARY))
            ->when($visitTypeId, fn (Builder $q) => $q->whereHas('appointments', fn (Builder $a) => $a->where(function (Builder $sub) use ($visitTypeId, $visitTypeName) {
                $sub->where('visit_type_id', $visitTypeId);
                if ($visitTypeName) {
                    $sub->orWhere('visit_type', $visitTypeName);
                }
            })))
            ->when($term !== '', fn (Builder $q) => $this->applySearch($q, $term))
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $registrationAudits = $this->journey->registrationAuditsForPatients($patients);

        return $patients
            ->map(fn (Patient $patient) => $this->formatTrack(
                $patient,
                $registrationAudits[$patient->id] ?? null,
            ))
            ->when(
                $stageFilter !== '',
                fn (Collection $tracks) => $tracks->filter(
                    fn (array $track) => ($track['stage_key'] ?? '') === $stageFilter
                )
            )
            ->when(
                $visitTypeId,
                fn (Collection $tracks) => $tracks->filter(
                    fn (array $track) => (int) ($track['visit_type_id'] ?? 0) === $visitTypeId
                )
            )
            ->values();
    }

    /** @return array<string, mixed> */
    private function formatTrack(Patient $patient, ?AuditLog $registrationAudit = null): array
    {
        $activeCase = $patient->cases->first(
            fn (CaseRecord $case) => $case->stage_key !== CaseRecord::STAGE_DELIVERED
        ) ?? $patient->cases->first();
        $appointment = $this->activeAppointment($patient);
        $visitAppointment = $patient->appointments->first();
        $visitTypeId = $this->resolveAppointmentVisitTypeId($visitAppointment);
        $uid = $patient->tracking_uid ?: $activeCase?->tracking_uid;

        $tracking = $uid
            ? $this->publicTracking->resolve($uid)
            : $this->fallbackTracking($patient, $activeCase, $appointment);

        if (! $activeCase && $appointment) {
            $tracking['stage_label'] = match ($appointment->status) {
                Appointment::STATUS_WAITING => 'في الاستقبال — بانتظار التحويل للعيادة',
                Appointment::STATUS_IN_CLINIC => 'في العيادة',
                default => $tracking['stage_label'],
            };

            if ($appointment->status === Appointment::STATUS_IN_CLINIC && ($tracking['current_index'] ?? 0) < 1) {
                $tracking['current_index'] = 1;
                $tracking['steps'] = $this->remapStepStatuses($tracking['steps'], 1);
            }
        }

        $steps = $tracking['steps'];
        $currentIndex = (int) ($tracking['current_index'] ?? 0);
        $totalSteps = count($steps);
        $progressPercent = $totalSteps > 1
            ? (int) round(($currentIndex / ($totalSteps - 1)) * 100)
            : 0;

        $entity = PatientEntityPresenter::forPatient($patient);

        return [
            'id' => $patient->id,
            'name' => $patient->name,
            'phone' => $patient->phone,
            'national_id' => $patient->national_id,
            'patient_type' => $patient->patient_type,
            'company_name' => $entity['label'],
            'entity' => $entity,
            'case_no' => $activeCase?->case_no,
            'stage_key' => $this->resolveStageKey($activeCase, $appointment),
            'pathway' => $tracking['pathway'],
            'pathway_label' => app(PathwayConfigService::class)->pathwayLabel($tracking['pathway']),
            'stage_label' => $tracking['stage_label'],
            'progress_percent' => $progressPercent,
            'steps' => $steps,
            'current_index' => $currentIndex,
            'search_hay' => mb_strtolower(trim(
                ($patient->name ?? '').' '.($patient->phone ?? '').' '.($patient->national_id ?? '')
            )),
            'visit_type_id' => $visitTypeId,
            'visit_type_label' => $visitAppointment?->displayVisitType(),
            'journey' => $this->journey->build($patient, $activeCase, $registrationAudit),
            'patient_details' => $this->formatPatientDetails($patient, $activeCase, $tracking),
        ];
    }

    /** @return array<string, mixed> */
    private function formatPatientDetails(Patient $patient, ?CaseRecord $activeCase, array $tracking): array
    {
        $statusLabels = [
            Patient::STATUS_ACTIVE => 'نشط',
            Patient::STATUS_INACTIVE => 'غير نشط',
            Patient::STATUS_QUOTED => 'تم التسعير',
            Patient::STATUS_DONE => 'مكتمل',
        ];

        $cases = $patient->cases->map(fn (CaseRecord $case) => [
            'case_no' => $case->case_no,
            'order_ref' => $case->order_ref,
            'stage_key' => $case->stage_key,
            'stage_label' => CaseStage::labelFor($case->stage_key),
            'patient_type' => $case->patient_type,
            'delivered_at' => $case->delivered_at?->toDateString(),
            'created_at' => $case->created_at?->toDateString(),
        ])->values()->all();

        return [
            'id' => $patient->id,
            'patient_code' => $patient->patient_code,
            'patient_qr' => $patient->patient_qr,
            'tracking_uid' => $patient->tracking_uid,
            'name' => $patient->name,
            'phone' => $patient->phone,
            'national_id' => $patient->national_id,
            'patient_type' => $patient->patient_type,
            'patient_type_label' => $patient->isMilitary() ? 'عسكري' : 'مدني',
            'rank' => $patient->rank,
            'sovereign_entity' => $patient->sovereign_entity,
            'display_entity' => $patient->displayEntity(),
            'company_name' => $patient->company_name,
            'company_code' => $patient->contractCompany?->company_code,
            'registered_at' => $patient->registered_at?->toDateString(),
            'last_visit_at' => $patient->last_visit_at?->toDateString(),
            'status' => $patient->status,
            'status_label' => $statusLabels[$patient->status] ?? $patient->status,
            'current_stage_label' => $tracking['stage_label'] ?? null,
            'active_case' => $activeCase ? [
                'case_no' => $activeCase->case_no,
                'order_ref' => $activeCase->order_ref,
                'stage_key' => $activeCase->stage_key,
                'stage_label' => CaseStage::labelFor($activeCase->stage_key),
                'delivered_at' => $activeCase->delivered_at?->toDateString(),
            ] : null,
            'cases' => $cases,
            'cases_count' => count($cases),
        ];
    }

    /** @param  list<array{key: string, label: string, status?: string}>  $steps */
    private function remapStepStatuses(array $steps, int $currentIndex): array
    {
        return array_map(function (array $step, int $index) use ($currentIndex) {
            $step['status'] = match (true) {
                $index < $currentIndex => 'done',
                $index === $currentIndex => 'current',
                default => 'pending',
            };

            return $step;
        }, $steps, array_keys($steps));
    }

    /** @return array{tracking_uid: ?string, pathway: string, stage_label: string, current_index: int, steps: list<array{key: string, label: string, status: string}>} */
    private function fallbackTracking(Patient $patient, ?CaseRecord $case, ?Appointment $appointment): array
    {
        $pathway = $this->pathwayConfig->resolvePathway($patient, $case);
        $steps = $this->pathwayConfig->displayStepsForPathway($pathway);

        $stageLabel = match ($appointment?->status) {
            Appointment::STATUS_WAITING => 'في الاستقبال — بانتظار التحويل للعيادة',
            Appointment::STATUS_IN_CLINIC => 'في العيادة — بانتظار الطبيب',
            default => 'تم التسجيل — في انتظار الكشف الطبي',
        };

        $currentIndex = $appointment?->status === Appointment::STATUS_IN_CLINIC ? 1 : 0;

        return [
            'tracking_uid' => null,
            'pathway' => $pathway,
            'stage_label' => $case
                ? CaseStage::labelFor($case->stage_key)
                : $stageLabel,
            'current_index' => $currentIndex,
            'steps' => $this->remapStepStatuses($steps, $currentIndex),
        ];
    }

    private function resolveStageKey(?CaseRecord $case, ?Appointment $appointment): ?string
    {
        if ($case) {
            return $case->stage_key;
        }

        if (! $appointment) {
            return null;
        }

        return $appointment->status === Appointment::STATUS_IN_CLINIC
            ? CaseRecord::STAGE_EXAM
            : CaseRecord::STAGE_RECEPTION;
    }

    private function activeAppointment(Patient $patient): ?Appointment
    {
        return $patient->appointments->first(
            fn (Appointment $a) => in_array($a->status, [Appointment::STATUS_WAITING, Appointment::STATUS_IN_CLINIC], true)
                && $a->appointment_date->lte(now())
        );
    }

    private function resolveAppointmentVisitTypeId(?Appointment $appointment): ?int
    {
        if (! $appointment) {
            return null;
        }

        if ($appointment->visit_type_id) {
            return (int) $appointment->visit_type_id;
        }

        if ($appointment->visit_type !== null && $appointment->visit_type !== '' && ctype_digit((string) $appointment->visit_type)) {
            return (int) $appointment->visit_type;
        }

        if ($appointment->visit_type) {
            $id = VisitType::query()->where('name', $appointment->visit_type)->value('id');

            return $id ? (int) $id : null;
        }

        return null;
    }

    private function applySearch(Builder $query, string $term): void
    {
        $digits = preg_replace('/\D/', '', $term) ?? '';

        $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('national_id', 'like', "%{$term}%")
                ->orWhere('patient_code', 'like', "%{$term}%");

            if ($digits !== '' && strlen($digits) >= 4) {
                $q->orWhere('phone', 'like', "%{$digits}%")
                    ->orWhere('national_id', 'like', "%{$digits}%");
            }
        });
    }
}
