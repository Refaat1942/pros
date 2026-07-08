<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ContractCompanyDebt;
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
 *  - المديونية المدنية: لا يوجد تاريخ لتسجيل «المستحق» في contract_company_debts،
 *    لذا نعتبر إجمالي المستحق الحالي رصيداً افتتاحياً، والحركة = ما تم تحصيله في الفترة.
 *  - قيمة المخزون: نعيد بناء الكمية عند لحظة القطع من stock_movements.balance_after،
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

        $collectedBefore = (float) DebtCollectionEntry::query()
            ->where('payable_type', $alias)
            ->where('collected_at', '<', $from)
            ->sum('amount');

        $collectedWithin = (float) DebtCollectionEntry::query()
            ->where('payable_type', $alias)
            ->whereBetween('collected_at', [$from, $to])
            ->sum('amount');

        $opening = $this->round($totalDue - $collectedBefore + $override);
        $movement = $this->round(-$collectedWithin);

        return [
            'opening' => $opening,
            'movement' => $movement,
            'closing' => $this->round($opening + $movement),
            'due' => $this->round($totalDue),
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
        /** @var array<int, float> $wac */
        $wac = StockItem::query()->pluck('wac', 'id')
            ->map(fn ($value) => (float) $value)
            ->all();

        $openingQty = [];
        $closingQty = [];

        StockMovement::query()
            ->where('moved_at', '<=', $to)
            ->orderBy('stock_item_id')
            ->orderBy('moved_at')
            ->orderBy('id')
            ->get(['stock_item_id', 'balance_after', 'moved_at'])
            ->each(function (StockMovement $movement) use ($from, &$openingQty, &$closingQty) {
                $id = (int) $movement->stock_item_id;
                $balance = (int) $movement->balance_after;

                if ($movement->moved_at < $from) {
                    $openingQty[$id] = $balance;
                }

                $closingQty[$id] = $balance;
            });

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
     * @param  array<int, int>  $qtyMap
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
