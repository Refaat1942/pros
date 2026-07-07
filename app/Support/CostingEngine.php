<?php

namespace App\Support;

/**
 * محرك التكاليف الجديد — يحسب سعر البيع من المواد + المكوّنات + الربح حسب النمط.
 *
 * الصيغة (مثبّتة حسب قرار العميل):
 *   component.amount = rate% × materials
 *   components_total = Σ components
 *   total_cost       = materials + components_total
 *   selling_price    = total_cost × (1 + profit%)
 *
 * نمط «الصرف السريع» بلا مكوّنات: selling_price = materials × (1 + profit%).
 * عند عدم اختيار نمط (null): selling_price = materials (بلا ربح).
 */
final class CostingEngine
{
    /**
     * @param  array{key:string, label:string, profit_rate:float, has_components:bool, components:list<array{label:string, rate:float}>}|null  $mode
     * @return array{
     *   mode_key: string|null,
     *   mode_label: string|null,
     *   materials_total: float,
     *   components: list<array{label:string, rate:float, amount:float}>,
     *   components_total: float,
     *   total_cost: float,
     *   profit_rate: float,
     *   profit_amount: float,
     *   selling_price: float
     * }
     */
    public function calculate(?array $mode, float $materialsTotal): array
    {
        $materials = round(max(0, $materialsTotal), 2);

        $components = [];
        $componentsTotal = 0.0;

        if ($mode !== null && ($mode['has_components'] ?? false)) {
            foreach ($mode['components'] ?? [] as $component) {
                $rate = round((float) ($component['rate'] ?? 0), 2);
                $amount = round($materials * ($rate / 100), 2);
                $componentsTotal += $amount;

                $components[] = [
                    'label' => (string) ($component['label'] ?? ''),
                    'rate' => $rate,
                    'amount' => $amount,
                ];
            }
        }

        $componentsTotal = round($componentsTotal, 2);
        $totalCost = round($materials + $componentsTotal, 2);

        $profitRate = $mode !== null ? round((float) ($mode['profit_rate'] ?? 0), 2) : 0.0;
        $sellingPrice = round($totalCost * (1 + ($profitRate / 100)), 2);
        $profitAmount = round($sellingPrice - $totalCost, 2);

        return [
            'mode_key' => $mode['key'] ?? null,
            'mode_label' => $mode['label'] ?? null,
            'materials_total' => $materials,
            'components' => $components,
            'components_total' => $componentsTotal,
            'total_cost' => $totalCost,
            'profit_rate' => $profitRate,
            'profit_amount' => $profitAmount,
            'selling_price' => $sellingPrice,
        ];
    }
}
