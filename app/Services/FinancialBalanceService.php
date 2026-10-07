<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ContractCompanyDebt;
use App\Models\ContractDebtAccrual;
use App\Models\DebtCollectionEntry;
use App\Models\MilitaryDebt;
use App\Models\Payment;
use App\Models\StockItem;
use App\Models\StockMovement;
use Carbon\Carbon;

/**
 * حساب أرصدة أول/آخر المدة لكل مجال مالي على مدى زمني محدد.
 *
 * ملاحظات تقريبية (موثّقة):
 *  - المديونية المدنية: المستحق بتاريخه من contract_debt_accruals (ترحيل حالة / إشعار دائن)؛
 *    المستحق القديم بلا قيود (قبل إضافة الجدول) يُعتبر رصيداً افتتاحياً.
 *  - قيمة المخزون: الكمية عند لحظة القطع = الرصيد الحالي − صافي الحركات بعدها،
 *    ونضربها في متوسط التكلفة الحالي (WAC) — تقريب لعدم تخزين WAC تاريخياً.
 */
class FinancialBalanceService
{
    public const DOMAIN_CASH = 'cash';

    public const DOMAIN_CIVILIAN = 'civilian';

    public const DOMAIN_MILITARY = 'military';

    public const DOMAIN_INVENTORY = 'inventory';

    /**
     * @param  array<string, float>  $openingOverrides  خريطة domain => مبلغ افتتاحي يدوي
     * @return array{
     *     from: Carbon, to: Carbon,
     *     cash: array{opening: float, movement: float, closing: float, collected: float},
     *     civilian: array{opening: float, movement: float, closing: float, due: float, collected: float},
     *     military: array{opening: float, movement: float, closing: float, due: float, collected: float},
     *     inventory: array{opening: float, movement: float, closing: float}
     * }
     */
    public function balances(Carbon $from, Carbon $to, array $openingOverrides = []): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        return [
            'from' => $from,
            'to' => $to,
            'cash' => $this->cash($from, $to, (float) ($openingOverrides[self::DOMAIN_CASH] ?? 0)),
            'civilian' => $this->civilianReceivable($from, $to, (float) ($openingOverrides[self::DOMAIN_CIVILIAN] ?? 0)),
            'military' => $this->militaryReceivable($from, $to, (float) ($openingOverrides[self::DOMAIN_MILITARY] ?? 0)),
            'inventory' => $this->inventoryValue($from, $to, (float) ($openingOverrides[self::DOMAIN_INVENTORY] ?? 0)),
        ];
    }

    /** @return array{opening: float, movement: float, closing: float, collected: float} */
    private function cash(Carbon $from, Carbon $to, float $override): array
    {
        $before = $this->round(
            (float) Payment::query()->where('received_at', '<', $from)->sum('amount')
            + (float) DebtCollectionEntry::query()->where('collected_at', '<', $from)->sum('amount')
        );

        $within = $this->round(
            (float) Payment::query()->whereBetween('received_at', [$from, $to])->sum('amount')
            + (float) DebtCollectionEntry::query()->whereBetween('collected_at', [$from, $to])->sum('amount')
        );

        $opening = $this->round($before + $override);

        return [
            'opening' => $opening,
            'movement' => $within,
            'closing' => $this->round($opening + $within),
            'collected' => $within,
        ];
    }

    /** @return array{opening: float, movement: float, closing: float, due: float, collected: float} */
    private function civilianReceivable(Carbon $from, Carbon $to, float $override): array
    {
        $alias = (new ContractCompanyDebt)->getMorphClass();
        $totalDue = (float) ContractCompanyDebt::query()->sum('due');
        $accrued = (float) ContractDebtAccrual::query()->sum('amount');
        $legacyDue = max(0.0, $totalDue - $accrued);
        $dueBefore = $legacyDue + (float) ContractDebtAccrual::query()->where('accrued_at', '<', $from)->sum('amount');
        $dueWithin = (float) ContractDebtAccrual::query()->whereBetween('accrued_at', [$from, $to])->sum('amount');

        $collectedBefore = (float) DebtCollectionEntry::query()
            ->where('payable_type', $alias)
            ->where('collected_at', '<', $from)
            ->sum('amount');

        $collectedWithin = (float) DebtCollectionEntry::query()
            ->where('payable_type', $alias)
            ->whereBetween('collected_at', [$from, $to])
            ->sum('amount');

        $opening = $this->round($dueBefore - $collectedBefore + $override);
        $movement = $this->round($dueWithin - $collectedWithin);

        return [
            'opening' => $opening,
            'movement' => $movement,
            'closing' => $this->round($opening + $movement),
            'due' => $this->round($dueWithin),
            'collected' => $this->round($collectedWithin),
        ];
    }

    /** @return array{opening: float, movement: float, closing: float, due: float, collected: float} */
    private function militaryReceivable(Carbon $from, Carbon $to, float $override): array
    {
        $alias = (new MilitaryDebt)->getMorphClass();
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $dueBefore = (float) MilitaryDebt::query()
            ->whereNotNull('delivered_at')
            ->whereDate('delivered_at', '<', $fromDate)
            ->sum('total_cost');

        $dueWithin = (float) MilitaryDebt::query()
            ->whereNotNull('delivered_at')
            ->whereDate('delivered_at', '>=', $fromDate)
            ->whereDate('delivered_at', '<=', $toDate)
            ->sum('total_cost');

        $collectedBefore = (float) DebtCollectionEntry::query()
            ->where('payable_type', $alias)
            ->where('collected_at', '<', $from)
            ->sum('amount');

        $collectedWithin = (float) DebtCollectionEntry::query()
            ->where('payable_type', $alias)
            ->whereBetween('collected_at', [$from, $to])
            ->sum('amount');

        $opening = $this->round($dueBefore - $collectedBefore + $override);
        $movement = $this->round($dueWithin - $collectedWithin);

        return [
            'opening' => $opening,
            'movement' => $movement,
            'closing' => $this->round($opening + $movement),
            'due' => $this->round($dueWithin),
            'collected' => $this->round($collectedWithin),
        ];
    }

    /** @return array{opening: float, movement: float, closing: float} */
    private function inventoryValue(Carbon $from, Carbon $to, float $override): array
    {
        /** @var array<int, float> $wac تكلفة الوحدة الحالية (FIFO) — نفس تقييم لوحة القيادة */
        $wac = collect(app(InventoryValuationService::class)->rows())
            ->mapWithKeys(fn (array $row) => [
                $row['id'] => $row['qty'] > 0 ? (float) $row['unit_cost'] : (float) $row['wac_unit'],
            ])
            ->all();

        // الرصيد عند لحظة القطع = الرصيد الحالي − صافي الحركات بعدها (والصنف المضاف بعدها = صفر).
        // كان يُعاد بناؤه من الحركات فقط، فالأصناف المرفوعة من الشيت بلا حركات تُحسب صفراً.
        $netAfter = fn (Carbon $cut) => StockMovement::query()
            ->where('moved_at', '>', $cut)
            ->selectRaw('stock_item_id, SUM(quantity) as net')
            ->groupBy('stock_item_id')
            ->pluck('net', 'stock_item_id')
            ->all();
        $afterFrom = $netAfter($from);
        $afterTo = $netAfter($to);
        $movedBy = fn (Carbon $cut) => array_flip(StockMovement::query()
            ->where('moved_at', '<=', $cut)
            ->distinct()
            ->pluck('stock_item_id')
            ->all());
        $movedByFrom = $movedBy($from);
        $movedByTo = $movedBy($to);

        $openingQty = [];
        $closingQty = [];

        StockItem::query()->get(['id', 'qty', 'created_at'])->each(
            function (StockItem $item) use ($from, $to, $afterFrom, $afterTo, $movedByFrom, $movedByTo, &$openingQty, &$closingQty) {
                $qty = (float) $item->qty;
                $createdAt = $item->created_at;
                // موجود عند القطع: أُنشئ قبله أو له حركة قبله.
                $existedAtFrom = ! $createdAt || $createdAt->lt($from) || isset($movedByFrom[$item->id]);
                $existedAtTo = ! $createdAt || $createdAt->lte($to) || isset($movedByTo[$item->id]);

                $openingQty[$item->id] = $existedAtFrom ? max(0.0, $qty - (float) ($afterFrom[$item->id] ?? 0)) : 0.0;
                $closingQty[$item->id] = $existedAtTo ? max(0.0, $qty - (float) ($afterTo[$item->id] ?? 0)) : 0.0;
            }
        );

        $openingValue = $this->valueOf($openingQty, $wac);
        $closingValue = $this->valueOf($closingQty, $wac);
        $movement = $this->round($closingValue - $openingValue);
        $opening = $this->round($openingValue + $override);

        return [
            'opening' => $opening,
            'movement' => $movement,
            'closing' => $this->round($opening + $movement),
        ];
    }

    /**
     * @param  array<int, float>  $qtyMap
     * @param  array<int, float>  $wac
     */
    private function valueOf(array $qtyMap, array $wac): float
    {
        $total = 0.0;

        foreach ($qtyMap as $id => $qty) {
            $total += $qty * ($wac[$id] ?? 0.0);
        }

        return $this->round($total);
    }

    /**
     * أرصدة فترة محاسبية مع تطبيق الأرصدة الافتتاحية اليدوية المسجّلة لها.
     *
     * @return array<string, mixed>
     */
    public function balancesForPeriod(AccountingPeriod $period): array
    {
        $period->loadMissing('openingOverrides');

        return $this->balances(
            Carbon::parse($period->start_date),
            Carbon::parse($period->end_date),
            $period->openingOverrideMap(),
        );
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}
