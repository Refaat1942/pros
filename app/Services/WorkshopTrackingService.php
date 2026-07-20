<?php

namespace App\Services;

use App\Enums\ManufacturingStage;
use App\Models\Bom;
use App\Models\CaseRecord;
use Illuminate\Support\Collection;

class WorkshopTrackingService
{
    /** @return array{data: list<array<string, mixed>>, summary: array<string, int>} */
    public function trackingList(?int $sectionId = null, ?int $technicianId = null): array
    {
        $query = CaseRecord::query()
            ->where('stage_key', CaseRecord::STAGE_MANUFACTURING)
            ->whereHas('bom', fn ($q) => $q->where('stage', Bom::STAGE_WIP))
            ->with([
                'patient:id,patient_code,name',
                'workshopSection:id,name,code',
                'assignedTechnician:id,name',
                'bom:id,case_id,bom_no,stage',
            ])
            ->orderByDesc('updated_at');

        if ($sectionId) {
            $query->where('workshop_section_id', $sectionId);
        }

        if ($technicianId) {
            $query->where('assigned_technician_id', $technicianId);
        }

        /** @var Collection<int, CaseRecord> $cases */
        $cases = $query->get();

        return [
            'data' => $cases->map(fn (CaseRecord $c) => [
                'id' => $c->id,
                'case_no' => $c->case_no,
                'work_order_no' => $c->work_order_no,
                'manufacturing_stage' => $c->manufacturing_stage,
                'manufacturing_stage_label' => ManufacturingStage::tryFrom($c->manufacturing_stage ?? '')?->label()
                    ?? ($c->manufacturing_stage ?? '—'),
                'workshop_progress_pct' => (int) ($c->workshop_progress_pct ?? 0),
                'workshop_assigned_at' => $c->workshop_assigned_at?->toIso8601String(),
                'updated_at' => $c->updated_at?->toIso8601String(),
                'patient' => $c->patient?->only(['id', 'patient_code', 'name']),
                'workshop_section' => $c->workshopSection?->only(['id', 'name', 'code']),
                'assigned_technician' => $c->assignedTechnician?->only(['id', 'name']),
                'pathway_label' => $c->isMilitary() ? 'عسكري' : 'مدني',
            ])->values()->all(),
            'summary' => [
                'total_wip' => $cases->count(),
                'assigned' => $cases->whereNotNull('assigned_technician_id')->count(),
                'unassigned' => $cases->whereNull('assigned_technician_id')->count(),
            ],
        ];
    }
}
