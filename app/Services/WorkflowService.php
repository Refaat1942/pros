<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\CaseRecord;
use App\Models\Role;
use App\Services\Notifications\NotificationService;
use App\Services\PathwayTransitionMessageService;
use Illuminate\Support\Facades\DB;

/**
 * السلطة الوحيدة على stage_key و manufacturing_stage — لا يُعدَّلان خارج هذه الخدمة.
 */
class WorkflowService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly PathwayTransitionMessageService $transitionMessages,
    ) {}

    /**
     * خريطة الانتقالات: الحدث → [المراحل المسموحة, المرحلة الهدف, manufacturing_stage|null]
     *
     * @var array<string, array{from: list<string>, to: string, mfg: ?string}>
     */
    private const TRANSITIONS = [
        // الكشف تم واعتُمد — قد تكون الحالة في الاستقبال (أُنشئت الآن) أو في الكشف.
        WorkflowEvent::ExamApproved->value => [
            'from' => [CaseRecord::STAGE_RECEPTION, CaseRecord::STAGE_EXAM],
            'to' => CaseRecord::STAGE_TECHNICAL,
            'mfg' => null,
        ],
        // الكشف اختياري — يقفز من الاستقبال مباشرةً للتوصيف.
        WorkflowEvent::ExamSkipped->value => [
            'from' => [CaseRecord::STAGE_RECEPTION],
            'to' => CaseRecord::STAGE_TECHNICAL,
            'mfg' => null,
        ],
        // التوصيف الفني → المعدلات (مراجعة وإضافة بنود قبل التسعير).
        WorkflowEvent::SpecSaved->value => [
            'from' => [CaseRecord::STAGE_TECHNICAL],
            'to' => CaseRecord::STAGE_ADJUSTMENTS,
            'mfg' => null,
        ],
        // المعدلات → التكاليف (تشغيل محرك الاحتساب).
        WorkflowEvent::AdjustmentsCompleted->value => [
            'from' => [CaseRecord::STAGE_ADJUSTMENTS],
            'to' => CaseRecord::STAGE_COST_CALC,
            'mfg' => null,
        ],
        // التكاليف → عرض السعر.
        WorkflowEvent::CostingCompleted->value => [
            'from' => [CaseRecord::STAGE_COST_CALC],
            'to' => CaseRecord::STAGE_QUOTE,
            'mfg' => null,
        ],
        // التكاليف → تصديق إدارة الخدمات (مسار عسكري خاص).
        WorkflowEvent::ServicesApprovalRequired->value => [
            'from' => [CaseRecord::STAGE_COST_CALC],
            'to' => CaseRecord::STAGE_SERVICES_APPROVAL,
            'mfg' => null,
        ],
        WorkflowEvent::ServicesApproved->value => [
            'from' => [CaseRecord::STAGE_SERVICES_APPROVAL],
            'to' => CaseRecord::STAGE_OPERATIONS,
            'mfg' => null,
        ],
        // عرض السعر → مكتب التشغيل (مركز القرار).
        WorkflowEvent::QuoteIssued->value => [
            'from' => [CaseRecord::STAGE_QUOTE],
            'to' => CaseRecord::STAGE_OPERATIONS,
            'mfg' => null,
        ],
        // مكتب التشغيل (كاش): إصدار عرض السعر → بانتظار الدفع في الخزنة.
        WorkflowEvent::SentToCashier->value => [
            'from' => [CaseRecord::STAGE_OPERATIONS],
            'to' => CaseRecord::STAGE_CASHIER,
            'mfg' => null,
        ],
        // الخزنة: تأكيد استلام المبلغ → إعادة لمكتب التشغيل لاعتماد إصدار أمر الشغل.
        WorkflowEvent::CashierPaid->value => [
            'from' => [CaseRecord::STAGE_CASHIER],
            'to' => CaseRecord::STAGE_OPERATIONS,
            'mfg' => null,
        ],
        // مكتب التشغيل: اعتماد → المخزن للصرف + إصدار أمر الشغل (حجز فوري في الخلفية).
        WorkflowEvent::OperationsApproved->value => [
            'from' => [CaseRecord::STAGE_OPERATIONS],
            'to' => CaseRecord::STAGE_MANUFACTURING,
            'mfg' => CaseRecord::MFG_WAREHOUSE,
        ],
        // مكتب التشغيل: رفض/تعديل → إعادة للمعدلات.
        WorkflowEvent::ReturnedToAdjustments->value => [
            'from' => [CaseRecord::STAGE_OPERATIONS],
            'to' => CaseRecord::STAGE_ADJUSTMENTS,
            'mfg' => null,
        ],
        // مكتب التشغيل/المعدلات: رفض جذري → إعادة للتوصيف.
        WorkflowEvent::ReturnedToTechnical->value => [
            'from' => [CaseRecord::STAGE_OPERATIONS, CaseRecord::STAGE_ADJUSTMENTS],
            'to' => CaseRecord::STAGE_TECHNICAL,
            'mfg' => null,
        ],
        WorkflowEvent::SpecEditPostWoRollback->value => [
            'from' => [CaseRecord::STAGE_MANUFACTURING],
            'to' => CaseRecord::STAGE_ADJUSTMENTS,
            'mfg' => null,
        ],
        // المخزن: صرف المواد بالباركود → دخول الورشة.
        WorkflowEvent::BomDispensed->value => [
            'from' => [CaseRecord::STAGE_MANUFACTURING],
            'to' => CaseRecord::STAGE_MANUFACTURING,
            'mfg' => CaseRecord::MFG_ISSUE,
        ],
        WorkflowEvent::BomFinished->value => [
            'from' => [CaseRecord::STAGE_MANUFACTURING],
            'to' => CaseRecord::STAGE_READY_DELIVERY,
            'mfg' => null,
        ],
        WorkflowEvent::Delivered->value => [
            'from' => [CaseRecord::STAGE_READY_DELIVERY],
            'to' => CaseRecord::STAGE_DELIVERED,
            'mfg' => null,
        ],
    ];

    public function advance(CaseRecord $case, string $event): void
    {
        $fromStageKey = null;
        $beforeSnapshot = null;

        $updated = DB::transaction(function () use ($case, $event, &$fromStageKey, &$beforeSnapshot) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $rule = self::TRANSITIONS[$event] ?? null;

            if (! $rule || ! in_array($case->stage_key, $rule['from'], true)) {
                throw InvalidWorkflowTransitionException::forEvent($event, $case->stage_key);
            }

            $before = [
                'stage_key' => $case->stage_key,
                'manufacturing_stage' => $case->manufacturing_stage,
            ];
            $fromStageKey = $before['stage_key'];
            $beforeSnapshot = $before;

            $updates = ['stage_key' => $rule['to']];

            if ($rule['to'] !== CaseRecord::STAGE_MANUFACTURING) {
                $updates['manufacturing_stage'] = null;
            } elseif ($rule['mfg'] !== null) {
                $updates['manufacturing_stage'] = $rule['mfg'];
            }

            if ($event === WorkflowEvent::Delivered->value) {
                $updates['delivered_at'] = now();
            }

            $case->update($updates);

            $transferMessage = $this->transitionMessages->transferMessage(
                $case->fresh(['patient']),
                $event,
                $before['stage_key'],
            );

            AuditService::log(
                action: 'update',
                description: "انتقال workflow: {$transferMessage}",
                tag: 'medical',
                before: $before,
                after: [
                    'stage_key' => $case->stage_key,
                    'manufacturing_stage' => $case->manufacturing_stage,
                ],
            );

            return $case;
        });

        $updated = $this->finalizeAfterTransition($updated);

        $actingRole = $this->actingRoleForEvent($event, $beforeSnapshot ?? ['stage_key' => $fromStageKey, 'manufacturing_stage' => null]);

        if ($actingRole !== null) {
            try {
                $this->notifications->markCaseReadForRole($updated->id, $actingRole);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // إشعار اللوحة التالية بعد نجاح الانتقال — لا يُعطّل التدفق إن فشل الإرسال.
        try {
            $this->notifications->notifyTransition($updated, $event, $fromStageKey);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * انتقال إداري لتخطي خطوة — يُحرّك الحالة إلى الخطوة التالية في المصمم.
     *
     * @param  array<string, mixed>  $targetStep
     */
    public function forceAdvanceToStep(CaseRecord $case, array $targetStep): CaseRecord
    {
        $stageKey = ($targetStep['stage_keys'] ?? [])[0] ?? null;

        if (! $stageKey) {
            abort(422, 'الخطوة التالية غير معرّفة في مسار العمل.');
        }

        return DB::transaction(function () use ($case, $targetStep, $stageKey) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $before = [
                'stage_key' => $case->stage_key,
                'manufacturing_stage' => $case->manufacturing_stage,
            ];

            $updates = ['stage_key' => $stageKey];
            $stepKey = (string) ($targetStep['key'] ?? '');

            $mfg = match ($stepKey) {
                'warehouse' => CaseRecord::MFG_WAREHOUSE,
                'workshop' => CaseRecord::MFG_ISSUE,
                default => null,
            };

            if ($stageKey === CaseRecord::STAGE_MANUFACTURING && $mfg !== null) {
                $updates['manufacturing_stage'] = $mfg;
            } elseif ($stageKey !== CaseRecord::STAGE_MANUFACTURING) {
                $updates['manufacturing_stage'] = null;
            }

            $case->update($updates);

            AuditService::log(
                action: 'skip',
                description: "تخطي إلى {$targetStep['label']} — {$case->case_no}",
                tag: 'medical',
                before: $before,
                after: [
                    'stage_key' => $case->stage_key,
                    'manufacturing_stage' => $case->manufacturing_stage,
                ],
            );

            return $case->fresh();
        });
    }

    /**
     * بعد أي انتقال — تطبيق التخطي التلقائي للمراحل الاختيارية (مثل المعدلات العسكرية).
     */
    public function finalizeAfterTransition(CaseRecord $case): CaseRecord
    {
        $case = $case->fresh();

        if (! app(WorkflowPolicyService::class)->shouldAutoSkip($case)) {
            return $case;
        }

        return app(CaseWorkflowSkipService::class)->applyConfiguredAutoSkip($case);
    }

    /** @param  array{stage_key: string, manufacturing_stage: ?string}  $before */
    private function actingRoleForEvent(string $event, array $before): ?string
    {
        if ($before['stage_key'] === CaseRecord::STAGE_MANUFACTURING) {
            return match ($before['manufacturing_stage']) {
                CaseRecord::MFG_WAREHOUSE => Role::SLUG_TECHNICAL,
                CaseRecord::MFG_ISSUE, CaseRecord::MFG_WORKSHOP => Role::SLUG_WORKSHOP,
                default => Role::SLUG_WORKSHOP,
            };
        }

        return match ($event) {
            WorkflowEvent::ExamApproved->value,
            WorkflowEvent::ExamSkipped->value => Role::SLUG_RECEPTION,
            WorkflowEvent::SpecSaved->value => Role::SLUG_SPEC,
            WorkflowEvent::AdjustmentsCompleted->value => Role::SLUG_ADJUSTMENTS,
            WorkflowEvent::CostingCompleted->value => Role::SLUG_COSTING,
            WorkflowEvent::ServicesApprovalRequired->value,
            WorkflowEvent::ServicesApproved->value => Role::SLUG_ADMIN,
            WorkflowEvent::QuoteIssued->value,
            WorkflowEvent::SentToCashier->value,
            WorkflowEvent::OperationsApproved->value,
            WorkflowEvent::ReturnedToAdjustments->value,
            WorkflowEvent::ReturnedToTechnical->value => Role::SLUG_OPERATIONS,
            WorkflowEvent::CashierPaid->value => Role::SLUG_CASHIER,
            WorkflowEvent::BomDispensed->value => Role::SLUG_TECHNICAL,
            WorkflowEvent::BomFinished->value => Role::SLUG_WORKSHOP,
            WorkflowEvent::Delivered->value => Role::SLUG_RECEPTION,
            WorkflowEvent::SpecEditPostWoRollback->value => Role::SLUG_ADJUSTMENTS,
            default => null,
        };
    }
}
