<?php

namespace Tests\Feature\Finance;

use App\Models\CaseRecord;
use App\Models\StockMovement;
use App\Services\InventoryFinancialReconciliationService;
use App\Services\ItemPricingAnalyticsService;
use Carbon\Carbon;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class InventoryFinancialReconciliationServiceTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_reconciliation_links_issue_movements_to_delivered_margin(): void
    {
        $from = Carbon::parse('2026-07-01');
        $to = Carbon::parse('2026-07-31');

        $item = $this->stockItem('RM-REC-1', qty: 10);
        $supplier = $this->makeSupplier();
        app(\App\Services\StockPriceService::class)->addBatch($item, 10, 100.00, $supplier, 'INV-R', now());

        StockMovement::create([
            'stock_item_id' => $item->id,
            'movement_type' => StockMovement::TYPE_ISSUE,
            'quantity' => -2,
            'unit_cost' => 100,
            'balance_after' => 8,
            'moved_at' => '2026-07-05 10:00:00',
        ]);

        $company = $this->civilianCompany();
        $case = $this->caseAtStage($this->civilianPatient($company), CaseRecord::STAGE_DELIVERED);
        $case->update([
            'delivered_at' => '2026-07-10 12:00:00',
            'quote_total' => 500,
            'internal_cost' => 150,
            'issue_cost' => 200,
        ]);

        $summary = app(InventoryFinancialReconciliationService::class)
            ->periodSummary($from, $to);

        $this->assertSame(200.0, $summary['inventory']['issued_value']);
        $this->assertSame(500.0, $summary['revenue']['delivered_revenue']);
        $this->assertSame(200.0, $summary['revenue']['delivered_wac_cost']);
        $this->assertSame(300.0, $summary['revenue']['gross_margin']);
    }

    public function test_reconciliation_net_outflow_accounts_for_returns_to_stock(): void
    {
        $from = Carbon::parse('2026-07-01');
        $to = Carbon::parse('2026-07-31');

        $item = $this->stockItem('RM-RET-REC', qty: 10);
        $supplier = $this->makeSupplier();
        app(\App\Services\StockPriceService::class)->addBatch($item, 10, 100.00, $supplier, 'INV-RET', now());

        StockMovement::create([
            'stock_item_id' => $item->id,
            'movement_type' => StockMovement::TYPE_ISSUE,
            'quantity' => -3,
            'unit_cost' => 100,
            'balance_after' => 7,
            'moved_at' => '2026-07-05 10:00:00',
        ]);

        StockMovement::create([
            'stock_item_id' => $item->id,
            'movement_type' => StockMovement::TYPE_RETURN,
            'quantity' => 1,
            'unit_cost' => 100,
            'balance_after' => 8,
            'reference_type' => 'return_note',
            'reference_id' => 1,
            'moved_at' => '2026-07-06 11:00:00',
        ]);

        $summary = app(InventoryFinancialReconciliationService::class)
            ->periodSummary($from, $to);

        $this->assertSame(300.0, $summary['inventory']['issued_value']);
        $this->assertSame(100.0, $summary['inventory']['returned_value']);
        $this->assertSame(200.0, $summary['inventory']['net_outflow']);
    }

    public function test_item_pricing_analytics_exposes_wac_highest_and_margin(): void
    {
        $item = $this->stockItem('RM-MGN-1', qty: 5);
        $supplier = $this->makeSupplier();
        $price = app(\App\Services\StockPriceService::class);
        $price->addBatch($item, 5, 100.00, $supplier, 'INV-A', now());
        $price->addBatch($item->fresh(), 3, 150.00, $supplier, 'INV-B', now());

        $row = app(ItemPricingAnalyticsService::class)->rowForItem($item->fresh());

        $this->assertSame(150.0, $row['highest_purchase_price']);
        $this->assertSame(100.0, $row['lowest_purchase_price']);
        $this->assertGreaterThan(0, $row['unit_margin']);
        $this->assertGreaterThan(0, $row['margin_pct']);
        $this->assertSame(2, $row['price_batch_count']);
    }
}
