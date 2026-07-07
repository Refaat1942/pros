<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Patient;
use App\Support\CaseFinancialSummary;

/**
 * إصدار الفاتورة التجارية الختامية عند التسليم — مدني فقط.
 */
class InvoiceService
{
    /**
     * @return array{invoice_no: ?string, invoice_total: float}
     */
    public function issueFinalInvoice(CaseRecord $case): array
    {
        if ($case->patient_type === Patient::TYPE_MILITARY || $case->isMilitary()) {
            return [
                'invoice_no' => null,
                'invoice_total' => (float) ($case->total_cost ?? 0),
            ];
        }

        if ($case->invoice_no) {
            return [
                'invoice_no' => $case->invoice_no,
                'invoice_total' => (float) $case->invoice_total,
            ];
        }

        $total = CaseFinancialSummary::totalCost($case->loadMissing(['pricingRequest', 'bom.items']));

        if ($total <= 0) {
            abort(422, 'لا يمكن إصدار فاتورة — مبلغ العرض غير صالح.');
        }

        $invoiceNo = $this->nextInvoiceNo();

        CaseRecord::where('id', $case->id)->update([
            'invoice_no' => $invoiceNo,
            'invoice_total' => $total,
            'total_cost' => $total,
        ]);

        AuditService::log(
            action: 'invoice',
            description: "إصدار فاتورة تجارية ختامية — {$invoiceNo}",
            tag: 'financial',
            after: [
                'case_id' => $case->id,
                'invoice_no' => $invoiceNo,
                'invoice_total' => $total,
            ],
        );

        return ['invoice_no' => $invoiceNo, 'invoice_total' => $total];
    }

    /**
     * يُستدعى داخل معاملة DeliveryService::close — القفل يمنع ترقيم فاتورة مكرّر
     * عند تسليم حالتين متزامنتين.
     */
    private function nextInvoiceNo(): string
    {
        $year = now()->year;
        $prefix = "INV-{$year}-";

        $last = CaseRecord::where('invoice_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('invoice_no')
            ->value('invoice_no');

        $num = $last
            ? ((int) substr($last, strlen($prefix)) + 1)
            : 1;

        return sprintf('%s%04d', $prefix, $num);
    }
}
