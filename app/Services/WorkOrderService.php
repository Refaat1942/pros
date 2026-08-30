<?php

namespace App\Services;

use App\Models\CaseRecord;

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

        $last = CaseRecord::where('work_order_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('work_order_no')
            ->value('work_order_no');

        $num = $last
            ? ((int) substr($last, strlen($prefix)) + 1)
            : 1;

        // H-7: تفادي 500 عند سباق نادر — نتخطّى أي رقم مستخدَم بالفعل (مثل نمط
        // nextQuoteNo/nextPaymentNo). التنسيق يتّسع تلقائياً بعد 9999 لنفس السنة.
        do {
            $workOrderNo = sprintf('%s%04d', $prefix, $num++);
        } while (CaseRecord::where('work_order_no', $workOrderNo)->exists());

        CaseRecord::where('id', $case->id)->update(['work_order_no' => $workOrderNo]);

        return $workOrderNo;
    }
}
