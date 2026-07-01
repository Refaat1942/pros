<?php

namespace App\Support;

use App\Models\Quote;

/**
 * عرض طباعة عرض السعر — إجمالي صافٍ بعد خصم جهة التعاقد.
 */
final class QuotePrintPresenter
{
    /**
     * @return array{
     *     gross_total: float,
     *     discount_percent: float,
     *     discount_amount: float,
     *     net_total: float,
     *     display_total: float,
     *     has_discount: bool
     * }
     */
    public static function fromQuote(Quote $quote): array
    {
        $quote->loadMissing(['caseRecord.contractCompany', 'items']);

        $gross = round((float) $quote->total, 2);
        $case  = $quote->caseRecord;

        if (! $case) {
            return self::withoutDiscount($gross);
        }

        $split = ContractBillingSplit::forCase($case, $gross);
        $discountAmount = round($gross - $split['patient_share'], 2);

        if ($discountAmount <= 0) {
            return self::withoutDiscount($gross);
        }

        return [
            'gross_total'      => $gross,
            'discount_percent' => (float) $split['company_share_percent'],
            'discount_amount'  => $discountAmount,
            'net_total'        => (float) $split['patient_share'],
            'display_total'    => (float) $split['patient_share'],
            'has_discount'     => true,
        ];
    }

    /** المبلغ المعتمد في OCR والطباعة — صافٍ بعد خصم جهة التعاقد. */
    public static function approvedAmount(Quote $quote): float
    {
        return self::fromQuote($quote)['display_total'];
    }

    /** @return array{gross_total: float, discount_percent: float, discount_amount: float, net_total: float, display_total: float, has_discount: bool} */
    private static function withoutDiscount(float $gross): array
    {
        return [
            'gross_total'      => $gross,
            'discount_percent' => 0.0,
            'discount_amount'  => 0.0,
            'net_total'        => $gross,
            'display_total'    => $gross,
            'has_discount'     => false,
        ];
    }
}
