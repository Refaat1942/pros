<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use App\Models\Role;

/**
 * رسائل التحويل بين الأقسام — تُستمد من خطوات المسار المُخصَّص (مصمم المسار).
 */
class PathwayTransitionMessageService
{
    /** @var array<string, string> */
    private const TARGET_STAGE = [
        WorkflowEvent::ExamApproved->value => CaseRecord::STAGE_TECHNICAL,
        WorkflowEvent::ExamSkipped->value => CaseRecord::STAGE_TECHNICAL,
        WorkflowEvent::SpecSaved->value => CaseRecord::STAGE_ADJUSTMENTS,
        WorkflowEvent::AdjustmentsCompleted->value => CaseRecord::STAGE_COST_CALC,
        WorkflowEvent::CostingCompleted->value => CaseRecord::STAGE_QUOTE,
        WorkflowEvent::QuoteIssued->value => CaseRecord::STAGE_OPERATIONS,
        WorkflowEvent::SentToCashier->value => CaseRecord::STAGE_CASHIER,
        WorkflowEvent::CashierPaid->value => CaseRecord::STAGE_OPERATIONS,
        WorkflowEvent::OperationsApproved->value => CaseRecord::STAGE_MANUFACTURING,
        WorkflowEvent::ReturnedToAdjustments->value => CaseRecord::STAGE_ADJUSTMENTS,
        WorkflowEvent::ReturnedToTechnical->value => CaseRecord::STAGE_TECHNICAL,
        WorkflowEvent::BomDispensed->value => CaseRecord::STAGE_MANUFACTURING,
        WorkflowEvent::BomFinished->value => CaseRecord::STAGE_READY_DELIVERY,
        WorkflowEvent::Delivered->value => CaseRecord::STAGE_DELIVERED,
    ];

    /** @var array<string, string> */
    private const DEPT_ROLE = [
        'reception' => Role::SLUG_RECEPTION,
        'doctor' => Role::SLUG_DOCTOR,
        'spec' => Role::SLUG_SPEC,
        'adjustments' => Role::SLUG_ADJUSTMENTS,
        'costing' => Role::SLUG_COSTING,
        'operations' => Role::SLUG_OPERATIONS,
        'cashier' => Role::SLUG_CASHIER,
        'warehouse' => Role::SLUG_TECHNICAL,
        'workshop' => Role::SLUG_WORKSHOP,
        'delivery' => Role::SLUG_RECEPTION,
    ];

    /** @var array<string, string> */
    private const TITLE_PREFIX = [
        WorkflowEvent::ExamApproved->value => '🔧',
        WorkflowEvent::ExamSkipped->value => '🔧',
        WorkflowEvent::SpecSaved->value => '📏',
        WorkflowEvent::AdjustmentsCompleted->value => '🧮',
        WorkflowEvent::CostingCompleted->value => '💰',
        WorkflowEvent::QuoteIssued->value => '🎯',
        WorkflowEvent::SentToCashier->value => '💵',
        WorkflowEvent::CashierPaid->value => '💰',
        WorkflowEvent::OperationsApproved->value => '📦',
        WorkflowEvent::ReturnedToAdjustments->value => '↩️',
        WorkflowEvent::ReturnedToTechnical->value => '↩️',
        WorkflowEvent::BomDispensed->value => '🏭',
        WorkflowEvent::BomFinished->value => '✅',
        WorkflowEvent::Delivered->value => '📁',
    ];

    public function __construct(private readonly PathwayConfigService $pathwayConfig) {}

    public function transferMessage(CaseRecord $case, string $event, string $fromStageKey): string
    {
        $targetStage = self::TARGET_STAGE[$event] ?? null;

        if ($targetStage === null) {
            return 'تم تحديث حالة الطلب.';
        }

        if ($event === WorkflowEvent::BomDispensed->value) {
            $from = $this->pathwayConfig->stepLabelForStage($case, $fromStageKey);
            $to = $this->pathwayConfig->stepLabelForStage($case, CaseRecord::STAGE_MANUFACTURING);

            return "تم التحويل من {$from} إلى {$to} — جاهز للورشة.";
        }

        $from = $this->pathwayConfig->stepLabelForStage($case, $fromStageKey);
        $to = $this->pathwayConfig->stepLabelForStage($case, $targetStage);

        return "تم التحويل من {$from} إلى {$to}";
    }

    /**
     * @return array{role: string, title: string, body: string}|null
     */
    public function notificationPayload(CaseRecord $case, string $event, string $fromStageKey): ?array
    {
        $targetStage = self::TARGET_STAGE[$event] ?? null;

        if ($targetStage === null) {
            return null;
        }

        $role = $this->targetRoleForStage($case, $targetStage, $event);
        $prefix = self::TITLE_PREFIX[$event] ?? '📌';
        $toLabel = $this->pathwayConfig->stepLabelForStage($case, $targetStage);

        $case->loadMissing('patient:id,name,patient_code');
        $patient = $case->patient?->name ?? 'غير معروف';
        $caseNo = $case->case_no ?? ('#'.$case->id);

        $title = match ($event) {
            WorkflowEvent::BomFinished->value => "{$prefix} طرف جاهز للتسليم",
            WorkflowEvent::Delivered->value => "{$prefix} تم تسليم وإغلاق حالة",
            default => "{$prefix} حالة جديدة — {$toLabel}",
        };

        $transfer = $this->transferMessage($case, $event, $fromStageKey);
        $body = "المريض {$patient} (حالة {$caseNo}) — {$transfer}.";

        return [
            'role' => $role,
            'title' => $title,
            'body' => $body,
        ];
    }

    private function targetRoleForStage(CaseRecord $case, string $stageKey, string $event): string
    {
        $pathway = $this->pathwayConfig->resolvePathway($case->patient, $case);
        $steps = $this->pathwayConfig->steps($pathway, activeOnly: true);

        $preferredKey = match ($event) {
            WorkflowEvent::BomDispensed->value => 'workshop',
            WorkflowEvent::BomFinished->value => 'delivery',
            default => null,
        };

        if ($preferredKey !== null) {
            foreach ($steps as $step) {
                if (($step['key'] ?? '') === $preferredKey) {
                    $dept = $step['owner_department'] ?? $preferredKey;

                    return self::DEPT_ROLE[$dept] ?? Role::SLUG_ADMIN;
                }
            }
        }

        foreach ($steps as $step) {
            if (! in_array($stageKey, $step['stage_keys'] ?? [], true)) {
                continue;
            }

            if ($stageKey === CaseRecord::STAGE_READY_DELIVERY && ($step['key'] ?? '') === 'operations_release') {
                continue;
            }

            $dept = $step['owner_department'] ?? '';

            return self::DEPT_ROLE[$dept] ?? Role::SLUG_ADMIN;
        }

        return Role::SLUG_ADMIN;
    }
}
