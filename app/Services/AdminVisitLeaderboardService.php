<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\VisitType;
use Illuminate\Support\Facades\DB;

/**
 * أكثر المرضى زيارة — مجمّع حسب نوع الزيارة (لوحة نظرة عامة).
 */
class AdminVisitLeaderboardService
{
    private const TOP_LIMIT = 5;

    /** @return list<array{visit_type_id: int, visit_type: string, total_visits: int, patients: list<array{name: string, patient_type: string, visit_count: int}>}> */
    public function topPatientsByVisitType(): array
    {
        $visitTypes = VisitType::query()->ordered()->get(['id', 'name']);
        $boards = [];

        foreach ($visitTypes as $visitType) {
            $counts = Appointment::query()
                ->where('visit_type_id', $visitType->id)
                ->whereNotNull('patient_id')
                ->select('patient_id', DB::raw('COUNT(*) as visit_count'))
                ->groupBy('patient_id')
                ->orderByDesc('visit_count')
                ->limit(self::TOP_LIMIT)
                ->get();

            if ($counts->isEmpty()) {
                continue;
            }

            $patients = Patient::query()
                ->whereIn('id', $counts->pluck('patient_id'))
                ->get(['id', 'name', 'patient_type'])
                ->keyBy('id');

            $boards[] = [
                'visit_type_id' => $visitType->id,
                'visit_type' => $visitType->name,
                'total_visits' => (int) Appointment::query()
                    ->where('visit_type_id', $visitType->id)
                    ->count(),
                'patients' => $counts->map(function ($row) use ($patients) {
                    $patient = $patients->get($row->patient_id);

                    return [
                        'name' => $patient?->name ?? '—',
                        'patient_type' => $patient?->patient_type ?? Patient::TYPE_CIVILIAN,
                        'visit_count' => (int) $row->visit_count,
                    ];
                })->values()->all(),
            ];
        }

        usort($boards, fn (array $a, array $b) => $b['total_visits'] <=> $a['total_visits']);

        return $boards;
    }
}
