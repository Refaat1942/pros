<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * مسار المرضى النشطين — لوحة نظرة عامة للإدارة (شريط تقدم + مرحلة حالية).
 */
class AdminPatientTrackService
{
    public function __construct(private readonly PublicTrackingService $publicTracking)
    {
    }

    /** @return Collection<int, array<string, mixed>> */
    public function list(?string $search = null, int $limit = 100): Collection
    {
        $term = trim((string) $search);

        return Patient::query()
            ->with([
                'cases' => fn ($q) => $q
                    ->with('quotes:id,case_id,status')
                    ->where('stage_key', '!=', CaseRecord::STAGE_DELIVERED)
                    ->orderByDesc('id'),
                'appointments' => fn ($q) => $q
                    ->whereIn('status', [Appointment::STATUS_WAITING, Appointment::STATUS_IN_CLINIC])
                    ->where('appointment_date', '<=', now()->toDateString())
                    ->orderByDesc('appointment_date'),
            ])
            ->where(function (Builder $q) {
                $q->whereHas('cases', fn (Builder $c) => $c->where('stage_key', '!=', CaseRecord::STAGE_DELIVERED))
                    ->orWhereHas('appointments', fn (Builder $a) => $a
                        ->whereIn('status', [Appointment::STATUS_WAITING, Appointment::STATUS_IN_CLINIC])
                        ->where('appointment_date', '<=', now()->toDateString()));
            })
            ->when($term !== '', fn (Builder $q) => $this->applySearch($q, $term))
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Patient $patient) => $this->formatTrack($patient))
            ->values();
    }

    /** @return array<string, mixed> */
    private function formatTrack(Patient $patient): array
    {
        $activeCase = $patient->cases->first();
        $appointment = $patient->appointments->first();
        $uid = $patient->tracking_uid ?: $activeCase?->tracking_uid;

        $tracking = $uid
            ? $this->publicTracking->resolve($uid)
            : $this->fallbackTracking($patient, $activeCase, $appointment);

        if (! $activeCase && $appointment) {
            $tracking['stage_label'] = match ($appointment->status) {
                Appointment::STATUS_WAITING   => 'في الاستقبال — بانتظار التحويل للعيادة',
                Appointment::STATUS_IN_CLINIC => 'في العيادة — بانتظار الطبيب',
                default                       => $tracking['stage_label'],
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

        return [
            'id'               => $patient->id,
            'name'             => $patient->name,
            'phone'            => $patient->phone,
            'national_id'      => $patient->national_id,
            'patient_type'     => $patient->patient_type,
            'company_name'     => $patient->displayEntity(),
            'case_no'          => $activeCase?->case_no,
            'pathway'          => $tracking['pathway'],
            'pathway_label'    => $tracking['pathway'] === 'military' ? 'عسكري' : 'مدني',
            'stage_label'      => $tracking['stage_label'],
            'progress_percent' => $progressPercent,
            'steps'            => $steps,
            'current_index'    => $currentIndex,
            'search_hay'       => mb_strtolower(trim(
                ($patient->name ?? '') . ' ' . ($patient->phone ?? '') . ' ' . ($patient->national_id ?? '')
            )),
        ];
    }

    /** @param  list<array{key: string, label: string, status?: string}>  $steps */
    private function remapStepStatuses(array $steps, int $currentIndex): array
    {
        return array_map(function (array $step, int $index) use ($currentIndex) {
            $step['status'] = match (true) {
                $index < $currentIndex  => 'done',
                $index === $currentIndex => 'current',
                default                  => 'pending',
            };

            return $step;
        }, $steps, array_keys($steps));
    }

    /** @return list<array{key: string, label: string}> */
    private function stepsForPath(bool $isMilitary): array
    {
        if ($isMilitary) {
            return [
                ['key' => 'registered', 'label' => 'تسجيل واستقبال'],
                ['key' => 'exam', 'label' => 'الكشف الطبي'],
                ['key' => 'technical', 'label' => 'التوصيف الفني والتحضير'],
                ['key' => 'manufacturing', 'label' => 'التصنيع بالورشة'],
                ['key' => 'ready', 'label' => 'جاهز للتسليم'],
                ['key' => 'delivered', 'label' => 'تم التسليم'],
            ];
        }

        return [
            ['key' => 'registered', 'label' => 'تسجيل واستقبال'],
            ['key' => 'exam', 'label' => 'الكشف الطبي'],
            ['key' => 'technical', 'label' => 'التوصيف الفني والتحضير'],
            ['key' => 'approval', 'label' => 'التسعير واعتماد التشغيل'],
            ['key' => 'manufacturing', 'label' => 'التصنيع بالورشة'],
            ['key' => 'ready', 'label' => 'جاهز للتسليم'],
            ['key' => 'delivered', 'label' => 'تم التسليم'],
        ];
    }

    /** @return array{tracking_uid: ?string, pathway: string, stage_label: string, current_index: int, steps: list<array{key: string, label: string, status: string}>} */
    private function fallbackTracking(Patient $patient, ?CaseRecord $case, ?Appointment $appointment): array
    {
        $isMilitary = $patient->isMilitary();
        $steps = $this->stepsForPath($isMilitary);

        $stageLabel = match ($appointment?->status) {
            Appointment::STATUS_WAITING   => 'في الاستقبال — بانتظار التحويل للعيادة',
            Appointment::STATUS_IN_CLINIC => 'في العيادة — بانتظار الطبيب',
            default                       => 'تم التسجيل — في انتظار الكشف الطبي',
        };

        $currentIndex = $appointment?->status === Appointment::STATUS_IN_CLINIC ? 1 : 0;

        return [
            'tracking_uid'  => null,
            'pathway'       => $isMilitary ? 'military' : 'civilian',
            'stage_label'   => $case
                ? \App\Enums\CaseStage::labelFor($case->stage_key)
                : $stageLabel,
            'current_index' => $currentIndex,
            'steps'         => $this->remapStepStatuses($steps, $currentIndex),
        ];
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
