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

        $components = $mode !== null && ($mode['has_components'] ?? false)
            ? $this->allocateComponents($materials, $mode['components'] ?? [])
            : [];
        $componentsTotal = round(array_sum(array_column($components, 'amount')), 2);
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

    /**
     * التسعير التلقائي المدمج: بنود «الطرف الصناعي» تأخذ المكوّنات + 95%، وبنود
     * «الصرف السريع» تأخذ 40% مباشرة بلا مكوّنات، ثم يُجمع سعر البيع الكلي.
     *
     * @param  array{profit_rate:float, has_components:bool, components:list<array{label:string, rate:float}>}|null  $limbProfile
     * @param  array{profit_rate:float}|null  $quickProfile
     * @return array{
     *   mode_key: string,
     *   materials_total: float,
     *   base_materials: float,
     *   quick_materials: float,
     *   components: list<array{label:string, rate:float, amount:float}>,
     *   components_total: float,
     *   base_total_cost: float,
     *   base_profit_rate: float,
     *   base_profit_amount: float,
     *   base_selling: float,
     *   quick_profit_rate: float,
     *   quick_profit_amount: float,
     *   quick_selling: float,
     *   total_cost: float,
     *   profit_rate: float,
     *   profit_amount: float,
     *   selling_price: float
     * }
     */
    public function calculateSplit(?array $limbProfile, ?array $quickProfile, float $baseMaterials, float $quickMaterials): array
    {
        $base = round(max(0, $baseMaterials), 2);
        $quick = round(max(0, $quickMaterials), 2);

        // مكوّنات الطرف الصناعي — تُحسب على مواد الطرف فقط (لا تُطبَّق على الصرف السريع).
        $components = $base > 0 && $limbProfile !== null && ($limbProfile['has_components'] ?? false)
            ? $this->allocateComponents($base, $limbProfile['components'] ?? [])
            : [];
        $componentsTotal = round(array_sum(array_column($components, 'amount')), 2);

        $baseProfitRate = $base > 0 && $limbProfile !== null ? round((float) ($limbProfile['profit_rate'] ?? 0), 2) : 0.0;
        $baseTotalCost = round($base + $componentsTotal, 2);
        $baseSelling = round($baseTotalCost * (1 + ($baseProfitRate / 100)), 2);
        $baseProfitAmount = round($baseSelling - $baseTotalCost, 2);

        $quickProfitRate = $quick > 0 && $quickProfile !== null ? round((float) ($quickProfile['profit_rate'] ?? 0), 2) : 0.0;
        $quickSelling = round($quick * (1 + ($quickProfitRate / 100)), 2);
        $quickProfitAmount = round($quickSelling - $quick, 2);

        $sellingPrice = round($baseSelling + $quickSelling, 2);
        $totalCost = round($baseTotalCost + $quick, 2);
        $profitAmount = round($sellingPrice - $totalCost, 2);
        $effectiveRate = $totalCost > 0 ? round(($profitAmount / $totalCost) * 100, 2) : 0.0;

        return [
            'mode_key' => 'auto',
            'materials_total' => round($base + $quick, 2),
            'base_materials' => $base,
            'quick_materials' => $quick,
            'components' => $components,
            'components_total' => $componentsTotal,
            'base_total_cost' => $baseTotalCost,
            'base_profit_rate' => $baseProfitRate,
            'base_profit_amount' => $baseProfitAmount,
            'base_selling' => $baseSelling,
            'quick_profit_rate' => $quickProfitRate,
            'quick_profit_amount' => $quickProfitAmount,
            'quick_selling' => $quickSelling,
            'total_cost' => $totalCost,
            'profit_rate' => $effectiveRate,
            'profit_amount' => $profitAmount,
            'selling_price' => $sellingPrice,
        ];
    }

    /**
     * مبالغ المكوّنات بالقرش — الإجمالي = round(المواد × مجموع النسب) ويُوزَّع فرق التقريب
     * على البنود ذات أكبر كسر. تقريب كل بند منفرداً كان يزيد الإجمالي قرشاً أو أكثر
     * (مثال: 2.72 × 100% = 2.73) فيرتفع سعر البيع دون سبب.
     *
     * @param  list<array{label?:string, rate?:float}>  $definitions
     * @return list<array{label:string, rate:float, amount:float}>
     */
    private function allocateComponents(float $materials, array $definitions): array
    {
        $rows = [];
        $rateSum = 0.0;
        foreach ($definitions as $component) {
            $rate = round((float) ($component['rate'] ?? 0), 2);
            $rateSum += $rate;
            $exactCents = $materials * $rate;   // = materials × rate/100 × 100
            $rows[] = [
                'label' => (string) ($component['label'] ?? ''),
                'rate' => $rate,
                'cents' => (int) floor($exactCents + 1e-9),
                'fraction' => $exactCents - floor($exactCents + 1e-9),
            ];
        }

        $targetCents = (int) round($materials * $rateSum);
        $leftover = $targetCents - array_sum(array_column($rows, 'cents'));

        $order = array_keys($rows);
        usort($order, fn ($a, $b) => $rows[$b]['fraction'] <=> $rows[$a]['fraction'] ?: $a <=> $b);
        foreach ($order as $i) {
            if ($leftover <= 0) {
                break;
            }
            $rows[$i]['cents']++;
            $leftover--;
        }

        return array_map(fn (array $r) => [
            'label' => $r['label'],
            'rate' => $r['rate'],
            'amount' => round($r['cents'] / 100, 2),
        ], $rows);
    }
}
