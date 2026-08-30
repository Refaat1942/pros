<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\CaseWorkshopAssignment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkshopSection;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

class WorkshopAssignmentService
{
    /**
     * @param  list<array{workshop_section_id: int, assigned_technician_id: int}>  $assignments
     */
    public function syncAssignments(CaseRecord $case, array $assignments): CaseRecord
    {
        if (! config('workshop.enabled', true)) {
            abort(422, 'ميزة أقسام الإنتاج غير مفعّلة.');
        }

        if ($assignments === []) {
            abort(422, 'أضف قسم إنتاج وفني واحد على الأقل.');
        }

        foreach ($assignments as $row) {
            $this->validateAssignmentTargets(
                (int) $row['workshop_section_id'],
                (int) $row['assigned_technician_id'],
            );
        }

        return DB::transaction(function () use ($case, $assignments) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $before = $case->only([
                'workshop_section_id',
                'assigned_technician_id',
                'workshop_assigned_at',
                'workshop_assignment_approved_at',
            ]);

            $case->workshopAssignments()->delete();

            foreach ($assignments as $index => $row) {
                CaseWorkshopAssignment::create([
                    'case_id' => $case->id,
                    'workshop_section_id' => (int) $row['workshop_section_id'],
                    'assigned_technician_id' => (int) $row['assigned_technician_id'],
                    'sort' => $index,
                ]);
            }

            $primary = $assignments[0];

            $case->update([
                'workshop_section_id' => (int) $primary['workshop_section_id'],
                'assigned_technician_id' => (int) $primary['assigned_technician_id'],
                'workshop_assigned_at' => now(),
                'workshop_assignment_approved_at' => null,
            ]);

            AuditService::log(
                action: 'assign',
                description: "تخصيص أمر شغل {$case->work_order_no} — {$case->case_no} (".count($assignments).' أقسام/فنيين)',
                tag: 'workshop',
                before: $before,
                after: $case->only([
                    'workshop_section_id',
                    'assigned_technician_id',
                    'workshop_assigned_at',
                    'workshop_assignment_approved_at',
                ]) + ['assignments_count' => count($assignments)],
            );

            return $case->fresh()->load(['workshopAssignments.workshopSection', 'workshopAssignments.assignedTechnician']);
        });
    }

    public function assignOnApprove(
        CaseRecord $case,
        ?int $sectionId,
        ?int $technicianId,
        ?array $assignments = null,
    ): CaseRecord {
        if ($assignments !== null && $assignments !== []) {
            return $this->syncAssignments($case, $assignments);
        }

        if ($sectionId === null && $technicianId === null) {
            abort(422, 'حدّد قسم الإنتاج والفني قبل حفظ التخصيص.');
        }

        return $this->syncAssignments($case, [[
            'workshop_section_id' => (int) $sectionId,
            'assigned_technician_id' => (int) $technicianId,
        ]]);
    }

    public function approveAssignment(CaseRecord $case): CaseRecord
    {
        if (! config('workshop.enabled', true)) {
            return $case;
        }

        $case->loadMissing(['bom', 'workshopAssignments']);

        if ($case->stage_key !== CaseRecord::STAGE_MANUFACTURING) {
            abort(422, 'الحالة ليست في مرحلة التصنيع.');
        }

        if (! $this->hasCompleteAssignments($case)) {
            abort(422, 'حدّد قسم الإنتاج والفني (واحد أو أكثر) قبل الاعتماد.');
        }

        if ($case->workshop_assignment_approved_at) {
            abort(422, 'تم اعتماد التخصيص مسبقاً.');
        }

        $approved = DB::transaction(function () use ($case) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $before = ['workshop_assignment_approved_at' => $case->workshop_assignment_approved_at];

            $case->update(['workshop_assignment_approved_at' => now()]);

            AuditService::log(
                action: 'approve',
                description: "اعتماد تخصيص الإنتاج — {$case->work_order_no} — {$case->case_no}",
                tag: 'workshop',
                before: $before,
                after: $case->only([
                    'workshop_section_id',
                    'assigned_technician_id',
                    'workshop_assignment_approved_at',
                ]),
            );

            return $case->fresh();
        });

        $approved->loadMissing('patient:id,name');
        $this->notifyWarehouseDispenseReady($approved);

        return $approved;
    }

    private function hasCompleteAssignments(CaseRecord $case): bool
    {
        $case->loadMissing('workshopAssignments');

        if ($case->workshopAssignments->isNotEmpty()) {
            return $case->workshopAssignments->every(
                fn (CaseWorkshopAssignment $row) => $row->workshop_section_id && $row->assigned_technician_id
            );
        }

        return $case->workshop_section_id && $case->assigned_technician_id;
    }

    private function notifyWarehouseDispenseReady(CaseRecord $case): void
    {
        if (! config('workshop.enabled', true)) {
            return;
        }

        $patient = $case->patient?->name ?? 'غير معروف';
        $caseNo = $case->case_no ?? ('#'.$case->id);

        app(NotificationService::class)->push(
            roleSlug: Role::SLUG_TECHNICAL,
            title: '📦 أمر صرف جديد للمخزن',
            body: "المريض {$patient} (حالة {$caseNo}) — تم اعتماد التخصيص في قسم الإنتاج — جاهز للصرف بالباركود من المخزن.",
            case: $case,
            event: 'workshop_assignment_approved',
            data: ['url' => '/technical/bom'],
        );
    }

    public function assertDispenseAllowed(CaseRecord $case): void
    {
        if (! config('workshop.enabled', true)) {
            return;
        }

        if (! $case->isWorkshopAssignmentApproved()) {
            abort(422, 'يجب اعتماد تخصيص قسم الإنتاج والفني قبل صرف المخزن.');
        }
    }

    public function updateProgress(CaseRecord $case, int $percent): CaseRecord
    {
        $percent = max(0, min(100, $percent));

        return DB::transaction(function () use ($case, $percent) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);
            $before = ['workshop_progress_pct' => $case->workshop_progress_pct];

            $case->update(['workshop_progress_pct' => $percent]);

            AuditService::log(
                action: 'update',
                description: "تحديث إنجاز {$case->work_order_no} — {$case->case_no} إلى {$percent}%",
                tag: 'workshop',
                before: $before,
                after: ['workshop_progress_pct' => $percent],
            );

            return $case->fresh();
        });
    }

    public function clearAssignments(CaseRecord $case): void
    {
        $case->workshopAssignments()->delete();
        $case->update([
            'workshop_section_id' => null,
            'assigned_technician_id' => null,
            'workshop_assigned_at' => null,
            'workshop_assignment_approved_at' => null,
            'workshop_progress_pct' => 0,
        ]);
    }

    private function validateAssignmentTargets(?int $sectionId, ?int $technicianId): void
    {
        if ($sectionId !== null) {
            $section = WorkshopSection::query()->where('active', true)->find($sectionId);
            if (! $section) {
                abort(422, 'قسم الإنتاج غير صالح.');
            }
        }

        if ($technicianId !== null) {
            $technician = User::query()->find($technicianId);
            if (! $technician) {
                abort(422, 'الفني غير موجود.');
            }

            if ($sectionId !== null) {
                $linked = WorkshopSection::query()
                    ->whereKey($sectionId)
                    ->whereHas('technicians', fn ($q) => $q->where('users.id', $technicianId))
                    ->exists();

                if (! $linked) {
                    abort(422, 'الفني غير مرتبط بالقسم المختار.');
                }
            }
        }
    }
}
