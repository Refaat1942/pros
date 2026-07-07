<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\BarcodeDispenseMismatchException;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\ReturnNoteService;
use App\Services\StockPriceService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Feature — BOM full lifecycle: raw → WIP → finished → return note
 * (الفصل الخامس: المخزن والصرف الصارم + الفصل السادس: الورشة)
 *
 * IMPORTANT: releaseToWip() now expects ONE barcode scan PER UNIT — the number of
 * scans for a code must equal that code's total quantity (strict code+item+quantity match).
 */
class BomLifecycleTest extends TestCase
{
    use ProstheticTestHelper;

    private function prepareCase(): array
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user = $this->userWithRole('technical');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0001']);

        return compact('item', 'case', 'user');
    }

    // ── Stage: Raw BOM ────────────────────────────────────────────────────────

    public function test_create_bom_has_raw_stage(): void
    {
        ['case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 3],
        ]);

        $this->assertEquals(Bom::STAGE_RAW, $bom->stage);
        $this->assertDatabaseHas('bom_items', [
            'bom_id' => $bom->id,
            'stock_item_code' => 'RM-001',
            'qty' => 3,
        ]);
    }

    public function test_create_bom_reserves_stock(): void
    {
        ['item' => $item, 'case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 4],
        ]);

        $item->refresh();
        $this->assertEquals(4, $item->reserved);
    }

    // ── Barcode dispense → WIP ────────────────────────────────────────────────
    // One barcode scan PER UNIT (scans-per-code must equal that code's quantity)

    public function test_dispense_with_correct_barcode_moves_to_wip(): void
    {
        ['item' => $item, 'case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        // qty=2 → لازم مسحتين لنفس الباركود
        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 2]]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001', 'BC-RM-001']); // 2 scans = qty 2

        $bom->refresh();
        $this->assertEquals(Bom::STAGE_WIP, $bom->stage);

        $item->refresh();
        $this->assertEquals(18, $item->qty, 'Stock must be reduced by qty=2 from the BomItem row');
        $this->assertEquals(0, $item->reserved);
    }

    /** المشهد الدرامي: الإنذار الحاد */
    public function test_wrong_barcode_throws_and_no_stock_changes(): void
    {
        $this->stockItem('RM-999', qty: 5);  // different item
        ['item' => $item, 'case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 1]]);
        $qtyBefore = $item->fresh()->qty;

        $this->expectException(BarcodeDispenseMismatchException::class);

        app(BomService::class)->releaseToWip($bom, ['BC-RM-999']); // wrong barcode for RM-001!
    }

    public function test_mismatched_barcode_count_throws(): void
    {
        ['case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        // qty=2 لكن مسحة واحدة فقط → عدد لا يطابق إجمالي الكميات
        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 2]]);

        $this->expectException(BarcodeDispenseMismatchException::class);

        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']); // 1 scan != qty 2
    }

    public function test_dispense_rejects_too_many_scans_for_a_code(): void
    {
        ['case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        // qty=1 لكن مسحتين → زيادة عن الكمية المطلوبة
        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 1]]);

        $this->expectException(BarcodeDispenseMismatchException::class);

        app(BomService::class)->releaseToWip($bom, ['BC-RM-001', 'BC-RM-001']); // 2 scans != qty 1
    }

    public function test_dispense_into_negative_stock_then_receive_recovers(): void
    {
        // رصيد 0، يُصرف 2 → -2، ثم توريد 5 → الرصيد الفعلي 3 (backorder).
        $item = $this->stockItem('RM-777', qty: 0);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 10, 100.00, $supplier, 'INV-A', now());
        $item->fresh()->update(['qty' => 0, 'reserved' => 0]); // نُرجع الرصيد لصفر مع بقاء السعر

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user = $this->userWithRole('technical');
        $this->actingAs($user);

        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-NEG-1']);

        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-777', 'qty' => 2]]);
        app(BomService::class)->releaseToWip($bom->fresh(), ['BC-RM-777', 'BC-RM-777']);

        $this->assertSame(-2, $item->fresh()->qty, 'الصرف من رصيد صفر يُنتج -2');

        // توريد 5 عبر مسار الاستلام الفعلي يرفع الرصيد من -2 إلى 3.
        $this->actingAs($user)
            ->postJson('/technical/inventory/receive', [
                'stock_item_id' => $item->id,
                'supplier_id' => $supplier->id,
                'qty' => 5,
                'unit_price' => 100,
                'invoice_no' => 'INV-NEG-B',
                'moved_at' => now()->toDateString(),
            ])
            ->assertCreated();

        $this->assertSame(3, $item->fresh()->qty, 'توريد 5 يرفع الرصيد من -2 إلى 3');
    }

    // ── Manufacturing sub-stages ──────────────────────────────────────────────

    public function test_advance_manufacturing_stage_sequence(): void
    {
        ['case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        // Start at MFG_WAREHOUSE (set by BOM create); advance through sequence
        $bomService = app(BomService::class);

        // First dispense to get to MFG_ISSUE
        $bom = $bomService->create($case, [['stock_item_code' => 'RM-001', 'qty' => 1]]);
        $bomService->releaseToWip($bom, ['BC-RM-001']); // advances to MFG_ISSUE automatically

        $case->refresh();
        $this->assertEquals(CaseRecord::MFG_ISSUE, $case->manufacturing_stage);

        // Then advance through remaining sub-stages
        foreach ([CaseRecord::MFG_GENERATION, CaseRecord::MFG_ASSEMBLY, CaseRecord::MFG_CASTING, CaseRecord::MFG_FINISHING] as $nextStage) {
            $bomService->advanceManufacturingStage($case, $nextStage);
            $this->assertEquals($nextStage, $case->fresh()->manufacturing_stage);
        }
    }

    // ── BOM close → finished ──────────────────────────────────────────────────

    public function test_close_finished_sets_bom_stage_finished(): void
    {
        ['item' => $item, 'case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 1]]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);
        $this->advanceCaseToFinishing($case);
        app(BomService::class)->finish($bom->fresh());

        $this->assertEquals(Bom::STAGE_FINISHED, $bom->fresh()->stage);
    }

    public function test_close_finished_without_wip_throws_http_exception(): void
    {
        ['case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 1]]);

        $this->expectException(HttpException::class);

        app(BomService::class)->finish($bom);
    }

    public function test_dispense_succeeds_when_bom_was_not_pre_reserved(): void
    {
        ['item' => $item, 'case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        // يحاكي BOM من التوصيف/seed: unit_cost موجود لكن reserved = 0
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);
        $bom->items()->update(['unit_cost' => 200.00]);

        $item->update(['reserved' => 0]);

        app(BomService::class)->releaseToWip($bom->fresh(), ['BC-RM-001', 'BC-RM-001']);

        $bom->refresh();
        $item->refresh();

        $this->assertEquals(Bom::STAGE_WIP, $bom->stage);
        $this->assertEquals(18, $item->qty);
        $this->assertEquals(0, $item->reserved);
    }

    // ── Return note ───────────────────────────────────────────────────────────

    public function test_return_note_restores_stock_qty(): void
    {
        ['item' => $item, 'case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        // بند واحد بكمية 2 — يُسمح بارتجاع 1 فقط (يبقى 1 في الورشة)
        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001', 'BC-RM-001']);

        $qtyAfterDispense = $item->fresh()->qty;  // 18

        $returnNote = app(ReturnNoteService::class)->create($bom, [
            ['stock_item_code' => 'RM-001', 'qty' => 1, 'name' => 'صنف RM-001'],
        ], 'قطعة فائضة', $user);

        // complete() expects scanned lines: [{line_id, barcode, qty_returned}]
        $lineId = $returnNote->lines()->first()->id;
        app(ReturnNoteService::class)->complete($returnNote, [
            ['line_id' => $lineId, 'barcode' => 'BC-RM-001', 'qty_returned' => 1],
        ]);

        $item->refresh();
        $this->assertEquals($qtyAfterDispense + 1, $item->qty,
            'Returned stock must be added back to inventory');
    }

    public function test_can_return_single_dispensed_unit(): void
    {
        ['case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $note = app(ReturnNoteService::class)->create($bom->fresh(), [
            ['stock_item_code' => 'RM-001', 'qty' => 1, 'name' => 'صنف RM-001'],
        ], 'ارتجاع وحدة واحدة', $user);

        $this->assertSame(1, $note->lines->first()->qty_requested);
    }

    public function test_cannot_request_second_return_when_pending_covers_single_unit(): void
    {
        ['case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        app(ReturnNoteService::class)->create($bom->fresh(), [
            ['stock_item_code' => 'RM-001', 'qty' => 1, 'name' => 'صنف RM-001'],
        ], 'طلب أول', $user);

        try {
            app(ReturnNoteService::class)->create($bom->fresh(), [
                ['stock_item_code' => 'RM-001', 'qty' => 1, 'name' => 'صنف RM-001'],
            ], 'طلب ثانٍ', $user);
            $this->fail('Expected duplicate return request to be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_can_return_partial_qty_leaving_one_in_workshop(): void
    {
        ['case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 4],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001', 'BC-RM-001', 'BC-RM-001', 'BC-RM-001']);

        $note = app(ReturnNoteService::class)->create($bom->fresh(), [
            ['stock_item_code' => 'RM-001', 'qty' => 3, 'name' => 'صنف RM-001'],
        ], 'فائض جزئي', $user);

        $this->assertSame(3, $note->lines->first()->qty_requested);

        try {
            app(ReturnNoteService::class)->create($bom->fresh(), [
                ['stock_item_code' => 'RM-001', 'qty' => 4, 'name' => 'صنف RM-001'],
            ], 'محاولة ارتجاع كامل', $user);
            $this->fail('Expected full-quantity return to be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
