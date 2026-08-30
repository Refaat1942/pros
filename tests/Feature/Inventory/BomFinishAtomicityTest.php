<?php

namespace Tests\Feature\Inventory;

use App\Enums\WorkflowEvent;
use App\Models\AuditLog;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\StockMovement;
use App\Services\BomService;
use App\Services\WorkflowService;
use RuntimeException;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Regression — BomService::finish() must commit case CLOSED + BOM finished atomically.
 */
class BomFinishAtomicityTest extends TestCase
{
    use ProstheticTestHelper;

    private function wipBomReadyToFinish(): array
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(\App\Services\StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-ATOMIC', now());

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $user = $this->userWithRole('technical');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-ATOMIC-001']);

        $this->actingAs($user);

        $bom = app(BomService::class)->create($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);
        $this->releaseBomToWip($bom, ['BC-RM-001', 'BC-RM-001']);
        $this->advanceCaseToFinishing($case);

        return compact('item', 'case', 'user', 'bom');
    }

    public function test_finish_success_closes_bom_and_case(): void
    {
        ['case' => $case, 'bom' => $bom] = $this->wipBomReadyToFinish();

        $caseBefore = $case->fresh();
        $this->assertSame(CaseRecord::MFG_FINISHING, $caseBefore->manufacturing_stage);
        $this->assertSame(CaseRecord::STAGE_MANUFACTURING, $caseBefore->stage_key);

        $finished = app(BomService::class)->finish($bom->fresh());

        $this->assertSame(Bom::STAGE_FINISHED, $finished->stage);
        $this->assertNotNull($finished->finished_at);

        $case->refresh();
        $this->assertSame(CaseRecord::STAGE_READY_DELIVERY, $case->stage_key);
        $this->assertNull($case->manufacturing_stage);
        $this->assertSame(100, (int) $case->workshop_progress_pct);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'finish',
            'tag' => 'warehouse',
        ]);
    }

    public function test_finish_failure_rolls_back_case_closed_state(): void
    {
        ['item' => $item, 'case' => $case, 'bom' => $bom] = $this->wipBomReadyToFinish();

        $caseBefore = $case->fresh();
        $bomBefore = $bom->fresh();
        $itemBefore = $item->fresh();
        $movementCountBefore = StockMovement::count();
        $auditCountBefore = AuditLog::query()->where('action', 'finish')->count();

        $this->mock(WorkflowService::class, function ($mock) {
            $mock->shouldReceive('advance')
                ->once()
                ->withArgs(function (CaseRecord $lockedCase, string $event) {
                    return $event === WorkflowEvent::BomFinished->value;
                })
                ->andThrow(new RuntimeException('injected workflow failure'));
        });

        try {
            app(BomService::class)->finish($bom->fresh());
            $this->fail('Expected finish() to throw after injected workflow failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('injected workflow failure', $e->getMessage());
        }

        $case->refresh();
        $bom->refresh();
        $item->refresh();

        $this->assertSame($caseBefore->manufacturing_stage, $case->manufacturing_stage);
        $this->assertSame($caseBefore->stage_key, $case->stage_key);
        $this->assertSame((int) $caseBefore->workshop_progress_pct, (int) $case->workshop_progress_pct);

        $this->assertSame($bomBefore->stage, $bom->stage);
        $this->assertNull($bom->finished_at);

        $this->assertSame($itemBefore->qty, $item->qty);
        $this->assertSame($itemBefore->reserved, $item->reserved);
        $this->assertSame($movementCountBefore, StockMovement::count());
        $this->assertSame($auditCountBefore, AuditLog::query()->where('action', 'finish')->count());
    }
}
