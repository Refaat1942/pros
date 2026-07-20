<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\User;
use App\Models\WorkshopSection;
use Illuminate\Support\Facades\DB;

class WorkshopAssignmentService
{
    public function assignOnApprove(
        CaseRecord $case,
        ?int $sectionId,
        ?int $technicianId,
    ): CaseRecord {
        if (! config('workshop.enabled', true)) {
            return $case;
        }

        if ($sectionId === null && $technicianId === null) {
            return $case;
        }

        if ($sectionId !== null) {
            $section = WorkshopSection::query()->where('active', true)->find($sectionId);
            if (! $section) {
                abort(422, 'قسم الورشة غير صالح.');
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

        return DB::transaction(function () use ($case, $sectionId, $technicianId) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $before = $case->only([
                'workshop_section_id',
                'assigned_technician_id',
                'workshop_assigned_at',
            ]);

            $case->update([
                'workshop_section_id' => $sectionId,
                'assigned_technician_id' => $technicianId,
                'workshop_assigned_at' => ($sectionId || $technicianId) ? now() : null,
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
                ]),
            );

            return $case->fresh();
        });
    }

    public function updateProgress(CaseRecord $case, int $percent): CaseRecord
    {
        $percent = max(0, min(100, $percent));

        $case->update(['workshop_progress_pct' => $percent]);

        return $case->fresh();
    }
}
