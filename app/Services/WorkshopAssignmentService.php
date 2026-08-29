<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkshopSection;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

class WorkshopAssignmentService
{
    public function assignOnApprove(
        CaseRecord $case,
        ?int $sectionId,
        ?int $technicianId,
    ): CaseRecord {
        if (! config('workshop.enabled', true)) {
            abort(422, 'ميزة أقسام الإنتاج غير مفعّلة.');
        }

        if ($sectionId === null && $technicianId === null) {
            abort(422, 'حدّد قسم الإنتاج أو الفني قبل حفظ التخصيص.');
        }

        $this->validateAssignmentTargets($sectionId, $technicianId);

        return DB::transaction(function () use ($case, $sectionId, $technicianId) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $before = $case->only([
                'workshop_section_id',
                'assigned_technician_id',
                'workshop_assigned_at',
                'workshop_assignment_approved_at',
            ]);

            $case->update([
                'workshop_section_id' => $sectionId,
                'assigned_technician_id' => $technicianId,
                'workshop_assigned_at' => ($sectionId || $technicianId) ? now() : null,
                'workshop_assignment_approved_at' => null,
            ]);

            AuditService::log(
                action: 'assign',
                description: "تخصيص أمر شغل {$case->work_order_no} — {$case->case_no}",
                tag: 'workshop',
                before: $before,
                after: $case->only([
                    'workshop_section_id',
                    'assigned_technician_id',
                    'workshop_assigned_at',
                    'workshop_assignment_approved_at',
                ]),
            );

            return $case->fresh();
        });
    }

    public function approveAssignment(CaseRecord $case): CaseRecord
    {
        if (! config('workshop.enabled', true)) {
            return $case;
        }

        $case->loadMissing('bom');

        if ($case->stage_key !== CaseRecord::STAGE_MANUFACTURING) {
            abort(422, 'الحالة ليست في مرحلة التصنيع.');
        }

        if (! $case->workshop_section_id || ! $case->assigned_technician_id) {
            abort(422, 'حدّد قسم الإنتاج والفني قبل الاعتماد.');
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
