<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\CaseRecord;
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

        $updated = DB::transaction(function () use ($case, $event, &$fromStageKey) {
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

            $updates = ['stage_key' => $rule['to']];

            if ($rule['mfg'] !== null) {
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

        // إشعار اللوحة التالية بعد نجاح الانتقال — لا يُعطّل التدفق إن فشل الإرسال.
        try {
            $this->notifications->notifyTransition($updated, $event, $fromStageKey);
        } catch (\Throwable $e) {
            report($e);
        }
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
}
