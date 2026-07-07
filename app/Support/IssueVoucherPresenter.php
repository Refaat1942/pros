<?php

namespace App\Support;

use App\Models\Bom;
use App\Models\Quote;
use Illuminate\Support\Collection;

class IssueVoucherPresenter
{
    /**
     * @return array{voucher_no: string, patient_name: string, company_name: string, items: Collection}
     */
    public static function fromBom(Bom $bom): array
    {
        $bom->loadMissing(['items', 'caseRecord.patient']);

        $case = $bom->caseRecord;
        $quote = $case
            ? Quote::where('case_id', $case->id)->orderByDesc('id')->first()
            : null;

        return [
            'voucher_no' => $quote?->order_ref ?: ($bom->order_ref ?: ($case?->order_ref ?? '—')),
            'patient_name' => $quote?->patient_name ?: ($bom->patient_name ?: ($case?->patient?->name ?? '—')),
            'company_name' => $quote?->company_name ?: ($case?->displayEntity() ?? '—'),
            'items' => $bom->items,
        ];
    }

    public static function printUrl(Bom $bom): ?string
    {
        if (! $bom->case_id) {
            return null;
        }

        $quote = Quote::where('case_id', $bom->case_id)->orderByDesc('id')->first();

        if ($quote) {
            return route('technical.quote.print-issue-voucher', $quote);
        }

        return route('technical.bom.print-issue-voucher', $bom);
    }
}
