<?php

namespace App\Support;

use App\Models\ContractCompany;
use App\Services\SettingService;

/**
 * محرك حساب التكاليف الإضافية (Overhead) — نسب ديناميكية فوق إجمالي المواد (أعلى سعر شراء).
 *
 * إجمالي السعر قبل الخصم = مجموع بنود BOM (توصيف + معدلات) بأعلى سعر مسجّل.
 * بنود النسب تُوزّع هذا الإجمالي — لا تُضاف فوق WAC الداخلي.
 */
final class OverheadCostingEngine
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    /**
     * @return array{
     *     materials_total: float,
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
    public function calculate(float $materialsTotal, ?ContractCompany $company = null): array
    {
        $materials = round(max(0, $materialsTotal), 2);
        $definitions = $this->settings->overheadRateDefinitions();

        $overheads = [];
        $overheadTotal = 0.0;

        foreach ($definitions as $definition) {
            $amount = round($materials * ($definition['rate'] / 100), 2);
            $overheadTotal += $amount;

            $overheads[] = [
                'key'    => $definition['key'],
                'label'  => $definition['label'],
                'rate'   => $definition['rate'],
                'amount' => $amount,
            ];
        }

        $overheadTotal = round($overheadTotal, 2);
        $grossBeforeDiscount = $materials;

        $discountPct = 0.0;
        if ($company instanceof ContractCompany && $company->is_contracted) {
            $discountPct = min(100, max(0, (float) $company->discount_percent));
        }

        $discountAmount = $discountPct > 0
            ? round($grossBeforeDiscount * ($discountPct / 100), 2)
            : 0.0;

        $netOffer = round($grossBeforeDiscount - $discountAmount, 2);

        return [
            'materials_total'       => $materials,
            'wac_total'             => $materials,
            'overheads'             => $overheads,
            'overhead_total'        => $overheadTotal,
            'gross_before_discount' => $grossBeforeDiscount,
            'discount_percent'      => $discountPct,
            'discount_amount'       => $discountAmount,
            'net_offer_total'       => $netOffer,
            'rates_sum'             => $this->settings->overheadRatesSum(),
        ];
    }

    public function grossBeforeDiscount(float $materialsTotal): float
    {
        return round(max(0, $materialsTotal), 2);
    }
}
