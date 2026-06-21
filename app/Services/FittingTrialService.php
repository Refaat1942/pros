<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\FittingTrial;

/**
 * تجارب التركيب والمعدلات — 1:1 مع الحالة.
 */
class FittingTrialService
{
    /**
     * إنشاء أو تحديث سجل تجربة تركيب.
     *
     * @param  array{trial1_date?: ?string, trial2_date?: ?string, notes?: ?string, status?: string}  $data
     */
    public function save(CaseRecord $case, array $data): FittingTrial
    {
        $case->loadMissing('bom');

        if (! $case->isEligibleForAdjustments()) {
            abort(422, 'الحالة غير مؤهلة لتجربة التركيب — يجب أن تكون في الورشة أو جاهزة للتسليم.');
        }

        $before = FittingTrial::where('case_id', $case->id)->first()?->toArray();

        $status = $data['status'] ?? match (true) {
            ! empty($data['trial2_date']) => FittingTrial::STATUS_COMPLETED,
            ! empty($data['trial1_date']) => FittingTrial::STATUS_TRIAL1,
            default => FittingTrial::STATUS_PENDING,
        };

        $trial = FittingTrial::updateOrCreate(
            ['case_id' => $case->id],
            array_filter([
                'trial1_date' => $data['trial1_date'] ?? null,
                'trial2_date' => $data['trial2_date'] ?? null,
                'notes'       => $data['notes'] ?? null,
                'status'      => $status,
            ], fn ($v) => $v !== null),
        );

        AuditService::log(
            action:      $before ? 'update' : 'create',
            description: "تسجيل تجربة تركيب — {$case->case_no}",
            tag:         'medical',
            before:      $before,
            after:       $trial->toArray(),
        );

        return $trial->fresh();
    }
}
