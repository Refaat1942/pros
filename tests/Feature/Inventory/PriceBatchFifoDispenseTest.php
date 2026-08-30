<?php

namespace Tests\Feature\Inventory;

use App\Models\StockItemPrice;
use App\Models\StockMovement;
use App\Models\SupplyRequestLine;
use App\Services\BomService;
use App\Services\PriceBatchDispenseService;
use App\Services\ReturnNoteService;
use App\Services\StockPriceService;
use App\Services\StockReceiveService;
use App\Services\SupplyRequestService;
use Carbon\Carbon;
use Tests\Support\ProstheticTestCase;

class PriceBatchFifoDispenseTest extends ProstheticTestCase
{
    public function test_dispense_exhausts_each_price_tier_in_receive_order(): void
    {
        $supplier = $this->makeSupplier();
        $item = $this->stockItem('RM-FIFO-1', qty: 30);
        $priceService = app(StockPriceService::class);

        $priceService->addBatch($item->fresh(), 5, 10.00, $supplier, 'INV-10', Carbon::parse('2026-01-01'));
        $priceService->addBatch($item->fresh(), 10, 20.00, $supplier, 'INV-20', Carbon::parse('2026-02-01'));
        $priceService->addBatch($item->fresh(), 15, 30.00, $supplier, 'INV-30', Carbon::parse('2026-03-01'));

        $service = app(PriceBatchDispenseService::class);
        $allocations = $service->allocateForDispense($item->fresh(), 12);

        $this->assertCount(2, $allocations);
        $this->assertSame(10.0, $allocations[0]['unit_price']);
        $this->assertEqualsWithDelta(5.0, $allocations[0]['qty'], 0.0001);
        $this->assertSame(20.0, $allocations[1]['unit_price']);
        $this->assertEqualsWithDelta(7.0, $allocations[1]['qty'], 0.0001);
    }

    public function test_dispense_prefers_earlier_received_price_tier_not_cheaper_later_tier(): void
    {
        $supplier = $this->makeSupplier();
        $item = $this->stockItem('RM-FIFO-2', qty: 15);
        $priceService = app(StockPriceService::class);

        $priceService->addBatch($item->fresh(), 10, 30.00, $supplier, 'INV-HIGH-FIRST', Carbon::parse('2026-01-01'));
        $priceService->addBatch($item->fresh(), 5, 10.00, $supplier, 'INV-LOW-LATER', Carbon::parse('2026-02-01'));

        $allocations = app(PriceBatchDispenseService::class)->allocateForDispense($item->fresh(), 8);

        $this->assertCount(1, $allocations);
        $this->assertSame(30.0, $allocations[0]['unit_price']);
        $this->assertEqualsWithDelta(8.0, $allocations[0]['qty'], 0.0001);
    }

    public function test_supply_request_batch_consumed_before_other_batches(): void
    {
        $supplier = $this->makeSupplier();
        $user = $this->userWithRole('technical');
        $item = $this->stockItem('RM-SR-1', qty: 0);

        app(StockPriceService::class)->addBatch($item->fresh(), 10, 15.00, $supplier, 'INV-LOW', now());

        $line = app(SupplyRequestService::class)->createLine([
            'line_type' => SupplyRequestLine::TYPE_CATALOG,
            'stock_item_id' => $item->id,
            'qty' => 4,
        ], $user);

        $movement = app(StockReceiveService::class)->receive(
            item: $item->fresh(),
            qty: 4,
            unitPrice: 50.00,
            supplier: $supplier,
            invoiceNo: 'SR-INV',
            movedAt: Carbon::now(),
            performedBy: $user,
            supplyRequestLineId: $line->id,
        );

        app(SupplyRequestService::class)->markLineReceived($line->fresh(), $movement);

        $supplyBatch = StockItemPrice::query()
            ->where('stock_item_id', $item->id)
            ->where('supply_request_line_id', $line->id)
            ->first();

        $this->assertNotNull($supplyBatch);
        $this->assertEqualsWithDelta(50.0, (float) $supplyBatch->amount, 0.01);

        $allocations = app(PriceBatchDispenseService::class)->allocateForDispense($item->fresh(), 3);

        $this->assertSame((int) $supplyBatch->id, $allocations[0]['batch_id']);
        $this->assertEqualsWithDelta(50.0, $allocations[0]['unit_price'], 0.01);
        $this->assertEqualsWithDelta(3.0, $allocations[0]['qty'], 0.0001);
    }

    public function test_bom_dispense_stamps_fifo_cost_not_wac(): void
    {
        ['item' => $item, 'case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        $supplier = \App\Models\Supplier::first();
        $priceService = app(StockPriceService::class);
        $priceService->addBatch($item->fresh(), 10, 100.00, $supplier, 'INV-LOW', now());
        $priceService->addBatch($item->fresh(), 10, 250.00, $supplier, 'INV-HIGH', now());
        $item->refresh();

        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 2]]);
        $this->releaseBomToWip($bom, ['BC-RM-001', 'BC-RM-001']);

        $movements = StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_ISSUE)
            ->where('reference_type', 'bom')
            ->where('reference_id', $bom->id)
            ->orderBy('id')
            ->get();

        $this->assertGreaterThanOrEqual(1, $movements->count());
        $this->assertEqualsWithDelta(100.0, (float) $movements->first()->unit_cost, 0.01);

        $bom->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $bom->items->first()->unit_cost, 0.01);

        $case->refresh();
        $this->assertSame(200.0, (float) $case->issue_cost);
    }

    public function test_return_restores_same_price_batch(): void
    {
        ['item' => $item, 'case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        $supplier = \App\Models\Supplier::first();
        app(StockPriceService::class)->addBatch($item->fresh(), 10, 100.00, $supplier, 'INV-RET-A', now());
        app(StockPriceService::class)->addBatch($item->fresh(), 10, 250.00, $supplier, 'INV-RET-B', now());

        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);
        $this->releaseBomToWip($bom, ['BC-RM-001', 'BC-RM-001']);

        $issueBatchId = StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_ISSUE)
            ->where('reference_type', 'bom')
            ->where('reference_id', $bom->id)
            ->orderBy('id')
            ->value('stock_item_price_id');

        $returnNote = app(ReturnNoteService::class)->create($bom->fresh(), [
            ['stock_item_code' => 'RM-001', 'qty' => 1, 'name' => 'صنف RM-001'],
        ], 'قطعة فائضة', $user);

        $lineId = $returnNote->lines()->first()->id;
        app(ReturnNoteService::class)->complete($returnNote, [
            ['line_id' => $lineId, 'barcode' => 'BC-RM-001', 'qty_returned' => 1],
        ]);

        $returnMovement = StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->latest('id')
            ->first();

        $this->assertNotNull($returnMovement);
        $this->assertSame((int) $issueBatchId, (int) $returnMovement->stock_item_price_id);
        $this->assertEqualsWithDelta(100.0, (float) $returnMovement->unit_cost, 0.01);

        $case->refresh();
        $this->assertSame(100.0, (float) $case->issue_cost);
    }
}
