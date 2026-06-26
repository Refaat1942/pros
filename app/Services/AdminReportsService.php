<?php

namespace App\Services;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\StockItem;
use App\Models\StockItemPrice;
use App\Models\StockMovement;
use App\Support\BomItemAggregator;
use App\Support\CaseFinancialSummary;
use Illuminate\Support\Facades\DB;

/**
 * تقارير الأدمن — /admin/reports (قراءة فقط من قاعدة البيانات).
 */
class AdminReportsService
{
    public function __construct(
        private readonly BiReportService $biReportService,
        private readonly StockPriceService $stockPriceService,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        $inventoryBoard = $this->biReportService->boardInventory();
        $operationsBoard = $this->biReportService->boardOperations();

        return [
            'financial' => $this->financial(),
            'inventory' => array_merge($inventoryBoard, $this->inventoryExtras($inventoryBoard)),
            'operations' => $operationsBoard,
            'bom'         => $this->bomReport(),
        ];
    }

    /** @return array<string, mixed> */
    private function financial(): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd   = now()->endOfMonth();

        $deliveredThisMonth = CaseRecord::query()
            ->where('patient_type', Patient::TYPE_CIVILIAN)
            ->where('stage_key', CaseRecord::STAGE_DELIVERED)
            ->whereBetween('delivered_at', [$monthStart, $monthEnd])
            ->get();

        $monthlyRevenue = $deliveredThisMonth->sum(
            fn (CaseRecord $case) => CaseFinancialSummary::totalCost($case)
        );

        $topItems = BomItem::query()
            ->select('stock_item_code', DB::raw('MAX(name) as name'), DB::raw('SUM(qty) as total_qty'))
            ->groupBy('stock_item_code')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'code' => $row->stock_item_code,
                'name' => $row->name,
                'qty'  => (int) $row->total_qty,
            ])
            ->all();

        $workOrders = CaseRecord::query()
            ->with('patient:id,name')
            ->whereNotNull('work_order_no')
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('updated_at', [$monthStart, $monthEnd])
                    ->orWhereBetween('created_at', [$monthStart, $monthEnd]);
            })
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'case_no', 'work_order_no', 'patient_id', 'stage_key', 'updated_at']);

        return [
            'monthly_revenue'      => round((float) $monthlyRevenue, 2),
            'delivered_count'      => $deliveredThisMonth->count(),
            'month_label'          => now()->translatedFormat('F Y'),
            'top_items'            => $topItems,
            'work_orders_count'    => $workOrders->count(),
            'work_orders'          => $workOrders->map(fn (CaseRecord $c) => [
                'work_order_no' => $c->work_order_no,
                'patient'       => $c->patient?->name ?? '—',
                'case_no'       => $c->case_no,
                'stage_key'     => $c->stage_key,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $inventoryBoard
     * @return array<string, mixed>
     */
    private function inventoryExtras(array $inventoryBoard): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd   = now()->endOfMonth();

        $itemCount = (int) ($inventoryBoard['item_count'] ?? 0);
        $lowStock  = (int) ($inventoryBoard['low_stock'] ?? 0);

        $lowStockItems = StockItem::query()
            ->where('status', StockItem::STATUS_LOW)
            ->orderBy('qty')
            ->limit(8)
            ->get(['code', 'name', 'qty'])
            ->map(fn (StockItem $i) => [
                'code' => $i->code,
                'name' => $i->name,
                'qty'  => (int) $i->qty,
            ])
            ->all();

        return [
            'health_pct'       => $itemCount > 0
                ? (int) round((($itemCount - $lowStock) / $itemCount) * 100)
                : 0,
            'low_stock_items'  => $lowStockItems,
            'issues_this_month' => StockMovement::query()
                ->where('movement_type', StockMovement::TYPE_ISSUE)
                ->whereBetween('moved_at', [$monthStart, $monthEnd])
                ->sum('quantity'),
            'active_batches'   => StockItemPrice::query()->where('qty', '>', 0)->count(),
            'batch_samples'      => StockItemPrice::query()
                ->with('stockItem:id,code,name')
                ->where('qty', '>', 0)
                ->orderByDesc('received_at')
                ->limit(8)
                ->get()
                ->map(fn (StockItemPrice $p) => [
                    'code'   => $p->stockItem?->code ?? '—',
                    'name'   => $p->stockItem?->name ?? '—',
                    'amount' => (float) $p->amount,
                    'qty'    => (int) $p->qty,
                ])
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function bomReport(): array
    {
        $boms = Bom::query()
            ->with([
                'items:id,bom_id,stock_item_code,name,qty,unit_cost',
                'caseRecord:id,case_no,work_order_no,patient_id',
                'caseRecord.patient:id,name',
            ])
            ->orderByDesc('id')
            ->limit((int) config('dashboards.table_fetch_limit', 1000))
            ->get();

        $stageLabels = [
            Bom::STAGE_RAW      => 'خام',
            Bom::STAGE_WIP      => 'تحت التشغيل',
            Bom::STAGE_FINISHED => 'تام',
        ];

        $summary = [
            Bom::STAGE_RAW      => ['count' => 0, 'value' => 0.0, 'lines' => 0],
            Bom::STAGE_WIP      => ['count' => 0, 'value' => 0.0, 'lines' => 0],
            Bom::STAGE_FINISHED => ['count' => 0, 'value' => 0.0, 'lines' => 0],
        ];

        $rows = [];

        foreach ($boms as $bom) {
            $merged  = BomItemAggregator::byStockCode($bom->items);
            $value   = $this->bomHighestBatchValue($merged);
            $lineCnt = count($merged);
            $stage   = $bom->stage ?? Bom::STAGE_RAW;

            if (isset($summary[$stage])) {
                $summary[$stage]['count']++;
                $summary[$stage]['value'] += $value;
                $summary[$stage]['lines'] += $lineCnt;
            }

            $rows[] = [
                'patient'       => $bom->patient_name ?: ($bom->caseRecord?->patient?->name ?? '—'),
                'work_order_no'   => $bom->caseRecord?->work_order_no ?? '—',
                'stage'         => $stage,
                'stage_label'   => $stageLabels[$stage] ?? $stage,
                'line_count'    => $lineCnt,
                'value'         => round($value, 2),
            ];
        }

        foreach ($summary as $key => $stat) {
            $summary[$key]['value'] = round($stat['value'], 2);
        }

        return [
            'summary' => $summary,
            'rows'    => $rows,
        ];
    }

    /** @param  list<array<string, mixed>>  $mergedItems */
    private function bomHighestBatchValue(array $mergedItems): float
    {
        $total = 0.0;

        foreach ($mergedItems as $item) {
            $qty = (int) ($item['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $unit = (float) ($item['unit_cost'] ?? 0);
            if ($unit <= 0) {
                $unit = $this->stockPriceService->highestUnitPrice((string) $item['stock_item_code']);
            }

            $total += $qty * $unit;
        }

        return $total;
    }
}
