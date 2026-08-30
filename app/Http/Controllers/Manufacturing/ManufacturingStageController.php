<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PreparesDocumentPrint;
use App\Models\CaseRecord;
use Illuminate\View\View;

class ManufacturingStageController extends Controller
{
    use PreparesDocumentPrint;
    /**
     * إذن شغل — للطباعة من مكتب التشغيل.
     */
    public function printWorkOrder(CaseRecord $case): View
    {
        abort_unless($case->work_order_no, 404, 'لا يوجد أمر تشغيل لهذه الحالة.');

        $case->load(['patient', 'contractCompany', 'bom.items']);

        abort_unless($case->bom, 404, 'لا توجد BOM مرتبطة بهذه الحالة.');

        return view('prints.work-order', [
            'case' => $case,
            'autoPrint' => true,
            'documentTemplate' => $this->documentTemplateForPrint('work_order', $case),
        ]);
    }
}
