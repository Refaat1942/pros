<?php

namespace Tests\Unit;

use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Unit — WAC recalculation formula (درامية الفصل الثالث: المتوسط المرجح).
 *
 * Rule: WAC is ONLY for inventory valuation.
 *       highestUnitPrice() is used for quote pricing — NEVER WAC.
 */
class WacFormulaTest extends TestCase
{
    use ProstheticTestHelper;

    private StockPriceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockPriceService::class);
    }

    /** (old_qty × old_wac + in_qty × in_price) / (old_qty + in_qty) */
    public function test_wac_formula_standard_case(): void
    {
        $item = $this->stockItem('RM-001', qty: 10, wac: 100.00);

        $this->service->recalcWac($item, inQty: 10, inPrice: 200.00);

        $item->refresh();
        // (10×100 + 10×200) / 20 = 3000/20 = 150
        $this->assertEquals(150.0, (float) $item->wac);
    }

    /** First receipt on empty stock: WAC = inPrice */
    public function test_wac_when_old_qty_is_zero(): void
    {
        $item = $this->stockItem('RM-002', qty: 0, wac: 0.00);

        $this->service->recalcWac($item, inQty: 5, inPrice: 300.00);

        $item->refresh();
        $this->assertEquals(300.0, (float) $item->wac);
    }

    /** highestUnitPrice must return max(amount) from price batches with qty > 0 */
    public function test_highest_unit_price_is_max_batch_price(): void
    {
        $supplier = $this->makeSupplier();
        $item = $this->stockItem('RM-003', qty: 15, wac: 80.00);

        $this->service->addBatch($item, 5, 100.00, $supplier, 'INV-001', now());
        $this->service->addBatch($item, 5, 250.00, $supplier, 'INV-002', now());
        $this->service->addBatch($item, 5, 180.00, $supplier, 'INV-003', now());

        $highest = $this->service->highestUnitPrice('RM-003');

        $this->assertEquals(250.00, $highest, 'Highest price must be 250, never WAC');
    }

    /** WAC must NOT be used for quote pricing — they are two separate concepts */
    public function test_wac_and_highest_price_are_distinct(): void
    {
        $supplier = $this->makeSupplier();
        $item = $this->stockItem('RM-004', qty: 10, wac: 50.00);

        $this->service->addBatch($item, 10, 90.00, $supplier, 'INV-004', now());

        $item->refresh();
        $wac = (float) $item->wac;
        $highest = $this->service->highestUnitPrice('RM-004');

        $this->assertNotEquals($wac, $highest, 'WAC and highest price must be independent values');
    }

    public function test_wac_stored_with_4_decimal_precision(): void
    {
        $item = $this->stockItem('RM-005', qty: 3, wac: 100.00);
        $this->service->recalcWac($item, inQty: 7, inPrice: 157.00);

        $item->refresh();
        // (3×100 + 7×157) / 10 = (300 + 1099) / 10 = 139.9
        $this->assertEquals(139.9, (float) $item->wac);
    }

    /**
     * C-2: النظام يسمح برصيد سالب (backorder). استلام مخزون بعد رصيد سالب يجب
     * ألا يُنتج WAC سالباً — الكمية السابقة السالبة تُعامَل كصفر عند الترجيح.
     */
    public function test_wac_never_negative_after_receiving_into_negative_stock(): void
    {
        // رصيد سالب (backorder) مع WAC سابق مرتفع.
        $item = $this->stockItem('RM-NEG', qty: -5, wac: 300.00);

        $this->service->recalcWac($item, inQty: 10, inPrice: 100.00);

        $item->refresh();
        // بأرضية 0 للكمية السابقة: (0×… + 10×100) / 10 = 100 — موجب وصحيح.
        $this->assertEquals(100.0, (float) $item->wac);
        $this->assertGreaterThanOrEqual(0.0, (float) $item->wac);
    }

    /** C-2: كمية سابقة سالبة والمقام السالب لا يعطّلان التحديث ولا ينتجان قيمة سالبة. */
    public function test_wac_recovers_when_prior_qty_more_negative_than_incoming(): void
    {
        $item = $this->stockItem('RM-NEG2', qty: -20, wac: 300.00);

        // كمية داخلة أقل من العجز — سابقاً كان المقام ≤ 0 فيُترك WAC قديماً؛ الآن يُحسب على الوارد.
        $this->service->recalcWac($item, inQty: 5, inPrice: 120.00);

        $item->refresh();
        // (0×… + 5×120) / 5 = 120 — موجب.
        $this->assertEquals(120.0, (float) $item->wac);
        $this->assertGreaterThanOrEqual(0.0, (float) $item->wac);
    }

    /** C-2: كمية داخلة غير موجبة لا تُحدّث WAC (استلام غير صالح للترجيح). */
    public function test_wac_unchanged_on_nonpositive_incoming_qty(): void
    {
        $item = $this->stockItem('RM-ZERO', qty: 10, wac: 150.00);

        $this->service->recalcWac($item, inQty: 0, inPrice: 999.00);

        $item->refresh();
        $this->assertEquals(150.0, (float) $item->wac);
    }
}
