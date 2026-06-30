<?php

namespace App\Support;

use App\Models\ContractCompany;
use App\Services\SettingService;

/**
 * محرك حساب التكاليف الإضافية (Overhead) — نسب ديناميكية فوق WAC المواد.
 */
final class OverheadCostingEngine
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    /**
     * @return array{
     *     wac_total: float,
     *     overheads: list<array{key: string, label: string, rate: float, amount: float}>,
     *     overhead_total: float,
     *     gross_before_discount: float,
     *     discount_percent: float,
     *     discount_amount: float,
     *     net_offer_total: float,
     *     rates_sum: float
     * }
     */
    public function calculate(float $wacTotal, ?ContractCompany $company = null): array
    {
        $wac = round(max(0, $wacTotal), 2);
        $definitions = $this->settings->overheadRateDefinitions();

        $overheads = [];
        $overheadTotal = 0.0;

        foreach ($definitions as $definition) {
            $amount = round($wac * ($definition['rate'] / 100), 2);
            $overheadTotal += $amount;

            $overheads[] = [
                'key'    => $definition['key'],
                'label'  => $definition['label'],
                'rate'   => $definition['rate'],
                'amount' => $amount,
            ];
        }

        $overheadTotal = round($overheadTotal, 2);
        $grossBeforeDiscount = round($wac + $overheadTotal, 2);

        $discountPct = 0.0;
        if ($company instanceof ContractCompany && $company->is_contracted) {
            $discountPct = min(100, max(0, (float) $company->discount_percent));
        }

        $discountAmount = $discountPct > 0
            ? round($grossBeforeDiscount * ($discountPct / 100), 2)
            : 0.0;

        $netOffer = round($grossBeforeDiscount - $discountAmount, 2);

        return [
            'wac_total'             => $wac,
            'overheads'             => $overheads,
            'overhead_total'        => $overheadTotal,
            'gross_before_discount' => $grossBeforeDiscount,
            'discount_percent'      => $discountPct,
            'discount_amount'       => $discountAmount,
            'net_offer_total'       => $netOffer,
            'rates_sum'             => $this->settings->overheadRatesSum(),
        ];
    }

    public function grossBeforeDiscount(float $wacTotal): float
    {
        return $this->calculate($wacTotal)['gross_before_discount'];
    }
}
