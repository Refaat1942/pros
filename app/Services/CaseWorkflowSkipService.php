<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Models\Appointment;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\User;
use App\Models\WorkflowStagePolicy;
use Illuminate\Support\Facades\DB;

/**
 * تخطي مراحل اختيارية في مسار الحالة — مع الحفاظ على الآثار الجانبية (تكاليف، BOM، …).
 */
class CaseWorkflowSkipService
{
    public function __construct(
        private readonly WorkflowPolicyService $policies,
        private readonly WorkflowService $workflow,
        private readonly CostingService $costingService,
        private readonly CaseService $caseService,
    ) {}

    /**
     * تخطي تلقائي حسب إعدادات الإدارة — يُستدعى بعد انتقال workflow.
     */
    public function applyConfiguredAutoSkip(CaseRecord $case): CaseRecord
    {
        if (! $this->policies->shouldAutoSkip($case)) {
            return $case;
        }

        return $this->skipCurrentStage($case, null, auto: true);
    }

    /**
     * تخطي يدوي لمرحلة اختيارية — للمدير أو الدور المصرّح.
     */
    public function skipCurrentStage(CaseRecord $case, ?User $user, bool $auto = false): CaseRecord
    {
        $case = $case->fresh();
        $stageKey = $case->stage_key;

        if (! $stageKey) {
            abort(422, 'لا توجد مرحلة حالية للتخطي.');
        }

        if ($auto && ! $this->policies->shouldAutoSkip($case)) {
            return $case;
        }

        if (! $auto && ! $this->policies->canManualSkip($case, $stageKey, $user)) {
            abort(403, 'لا تملك صلاحية تخطي هذه المرحلة أو أنها إلزامية.');
        }

        return match ($stageKey) {
            CaseRecord::STAGE_RECEPTION => $this->skipExamFromReception($case, $user, $auto),
            CaseRecord::STAGE_ADJUSTMENTS => $this->skipAdjustmentsStage($case, $user, $auto),
            default => abort(422, 'تخطي هذه المرحلة غير مدعوم حالياً.'),
        };
    }

    private function skipExamFromReception(CaseRecord $case, ?User $user, bool $auto): CaseRecord
    {
        abort_unless(
            $this->policies->canManualSkip($case, CaseRecord::STAGE_EXAM, $user) || $auto,
            403,
            'تخطي الكشف غير مسموح.'
        );

        $this->workflow->advance($case, WorkflowEvent::ExamSkipped->value);

        return $this->afterSkip($case->fresh(), CaseRecord::STAGE_EXAM, $user, $auto);
    }

    private function skipAdjustmentsStage(CaseRecord $case, ?User $user, bool $auto): CaseRecord
    {
        $updated = $this->costingService->receiveFromAdjustments($case);

        return $this->afterSkip($updated, CaseRecord::STAGE_ADJUSTMENTS, $user, $auto);
    }

    private function afterSkip(CaseRecord $case, string $skippedStage, ?User $user, bool $auto): CaseRecord
    {
        AuditService::log(
            action: 'skip',
            description: $auto
                ? "تخطي تلقائي للمرحلة — {$skippedStage} — {$case->case_no}"
                : "تخطي يدوي للمرحلة — {$skippedStage} — {$case->case_no}",
            tag: 'medical',
            after: [
                'case_id' => $case->id,
                'skipped_stage' => $skippedStage,
                'new_stage' => $case->stage_key,
                'by' => $auto ? 'النظام — إعداد تلقائي' : ($user?->name ?? '—'),
            ],
        );

        return $this->workflow->finalizeAfterTransition($case);
    }

    /**
     * تخطي الكشف من موعد العيادة — يُتحقق من السياسة قبل التنفيذ.
     */
    public function skipExamForAppointment(Appointment $appointment, User $user): CaseRecord
    {
        abort_unless(
            $appointment->status === Appointment::STATUS_IN_CLINIC && $appointment->transferred_to_clinic,
            422,
            'يجب تحويل المريض من الاستقبال قبل تخطّي الكشف.'
        );

        $patient = Patient::findOrFail($appointment->patient_id);

        $pathway = $patient->isMilitary()
            ? WorkflowStagePolicy::PATHWAY_MILITARY
            : WorkflowStagePolicy::PATHWAY_CIVILIAN;

        abort_unless(
            $this->policies->canSkipStageForPathway($pathway, CaseRecord::STAGE_EXAM, $user),
            403,
            'تخطي الكشف غير مسموح حسب إعدادات مسار العمل.'
        );

        return DB::transaction(function () use ($appointment, $patient, $user) {
            $case = $this->caseService->initiateFromReception($patient);

            Appointment::where('id', $appointment->id)->update([
                'status' => Appointment::STATUS_DONE,
                'status_label' => 'منتهٍ',
            ]);

            AuditService::log(
                action: 'skip',
                description: "تخطّي الكشف الطبي — {$patient->name} (موعد #{$appointment->id})",
                tag: 'medical',
                after: ['case_id' => $case->id, 'appointment_id' => $appointment->id, 'by' => $user->name],
            );

            return $case->fresh();
        });
    }
}
