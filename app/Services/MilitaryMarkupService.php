<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\CaseRecord;

/**
 * محرك الربحية العسكرية (خلفي) — يحسب سعر البيع وهامش الربح.
 *
 * يعمل في الخلفية للحالات العسكرية فقط:
 *   - سعر البيع = مجموع (الكمية × سعر الصنف) — أعلى سعر مسجَّل للصنف.
 *   - التكلفة = internal_cost (WAC) للحالة.
 *   - نسبة الربح = (البيع − التكلفة) / التكلفة × 100.
 *
 * النِّسَب والقيم تُخزَّن على الحالة وتظهر للسوبر أدمن فقط
 * (صلاحية view-military-profit) — مجرّدة تماماً من واجهة بقية الموظفين.
 */
class MilitaryMarkupService
{
    public function __construct(private readonly StockPriceService $stockPriceService)
    {
    }

    /**
     * يحسب ويُخزّن ربحية الحالة العسكرية. لا أثر على المدني.
     */
    public function apply(CaseRecord $case): void
    {
        if (! $case->isMilitary()) {
            return;
        }

        $bom = Bom::with('items')->where('case_id', $case->id)->first();

        if (! $bom) {
            return;
        }

        $sellingPrice = 0.0;

        foreach ($bom->items as $item) {
            $unitPrice = $this->stockPriceService->highestUnitPrice($item->stock_item_code);
            $sellingPrice += $item->qty * $unitPrice;
        }

        $sellingPrice = round($sellingPrice, 2);
        $cost         = (float) $case->internal_cost;
        $markupPct    = $cost > 0 ? round((($sellingPrice - $cost) / $cost) * 100, 2) : 0.0;

        CaseRecord::where('id', $case->id)->update([
            'military_selling_price' => $sellingPrice,
            'military_markup_pct'    => $markupPct,
        ]);
    }
}
