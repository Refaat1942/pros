<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Support\DocumentSequence;

/**
 * توليد أرقام أوامر الشغل — WO-YYYY-NNNN
 */
class WorkOrderService
{
    /**
     * يُولِّد رقم أمر شغل فريداً ويُخزّنه على الحالة.
     */
    public function generate(CaseRecord $case): string
    {
        if ($case->work_order_no) {
            return $case->work_order_no;
        }

        $year = now()->year;
        $prefix = "WO-{$year}-";

        do {
            $workOrderNo = $prefix.sprintf('%04d', DocumentSequence::next("WO-{$year}", fn () => DocumentSequence::maxSuffix(CaseRecord::class, 'work_order_no', $prefix)));
        } while (CaseRecord::where('work_order_no', $workOrderNo)->exists());

        CaseRecord::where('id', $case->id)->update(['work_order_no' => $workOrderNo]);

        return $workOrderNo;
    }
}
