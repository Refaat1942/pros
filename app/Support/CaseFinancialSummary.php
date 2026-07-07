<?php

namespace App\Support;

use App\Models\CaseRecord;
use App\Models\PricingRequest;
use Illuminate\Support\Facades\Gate;

/**
 * ملخص مالي للحالة — للعرض بعد التسليم وفي تقارير الأدمن.
 */
final class CaseFinancialSummary
{
    /**
     * التكلفة الداخلية (WAC) تظهر لمن يملك صلاحية "view-costs" (الأدمن دائماً).
     */
    public static function canSeeInternalCost(): bool
    {
        return Gate::allows('view-costs');
    }

    /**
     * نِسَب الربحية العسكرية — للسوبر أدمن فقط (صلاحية view-military-profit).
     */
    public static function canSeeMilitaryProfit(): bool
    {
        return Gate::allows('view-military-profit');
    }

    /** التكلفة الحقيقية (WAC) للحالة — لمن يملك صلاحية رؤية التكاليف. */
    public static function internalCost(CaseRecord $case): float
    {
        return (float) $case->internal_cost;
    }

    public static function totalCost(CaseRecord $case): float
    {
        foreach ([
            (float) $case->invoice_total,
            (float) $case->quote_total,
            (float) $case->total_cost,
        ] as $amount) {
            if ($amount > 0) {
                return $amount;
            }
        }

        $pricing = self::resolvePricingRequest($case);

        if ($pricing && (float) $pricing->computed_total > 0) {
            return (float) $pricing->computed_total;
        }

        $case->loadMissing('bom.items');

        if ($case->bom?->items->isNotEmpty()) {
            return round(
                $case->bom->items->sum(fn ($item) => $item->qty * (float) $item->unit_cost),
                2
            );
        }

        return 0.0;
    }

    /** المبلغ المعروض للفوترة/الطباعة — صافٍ بعد خصم جهة التعاقد. */
    public static function billableAmount(CaseRecord $case): float
    {
        $case->loadMissing('contractCompany');

        $gross = ContractBillingSplit::grossTotal($case);

        if ($gross <= 0) {
            $gross = self::totalCost($case);
        }

        return ContractBillingSplit::patientDue($case, $gross);
    }

    public static function paidAmount(CaseRecord $case, ?float $total = null): float
    {
        $paid = (float) ($case->paid ?? 0);

        if ($paid > 0) {
            return $paid;
        }

        $total ??= self::totalCost($case);

        if ($case->stage_key === CaseRecord::STAGE_DELIVERED && $total > 0) {
            return ContractBillingSplit::patientDue($case, $total);
        }

        return 0.0;
    }

    /** يثبّت total_cost (و paid للحالات المسلّمة) بعد إغلاق التسليم. */
    public static function syncOnDelivery(CaseRecord $case): void
    {
        $case->loadMissing(['pricingRequest', 'bom.items']);
        $total = self::totalCost($case);

        if ($total <= 0) {
            return;
        }

        $updates = [];

        if ((float) $case->total_cost <= 0) {
            $updates['total_cost'] = $total;
        }

        if ((float) $case->paid <= 0) {
            $updates['paid'] = ContractBillingSplit::patientDue($case, $total);
        }

        if ($updates !== []) {
            CaseRecord::where('id', $case->id)->update($updates);
        }
    }

    private static function resolvePricingRequest(CaseRecord $case): ?PricingRequest
    {
        if ($case->relationLoaded('pricingRequest') && $case->pricingRequest) {
            return $case->pricingRequest;
        }

        if ($case->pricing_request_id) {
            $pricing = $case->pricingRequest()->first(['id', 'case_id', 'computed_total']);

            if ($pricing) {
                return $pricing;
            }
        }

        return PricingRequest::query()
            ->where('case_id', $case->id)
            ->first(['id', 'case_id', 'computed_total']);
    }
}
