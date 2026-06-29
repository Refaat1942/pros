<?php

namespace App\Support;

use App\Models\CaseRecord;
use App\Models\ContractCompany;

/**
 * توزيع تكلفة الحالة بين المريض (كاش) وجهة التعاقد (مديونية).
 *
 * discount_percent على الجهة = نسبة ما تتحمّله الجهة من الإجمالي (مثال 20% → 200 من 1000).
 * المريض يدفع الباقي (80% → 800) — سعر العرض/الفاتورة يبقى بالكامل.
 */
final class ContractBillingSplit
{
    /**
     * @return array{
     *     gross_total: float,
     *     patient_share: float,
     *     company_share: float,
     *     company_share_percent: float,
     *     patient_share_percent: float
     * }
     */
    public static function forCase(CaseRecord $case, ?float $grossTotal = null): array
    {
        $case->loadMissing('contractCompany');

        $gross = round($grossTotal ?? self::grossTotal($case), 2);

        if ($case->isMilitary() || ! PatientEntityPresenter::postsContractDebt($case)) {
            return self::patientPaysAll($gross);
        }

        $company = $case->contractCompany
            ?? ($case->contract_company_id
                ? ContractCompany::find($case->contract_company_id)
                : null);

        return $company instanceof ContractCompany
            ? self::forCompany($company, $gross)
            : self::patientPaysAll($gross);
    }

    /**
     * @return array{
     *     gross_total: float,
     *     patient_share: float,
     *     company_share: float,
     *     company_share_percent: float,
     *     patient_share_percent: float
     * }
     */
    public static function forCompany(ContractCompany $company, float $grossTotal): array
    {
        $gross = round(max(0, $grossTotal), 2);
        $companyPct = min(100, max(0, (float) $company->discount_percent));

        if ($companyPct <= 0) {
            return self::patientPaysAll($gross);
        }

        if ($companyPct >= 100) {
            return [
                'gross_total'           => $gross,
                'patient_share'         => 0.0,
                'company_share'         => $gross,
                'company_share_percent' => 100.0,
                'patient_share_percent' => 0.0,
            ];
        }

        $companyShare = round($gross * ($companyPct / 100), 2);
        $patientShare = round($gross - $companyShare, 2);

        return [
            'gross_total'           => $gross,
            'patient_share'         => $patientShare,
            'company_share'         => $companyShare,
            'company_share_percent' => $companyPct,
            'patient_share_percent' => round(100 - $companyPct, 2),
        ];
    }

    public static function patientDue(CaseRecord $case, ?float $grossTotal = null): float
    {
        return self::forCase($case, $grossTotal)['patient_share'];
    }

    public static function companyDue(CaseRecord $case, ?float $grossTotal = null): float
    {
        return self::forCase($case, $grossTotal)['company_share'];
    }

    public static function grossTotal(CaseRecord $case): float
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

        return CaseFinancialSummary::totalCost($case);
    }

    /** @return array{gross_total: float, patient_share: float, company_share: float, company_share_percent: float, patient_share_percent: float} */
    private static function patientPaysAll(float $gross): array
    {
        return [
            'gross_total'           => $gross,
            'patient_share'         => $gross,
            'company_share'         => 0.0,
            'company_share_percent' => 0.0,
            'patient_share_percent' => $gross > 0 ? 100.0 : 0.0,
        ];
    }
}
