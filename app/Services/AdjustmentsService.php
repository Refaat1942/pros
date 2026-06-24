<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\CaseRecord;

/**
 * المعدلات (الخطوة 4) — مرحلة المراجعة والإضافة قبل التكاليف.
 *
 * يصل مستشار المعدلات بعد إرسال التوصيف الفني مباشرةً. يرى بنود الفني للقراءة
 * فقط (source=spec) ولا يستطيع تعديلها أو حذفها، لكنه يضيف بنوداً/مكوّنات فنية
 * إضافية لنفس الـ BOM (source=adjustment). عند الإغلاق يُجمَّع الـ BOM ويُدفع
 * لمحرك التكاليف ثم عرض السعر ثم مكتب التشغيل.
 */
class AdjustmentsService
{
    public function __construct(
        private readonly BomService $bomService,
        private readonly CostingService $costingService,
    ) {
    }

    /**
     * إضافة بنود مستشار المعدلات — البنود الأصلية (الفني) تبقى للقراءة فقط.
     *
     * @param  list<array{stock_item_code: string, name?: string, qty: int}>  $items
     */
    public function addItems(CaseRecord $case, array $items): Bom
    {
        if ($case->stage_key !== CaseRecord::STAGE_ADJUSTMENTS) {
            abort(422, 'الحالة ليست في مرحلة المعدلات.');
        }

        return $this->bomService->appendAdjustmentItems($case, $items);
    }

    /**
     * إغلاق مرحلة المعدلات — دفع الـ BOM لمحرك التكاليف والتوقف عند cost_calc.
     */
    public function complete(CaseRecord $case): CaseRecord
    {
        $case = $this->costingService->receiveFromAdjustments($case);
        $case->clearReworkNotice();

        return $case;
    }
}
