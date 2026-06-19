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
        if (! in_array($case->stage_key, [
            CaseRecord::STAGE_MANUFACTURING,
            CaseRecord::STAGE_READY_DELIVERY,
        ], true)) {
            abort(422, 'الحالة ليست في مرحلة التصنيع أو التركيب.');
        }

        $before = FittingTrial::where('case_id', $case->id)->first()?->toArray();

        $trial = FittingTrial::updateOrCreate(
            ['case_id' => $case->id],
            array_filter([
                'trial1_date' => $data['trial1_date'] ?? null,
                'trial2_date' => $data['trial2_date'] ?? null,
                'notes'       => $data['notes'] ?? null,
                'status'      => $data['status'] ?? FittingTrial::STATUS_PENDING,
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
