<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Exceptions\InvalidWorkflowTransitionException;
use App\Models\CaseRecord;
use Illuminate\Support\Facades\DB;

/**
 * السلطة الوحيدة على stage_key و manufacturing_stage — لا يُعدَّلان خارج هذه الخدمة.
 */
class WorkflowService
{
    /**
     * خريطة الانتقالات: الحدث → [المراحل المسموحة, المرحلة الهدف, manufacturing_stage|null]
     *
     * @var array<string, array{from: list<string>, to: string, mfg: ?string}>
     */
    private const TRANSITIONS = [
        WorkflowEvent::ExamApproved->value => [
            'from' => [CaseRecord::STAGE_RECEPTION, CaseRecord::STAGE_EXAM],
            'to'   => CaseRecord::STAGE_TECHNICAL,
            'mfg'  => null,
        ],
        WorkflowEvent::SpecSaved->value => [
            'from' => [CaseRecord::STAGE_TECHNICAL],
            'to'   => CaseRecord::STAGE_COST_CALC,
            'mfg'  => null,
        ],
        WorkflowEvent::PricingCompletedCivilian->value => [
            'from' => [CaseRecord::STAGE_COST_CALC],
            'to'   => CaseRecord::STAGE_WAITING_RETURN,
            'mfg'  => null,
        ],
        WorkflowEvent::PricingCompletedMilitary->value => [
            'from' => [CaseRecord::STAGE_COST_CALC],
            'to'   => CaseRecord::STAGE_MANUFACTURING,
            'mfg'  => CaseRecord::MFG_WAREHOUSE,
        ],
        WorkflowEvent::ApprovalScanned->value => [
            'from' => [CaseRecord::STAGE_WAITING_RETURN],
            'to'   => CaseRecord::STAGE_MANUFACTURING,
            'mfg'  => CaseRecord::MFG_WAREHOUSE,
        ],
        WorkflowEvent::BomDispensed->value => [
            'from' => [CaseRecord::STAGE_WAITING_RETURN, CaseRecord::STAGE_MANUFACTURING],
            'to'   => CaseRecord::STAGE_MANUFACTURING,
            'mfg'  => CaseRecord::MFG_ISSUE,
        ],
        WorkflowEvent::BomFinished->value => [
            'from' => [CaseRecord::STAGE_MANUFACTURING],
            'to'   => CaseRecord::STAGE_READY_DELIVERY,
            'mfg'  => null,
        ],
        WorkflowEvent::Delivered->value => [
            'from' => [CaseRecord::STAGE_READY_DELIVERY],
            'to'   => CaseRecord::STAGE_DELIVERED,
            'mfg'  => null,
        ],
    ];

    public function advance(CaseRecord $case, string $event): void
    {
        DB::transaction(function () use ($case, $event) {
            $case = CaseRecord::lockForUpdate()->findOrFail($case->id);

            $rule = self::TRANSITIONS[$event] ?? null;

            if (! $rule || ! in_array($case->stage_key, $rule['from'], true)) {
                throw InvalidWorkflowTransitionException::forEvent($event, $case->stage_key);
            }

            $before = [
                'stage_key'           => $case->stage_key,
                'manufacturing_stage' => $case->manufacturing_stage,
            ];

            $updates = ['stage_key' => $rule['to']];

            if ($rule['mfg'] !== null) {
                $updates['manufacturing_stage'] = $rule['mfg'];
            }

            if ($event === WorkflowEvent::Delivered->value) {
                $updates['delivered_at'] = now()->toDateString();
            }

            $case->update($updates);

            AuditService::log(
                action:      'update',
                description: "انتقال workflow: {$event} — {$before['stage_key']} → {$rule['to']}",
                tag:         'medical',
                before:      $before,
                after:       [
                    'stage_key'           => $case->stage_key,
                    'manufacturing_stage' => $case->manufacturing_stage,
                ],
            );
        });
    }
}
