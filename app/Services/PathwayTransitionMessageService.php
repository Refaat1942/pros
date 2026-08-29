<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use App\Models\PathwayStep;
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
        WorkflowEvent::ServicesApprovalRequired->value => CaseRecord::STAGE_SERVICES_APPROVAL,
        WorkflowEvent::ServicesApproved->value => CaseRecord::STAGE_OPERATIONS,
        WorkflowEvent::QuoteIssued->value => CaseRecord::STAGE_OPERATIONS,
        WorkflowEvent::SentToCashier->value => CaseRecord::STAGE_CASHIER,
        WorkflowEvent::CashierPaid->value => CaseRecord::STAGE_OPERATIONS,
        WorkflowEvent::OperationsApproved->value => CaseRecord::STAGE_MANUFACTURING,
        WorkflowEvent::ReturnedToAdjustments->value => CaseRecord::STAGE_ADJUSTMENTS,
        WorkflowEvent::ReturnedToTechnical->value => CaseRecord::STAGE_TECHNICAL,
        WorkflowEvent::SpecEditPostWoRollback->value => CaseRecord::STAGE_ADJUSTMENTS,
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
        'admin' => Role::SLUG_ADMIN,
    ];

    /** @var array<string, string> */
    private const ROLE_ACTION_URL = [
        Role::SLUG_RECEPTION => '/reception/appointments',
        Role::SLUG_DOCTOR => '/doctor/queue',
        Role::SLUG_SPEC => '/spec/spec',
        Role::SLUG_ADJUSTMENTS => '/adjustments/adjustments',
        Role::SLUG_COSTING => '/costing/costing',
        Role::SLUG_OPERATIONS => '/operations/pending',
        Role::SLUG_CASHIER => '/cashier/payments',
        Role::SLUG_TECHNICAL => '/technical/bom',
        Role::SLUG_WORKSHOP => '/workshop/workshop',
        Role::SLUG_ADMIN => '/admin/dashboard',
    ];

    /** @var array<string, string> */
    private const TITLE_PREFIX = [
        WorkflowEvent::ExamApproved->value => '🔧',
        WorkflowEvent::ExamSkipped->value => '🔧',
        WorkflowEvent::SpecSaved->value => '📏',
        WorkflowEvent::AdjustmentsCompleted->value => '🧮',
        WorkflowEvent::CostingCompleted->value => '💰',
        WorkflowEvent::ServicesApprovalRequired->value => '🪖',
        WorkflowEvent::ServicesApproved->value => '🎯',
        WorkflowEvent::QuoteIssued->value => '🎯',
        WorkflowEvent::SentToCashier->value => '💵',
        WorkflowEvent::CashierPaid->value => '💰',
        WorkflowEvent::OperationsApproved->value => '🏭',
        WorkflowEvent::ReturnedToAdjustments->value => '↩️',
        WorkflowEvent::ReturnedToTechnical->value => '↩️',
        WorkflowEvent::SpecEditPostWoRollback->value => '↩️',
        WorkflowEvent::BomDispensed->value => '🏭',
        WorkflowEvent::BomFinished->value => '✅',
        WorkflowEvent::Delivered->value => '📁',
    ];

    public function __construct(private readonly PathwayConfigService $pathwayConfig) {}

    public function transferMessage(CaseRecord $case, string $event, string $fromStageKey): string
    {
        $targetStage = $this->resolveTargetStage($case, $event);

        if ($targetStage === null) {
            return 'تم تحديث حالة الطلب.';
        }

        $from = $this->resolveFromLabel($case, $event, $fromStageKey);
        $to = $this->resolveToLabel($case, $event, $fromStageKey, $targetStage);

        return "تم التحويل من {$from} إلى {$to}.";
    }

    /**
     * @return array{role: string, title: string, body: string, url: string}|null
     */
    public function notificationPayload(CaseRecord $case, string $event, string $fromStageKey): ?array
    {
        if ($this->shouldSuppressNotification($case, $event)) {
            return null;
        }

        $targetStage = $this->resolveTargetStage($case, $event);

        if ($targetStage === null) {
            return null;
        }

        $role = $this->targetRoleForStage($case, $targetStage, $event);
        $prefix = self::TITLE_PREFIX[$event] ?? '📌';
        $toLabel = $this->resolveToLabel($case, $event, $fromStageKey, $targetStage);

        $case->loadMissing('patient:id,name,patient_code');
        $patient = $case->patient?->name ?? 'غير معروف';
        $caseNo = $case->case_no ?? ('#'.$case->id);

        $title = match ($event) {
            WorkflowEvent::BomFinished->value => "{$prefix} طرف جاهز للتسليم — الاستقبال",
            WorkflowEvent::SentToCashier->value => "{$prefix} عرض سعر بانتظار الدفع — الخزنة",
            WorkflowEvent::Delivered->value => "{$prefix} تم تسليم وإغلاق حالة",
            WorkflowEvent::ServicesApprovalRequired->value => "{$prefix} بانتظار تصديق إدارة الخدمات",
            default => "{$prefix} حالة جديدة — {$toLabel}",
        };

        $transfer = $this->transferMessage($case, $event, $fromStageKey);
        $body = "المريض {$patient} (حالة {$caseNo}) — {$transfer}.";

        return [
            'role' => $role,
            'title' => $title,
            'body' => $body,
            'url' => $this->actionUrl($case, $event, $targetStage, $role),
        ];
    }

    /**
     * إصدار عرض سعر جهة للاستقبال — لا يمر بمحرك workflow.
     *
     * @return array{role: string, title: string, body: string, url: string}
     */
    public function entityQuoteReleasedPayload(CaseRecord $case): array
    {
        $case->loadMissing('patient:id,name');
        $patient = $case->patient?->name ?? 'غير معروف';
        $caseNo = $case->case_no ?? ('#'.$case->id);
        $from = $this->pathwayConfig->stepLabelForKey($case, 'quote');
        $to = $this->pathwayConfig->stepLabelForKey($case, 'entity_return');

        return [
            'role' => Role::SLUG_RECEPTION,
            'title' => '📄 عرض سعر بانتظار خطاب الموافقة',
            'body' => "المريض {$patient} (حالة {$caseNo}) — تم التحويل من {$from} إلى {$to}.",
            'url' => '/reception/quote',
        ];
    }

    public function resolveTargetStage(CaseRecord $case, string $event): ?string
    {
        $case->loadMissing(['patient', 'quotes']);

        if ($event === WorkflowEvent::CostingCompleted->value) {
            if ($case->needsServicesApproval()) {
                return CaseRecord::STAGE_SERVICES_APPROVAL;
            }

            if ($case->isMilitary()) {
                return CaseRecord::STAGE_OPERATIONS;
            }

            if ($this->pathwayConfig->resolvePathway($case->patient, $case) === PathwayStep::PATHWAY_ENTITY) {
                return CaseRecord::STAGE_QUOTE;
            }

            return CaseRecord::STAGE_OPERATIONS;
        }

        if ($event === WorkflowEvent::QuoteIssued->value) {
            return $case->isCashCivilian()
                ? CaseRecord::STAGE_CASHIER
                : CaseRecord::STAGE_OPERATIONS;
        }

        return self::TARGET_STAGE[$event] ?? null;
    }

    public function actionUrl(CaseRecord $case, string $event, string $targetStage, string $role): string
    {
        return match ($event) {
            WorkflowEvent::BomFinished->value => '/reception/delivery',
            WorkflowEvent::SentToCashier->value => '/cashier/payments',
            WorkflowEvent::CashierPaid->value => '/operations/pending',
            WorkflowEvent::OperationsApproved->value => $this->workshopGatesDispense()
                ? '/workshop/workshop'
                : '/technical/bom',
            WorkflowEvent::BomDispensed->value => '/workshop/workshop',
            WorkflowEvent::ServicesApprovalRequired->value => '/admin/dashboard',
            WorkflowEvent::ServicesApproved->value => '/operations/pending',
            WorkflowEvent::QuoteIssued->value => $case->isCashCivilian()
                ? '/cashier/payments'
                : ($role === Role::SLUG_RECEPTION
                    ? '/reception/quote'
                    : '/operations/pending'),
            WorkflowEvent::CostingCompleted->value => match ($role) {
                Role::SLUG_ADMIN => '/admin/dashboard',
                Role::SLUG_OPERATIONS => '/operations/pending',
                default => self::ROLE_ACTION_URL[$role] ?? '/operations/pending',
            },
            default => self::ROLE_ACTION_URL[$role] ?? '/',
        };
    }

    private function shouldSuppressNotification(CaseRecord $case, string $event): bool
    {
        $pathway = $this->pathwayConfig->resolvePathway($case->patient, $case);

        if ($event === WorkflowEvent::QuoteIssued->value && $case->isMilitary()) {
            return true;
        }

        if ($event === WorkflowEvent::CostingCompleted->value
            && $pathway === PathwayStep::PATHWAY_CIVILIAN
            && ! $case->needsServicesApproval()) {
            return true;
        }

        // مسار الكاش: QuoteIssued يُتبع فوراً بـ SentToCashier — إشعار واحد للخزنة يكفي.
        if ($event === WorkflowEvent::QuoteIssued->value && $case->isCashCivilian()) {
            return true;
        }

        return false;
    }

    private function targetRoleForStage(CaseRecord $case, string $stageKey, string $event): string
    {
        $pathway = $this->pathwayConfig->resolvePathway($case->patient, $case);
        $steps = $this->pathwayConfig->steps($pathway, activeOnly: true);

        $preferredKey = match ($event) {
            WorkflowEvent::BomDispensed->value => 'workshop',
            WorkflowEvent::BomFinished->value => 'delivery',
            WorkflowEvent::CostingCompleted->value => match (true) {
                $case->needsServicesApproval() => 'services_approval',
                $case->isMilitary(), $pathway === PathwayStep::PATHWAY_CIVILIAN => 'operations_wo',
                $pathway === PathwayStep::PATHWAY_ENTITY => 'quote',
                default => null,
            },
            WorkflowEvent::QuoteIssued->value => match (true) {
                $case->isCashCivilian() => 'cashier',
                $pathway === PathwayStep::PATHWAY_ENTITY => $this->pathwayConfig->entityOperationsStepKey($case),
                default => 'operations_wo',
            },
            WorkflowEvent::OperationsApproved->value => $this->workshopGatesDispense() ? 'workshop' : 'warehouse',
            WorkflowEvent::SentToCashier->value => 'cashier',
            WorkflowEvent::CashierPaid->value => 'operations_wo',
            WorkflowEvent::ServicesApprovalRequired->value => 'services_approval',
            WorkflowEvent::ServicesApproved->value => 'operations_wo',
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

        if ($pathway === PathwayStep::PATHWAY_ENTITY && $stageKey === CaseRecord::STAGE_OPERATIONS) {
            $quoteStep = $this->pathwayConfig->entityOperationsStepKey($case);
            foreach ($steps as $step) {
                if (($step['key'] ?? '') === $quoteStep) {
                    $dept = $step['owner_department'] ?? 'operations';

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

            if ($pathway === PathwayStep::PATHWAY_ENTITY
                && $stageKey === CaseRecord::STAGE_OPERATIONS
                && ($step['key'] ?? '') !== $this->pathwayConfig->entityOperationsStepKey($case)) {
                continue;
            }

            $dept = $step['owner_department'] ?? '';

            return self::DEPT_ROLE[$dept] ?? Role::SLUG_ADMIN;
        }

        return Role::SLUG_ADMIN;
    }

    private function resolveFromLabel(CaseRecord $case, string $event, string $fromStageKey): string
    {
        $stepKey = $this->fromStepKeyForEvent($case, $event, $fromStageKey);

        if ($stepKey !== null) {
            return $this->pathwayConfig->stepLabelForKey($case, $stepKey);
        }

        return $this->pathwayConfig->stepLabelForStage($case, $fromStageKey);
    }

    private function resolveToLabel(CaseRecord $case, string $event, string $fromStageKey, string $targetStage): string
    {
        $stepKey = $this->toStepKeyForEvent($case, $event, $fromStageKey, $targetStage);

        if ($stepKey !== null) {
            return $this->pathwayConfig->stepLabelForKey($case, $stepKey);
        }

        return $this->pathwayConfig->stepLabelForStage($case, $targetStage);
    }

    private function fromStepKeyForEvent(CaseRecord $case, string $event, string $fromStageKey): ?string
    {
        return match ($event) {
            WorkflowEvent::BomDispensed->value => 'warehouse',
            WorkflowEvent::BomFinished->value => 'workshop',
            WorkflowEvent::CashierPaid->value => 'cashier',
            WorkflowEvent::QuoteIssued->value => 'cost_calc',
            default => $this->stepKeyForStage($case, $fromStageKey),
        };
    }

    private function toStepKeyForEvent(CaseRecord $case, string $event, string $fromStageKey, string $targetStage): ?string
    {
        $case->loadMissing(['patient', 'quotes', 'bom']);
        $pathway = $this->pathwayConfig->resolvePathway($case->patient, $case);

        return match ($event) {
            WorkflowEvent::ExamApproved->value, WorkflowEvent::ExamSkipped->value => 'technical',
            WorkflowEvent::SpecSaved->value => 'adjustments',
            WorkflowEvent::AdjustmentsCompleted->value => 'cost_calc',
            WorkflowEvent::CostingCompleted->value => match (true) {
                $case->needsServicesApproval() => 'services_approval',
                $case->isMilitary() => 'operations_wo',
                $pathway === PathwayStep::PATHWAY_ENTITY => 'quote',
                default => 'operations_wo',
            },
            WorkflowEvent::QuoteIssued->value => $case->isCashCivilian()
                ? 'cashier'
                : 'operations_wo',
            WorkflowEvent::SentToCashier->value => 'cashier',
            WorkflowEvent::CashierPaid->value => 'operations_wo',
            WorkflowEvent::ServicesApprovalRequired->value => 'services_approval',
            WorkflowEvent::ServicesApproved->value => 'operations_wo',
            WorkflowEvent::OperationsApproved->value => $this->workshopGatesDispense() ? 'workshop' : 'warehouse',
            WorkflowEvent::BomDispensed->value => 'workshop',
            WorkflowEvent::BomFinished->value => 'delivery',
            WorkflowEvent::Delivered->value => 'delivery',
            WorkflowEvent::ReturnedToAdjustments->value, WorkflowEvent::SpecEditPostWoRollback->value => 'adjustments',
            WorkflowEvent::ReturnedToTechnical->value => 'technical',
            default => $this->stepKeyForStage($case, $targetStage),
        };
    }

    private function stepKeyForStage(CaseRecord $case, string $stageKey): ?string
    {
        $case->loadMissing(['patient', 'quotes', 'bom']);

        return match ($stageKey) {
            CaseRecord::STAGE_RECEPTION => 'reception',
            CaseRecord::STAGE_EXAM => 'exam',
            CaseRecord::STAGE_TECHNICAL => 'technical',
            CaseRecord::STAGE_ADJUSTMENTS => 'adjustments',
            CaseRecord::STAGE_COST_CALC => 'cost_calc',
            CaseRecord::STAGE_SERVICES_APPROVAL => 'services_approval',
            CaseRecord::STAGE_QUOTE => 'quote',
            CaseRecord::STAGE_OPERATIONS => $this->pathwayConfig->entityOperationsStepKey($case),
            CaseRecord::STAGE_CASHIER => 'cashier',
            CaseRecord::STAGE_MANUFACTURING => match (true) {
                $case->bom?->stage === \App\Models\Bom::STAGE_WIP => 'workshop',
                $case->bom?->stage === \App\Models\Bom::STAGE_FINISHED => 'delivery',
                default => 'warehouse',
            },
            CaseRecord::STAGE_READY_DELIVERY, CaseRecord::STAGE_DELIVERED => 'delivery',
            default => null,
        };
    }

    private function workshopGatesDispense(): bool
    {
        return config('workshop.enabled', true);
    }
}
