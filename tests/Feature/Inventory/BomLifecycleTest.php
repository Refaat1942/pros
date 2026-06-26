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
 * IMPORTANT: releaseToWip() expects ONE barcode per unique stock_item_code (rows merged by code).
 */
class BomLifecycleTest extends TestCase
{
    use ProstheticTestHelper;

    private function prepareCase(): array
    {
        $item     = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user    = $this->userWithRole('technical');
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
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
            'bom_id'          => $bom->id,
            'stock_item_code' => 'RM-001',
            'qty'             => 3,
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
    // One barcode per BomItem row (not per unit quantity)

    public function test_dispense_with_correct_barcode_moves_to_wip(): void
    {
        ['item' => $item, 'case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        // 1 BomItem row with qty=2 → pass 1 barcode
        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 2]]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']); // 1 barcode = 1 item row

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

        $bom       = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 1]]);
        $qtyBefore = $item->fresh()->qty;

        $this->expectException(BarcodeDispenseMismatchException::class);

        app(BomService::class)->releaseToWip($bom, ['BC-RM-999']); // wrong barcode for RM-001!
    }

    public function test_mismatched_barcode_count_throws(): void
    {
        ['case' => $case, 'user' => $user] = $this->prepareCase();
        $this->actingAs($user);

        // 1 BomItem row but 2 barcodes passed → mismatch
        $bom = app(BomService::class)->create($case, [['stock_item_code' => 'RM-001', 'qty' => 2]]);

        $this->expectException(BarcodeDispenseMismatchException::class);

        app(BomService::class)->releaseToWip($bom, ['BC-RM-001', 'BC-RM-001']); // 2 != 1 item
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

        app(BomService::class)->releaseToWip($bom->fresh(), ['BC-RM-001']);

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

        // صفّان بنفس الكود — يُصرف بباركود واحد لكل كود صنف فريد
        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->releaseToWip($bom, ['BC-RM-001']);

        $qtyAfterDispense = $item->fresh()->qty;  // 18

        // Reuse the same user from prepareCase — do NOT create a new one (email conflict)
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
}
