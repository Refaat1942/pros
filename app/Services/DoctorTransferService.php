<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\TechOrderSpec;
use App\Support\CaseDisplayStatus;
use Illuminate\Support\Collection;

/**
 * الحالات المحوّلة من العيادة للتوصيف الفني / المخزون — لوحة الطبيب.
 */
class DoctorTransferService
{
    public function list(?string $search = null): Collection
    {
        return CaseRecord::query()
            ->with([
                'patient:id,name,patient_type',
                'recommendations',
                'techOrderSpec' => fn ($q) => $q->where('locked', true)->with('items'),
                'medicalRecords' => fn ($q) => $q
                    ->where('locked', true)
                    ->with('items')
                    ->latest()
                    ->limit(1),
            ])
            ->whereHas('medicalRecords', fn ($q) => $q->where('locked', true))
            ->whereNotIn('stage_key', [CaseRecord::STAGE_RECEPTION, CaseRecord::STAGE_EXAM])
            ->when($search, function ($query, $term) {
                $query->where(function ($q) use ($term) {
                    $q->where('company_name', 'like', "%{$term}%")
                        ->orWhereHas('patient', fn ($q) => $q->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('created_at')
            ->limit((int) config('dashboards.table_fetch_limit', 1000))
            ->get()
            ->map(fn (CaseRecord $case) => $this->formatRow($case));
    }

    /** @return array<string, mixed> */
    public function formatRow(CaseRecord $case): array
    {
        $patient = $case->patient;
        $record = $case->medicalRecords->first();

        $display = CaseDisplayStatus::forCase($case);

        return [
            'id' => $case->id,
            'case_no' => $case->case_no,
            'name' => $patient?->name ?? $record?->patient_name ?? '—',
            'company' => $case->displayEntity(),
            'display_entity' => $case->displayEntity(),
            'patient_type' => $case->patient_type,
            'stage_key' => $case->stage_key,
            'manufacturing_stage' => $case->manufacturing_stage,
            'date' => $case->created_at?->format('d/m/Y') ?? '—',
            'transferred_at' => $case->created_at?->toIso8601String(),
            'status' => $display->label,
            'status_group' => $this->statusGroup($case),
            'status_badge_class' => $display->badgeClass,
            'diagnosis' => $record?->diagnosis,
            'prescription' => $record?->prescription,
            'recommendations' => $this->resolveRecommendations($case, $record),
        ];
    }

    /**
     * أصناف التوصية — من التقرير الطبي، ثم case_recommendations، ثم التوصيف الفني المُرسَل.
     *
     * @return list<array{name: string, code: string|null, qty: int}>
     */
    public function resolveRecommendations(CaseRecord $case, ?MedicalRecord $record): array
    {
        $fromRecord = $record?->items ?? collect();
        if ($fromRecord->isNotEmpty()) {
            return $this->mapRecommendationRows($fromRecord);
        }

        if ($case->relationLoaded('recommendations') && $case->recommendations->isNotEmpty()) {
            return $this->mapRecommendationRows($case->recommendations);
        }

        $spec = $case->relationLoaded('techOrderSpec') && $case->techOrderSpec
            ? $case->techOrderSpec
            : TechOrderSpec::query()
                ->where('case_id', $case->id)
                ->where('locked', true)
                ->with('items')
                ->latest('submitted_at')
                ->first();

        if ($spec?->relationLoaded('items') && $spec->items->isNotEmpty()) {
            return $this->mapRecommendationRows($spec->items);
        }

        return [];
    }

    /** @param  Collection<int, object>  $items */
    private function mapRecommendationRows(Collection $items): array
    {
        return $items->map(fn ($item) => [
            'name' => $item->name,
            'code' => $item->stock_item_code,
            'qty' => (int) ($item->qty ?? 1),
        ])->values()->all();
    }

    public function statusGroup(CaseRecord $case): string
    {
        if ($case->stage_key === CaseRecord::STAGE_DELIVERED) {
            return 'مكتمل';
        }

        if (in_array($case->stage_key, [CaseRecord::STAGE_MANUFACTURING, CaseRecord::STAGE_READY_DELIVERY], true)) {
            return 'في الورشة';
        }

        return 'قيد التوصيف';
    }

    /** @deprecated Use CaseDisplayStatus::forCase() */
    public function statusLabel(CaseRecord $case): string
    {
        return CaseDisplayStatus::forCase($case)->label;
    }

    /** @return array{total: int, spec: int, workshop: int, done: int} */
    public function stats(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'spec' => $rows->where('status_group', 'قيد التوصيف')->count(),
            'workshop' => $rows->where('status_group', 'في الورشة')->count(),
            'done' => $rows->where('status_group', 'مكتمل')->count(),
        ];
    }
}
