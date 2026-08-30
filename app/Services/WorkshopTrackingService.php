<?php

namespace App\Services;

use App\Enums\ManufacturingStage;
use App\Models\Bom;
use App\Models\CaseRecord;
use Illuminate\Support\Collection;

class WorkshopTrackingService
{
    /** @return array{data: list<array<string, mixed>>, summary: array<string, int>} */
    public function technicianBoard(): array
    {
        $cases = CaseRecord::query()
            ->workshopDeskQueue()
            ->with([
                'patient:id,patient_code,name',
                'workshopSection:id,name,code',
                'assignedTechnician:id,name',
                'bom:id,case_id,bom_no,stage,released_at',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $groups = [];
        $unassigned = [];

        foreach ($cases as $case) {
            $order = $this->formatTrackingOrder($case);
            $techId = $case->assigned_technician_id;

            if (! $techId) {
                $unassigned[] = $order;

                continue;
            }

            if (! isset($groups[$techId])) {
                $groups[$techId] = [
                    'technician' => $case->assignedTechnician?->only(['id', 'name']) ?? ['id' => $techId, 'name' => '—'],
                    'section' => $case->workshopSection?->only(['id', 'name', 'code']),
                    'orders' => [],
                    'active_count' => 0,
                    'done_count' => 0,
                    'avg_progress' => 0,
                ];
            }

            $groups[$techId]['orders'][] = $order;
            if ($order['is_done']) {
                $groups[$techId]['done_count']++;
            } else {
                $groups[$techId]['active_count']++;
            }
        }

        $technicians = collect($groups)->map(function (array $group) {
            $progressValues = collect($group['orders'])->pluck('progress_pct');
            $group['avg_progress'] = $progressValues->isEmpty()
                ? 0
                : (int) round($progressValues->avg());
            $group['orders'] = collect($group['orders'])
                ->sortByDesc('updated_at')
                ->values()
                ->all();

            return $group;
        })->sortBy(fn ($g) => $g['technician']['name'] ?? '')->values()->all();

        $assignedCount = $cases->whereNotNull('assigned_technician_id')->count();

        return [
            'technicians' => $technicians,
            'unassigned' => collect($unassigned)->sortByDesc('updated_at')->values()->all(),
            'summary' => [
                'total_wip' => $cases->count(),
                'assigned' => $assignedCount,
                'unassigned' => $cases->count() - $assignedCount,
                'technicians_active' => count($technicians),
                'avg_progress' => $cases->isEmpty()
                    ? 0
                    : (int) round($cases->avg(fn (CaseRecord $c) => (int) ($c->workshop_progress_pct ?? 0))),
            ],
        ];
    }

    private function formatTrackingOrder(CaseRecord $case): array
    {
        $progress = (int) ($case->workshop_progress_pct ?? 0);
        $stageLabel = ManufacturingStage::workshopDeskLabelFor($case->manufacturing_stage);
        $isDone = $progress >= 100
            || $case->manufacturing_stage === ManufacturingStage::Assembly->value;

        return [
            'id' => $case->id,
            'case_no' => $case->case_no,
            'work_order_no' => $case->work_order_no,
            'manufacturing_stage' => $case->manufacturing_stage,
            'manufacturing_stage_label' => $stageLabel,
            'progress_pct' => $progress,
            'is_done' => $isDone,
            'pathway_label' => $case->isMilitary() ? 'عسكري' : 'مدني',
            'patient' => $case->patient?->only(['id', 'patient_code', 'name']),
            'workshop_section' => $case->workshopSection?->only(['id', 'name', 'code']),
            'assigned_technician' => $case->assignedTechnician?->only(['id', 'name']),
            'workshop_assigned_at' => $case->workshop_assigned_at?->toIso8601String(),
            'updated_at' => $case->updated_at?->toIso8601String(),
        ];
    }

    /** @return array{data: list<array<string, mixed>>, summary: array<string, int>} */
    public function assignmentTrackingList(?int $sectionId = null): array
    {
        if (! config('workshop.enabled', true)) {
            return ['data' => [], 'summary' => ['total' => 0, 'assigned' => 0, 'awaiting_approval' => 0]];
        }

        $query = CaseRecord::query()
            ->workshopAssignmentQueue()
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

        $cases = $query->get();

        return [
            'data' => $cases->map(fn (CaseRecord $c) => [
                'id' => $c->id,
                'case_no' => $c->case_no,
                'work_order_no' => $c->work_order_no,
                'queue_phase' => 'assignment',
                'assignment_status' => $c->isWorkshopAssignmentApproved()
                    ? 'approved'
                    : ($c->workshop_section_id && $c->assigned_technician_id ? 'pending_approval' : 'unassigned'),
                'assignment_status_label' => $c->isWorkshopAssignmentApproved()
                    ? 'معتمد — جاهز للصرف'
                    : ($c->workshop_section_id && $c->assigned_technician_id ? 'بانتظار اعتماد التخصيص' : 'غير مُخصّص'),
                'manufacturing_stage_label' => 'بانتظار التخصيص / الصرف',
                'workshop_progress_pct' => (int) ($c->workshop_progress_pct ?? 0),
                'workshop_assigned_at' => $c->workshop_assigned_at?->toIso8601String(),
                'workshop_assignment_approved_at' => $c->workshop_assignment_approved_at?->toIso8601String(),
                'updated_at' => $c->updated_at?->toIso8601String(),
                'patient' => $c->patient?->only(['id', 'patient_code', 'name']),
                'workshop_section' => $c->workshopSection?->only(['id', 'name', 'code']),
                'assigned_technician' => $c->assignedTechnician?->only(['id', 'name']),
                'pathway_label' => $c->isMilitary() ? 'عسكري' : 'مدني',
            ])->values()->all(),
            'summary' => [
                'total' => $cases->count(),
                'assigned' => $cases->whereNotNull('assigned_technician_id')->count(),
                'awaiting_approval' => $cases->filter(
                    fn (CaseRecord $c) => $c->workshop_section_id && $c->assigned_technician_id && ! $c->isWorkshopAssignmentApproved()
                )->count(),
            ],
        ];
    }

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
                'queue_phase' => 'wip',
                'assignment_status' => 'dispensed',
                'assignment_status_label' => 'تحت التشغيل',
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
