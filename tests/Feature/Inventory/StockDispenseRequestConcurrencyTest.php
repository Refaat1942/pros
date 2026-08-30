<?php

namespace Tests\Feature\Inventory;

use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\Role;
use App\Models\StockDispenseRequest;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\BomService;
use App\Services\StockDispenseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * P1-04: one pending dispense request per BOM; safe approve/reject under contention.
 */
class StockDispenseRequestConcurrencyTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    private function dispenseReadyBom(): array
    {
        config(['inventory.dispense_requires_approval' => true]);

        $this->stockItem('RM-001', qty: 20, wac: 100.00);
        $patient = $this->militaryPatient($this->militaryCompany());
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-P1-04-'.uniqid()]);

        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'name' => 'صنف RM-001', 'qty' => 1],
        ]);
        app(BomService::class)->reserveForCase($case->fresh());
        $case = $this->seedWorkshopAssignmentApproved($case->fresh());

        $technical = $this->userWithRole(Role::SLUG_TECHNICAL);
        $admin = $this->userWithRole(Role::SLUG_ADMIN);

        return [
            'bom' => $bom->fresh(),
            'case' => $case,
            'barcodes' => ['BC-RM-001'],
            'technical' => $technical,
            'admin' => $admin,
        ];
    }

    public function test_sequential_duplicate_submit_rejected(): void
    {
        $ctx = $this->dispenseReadyBom();
        $service = app(StockDispenseRequestService::class);

        $first = $service->submit($ctx['bom'], $ctx['barcodes'], $ctx['technical']);
        $this->assertSame(StockDispenseRequest::STATUS_PENDING, $first->status);

        try {
            $service->submit($ctx['bom']->fresh(), $ctx['barcodes'], $ctx['technical']);
            $this->fail('Second submit must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(
            1,
            StockDispenseRequest::query()
                ->where('bom_id', $ctx['bom']->id)
                ->where('status', StockDispenseRequest::STATUS_PENDING)
                ->count(),
        );
    }

    public function test_reject_then_resubmit_allowed(): void
    {
        $ctx = $this->dispenseReadyBom();
        $service = app(StockDispenseRequestService::class);

        $first = $service->submit($ctx['bom'], $ctx['barcodes'], $ctx['technical']);
        $service->reject($first->fresh(), $ctx['admin'], 'رفض اختبار');

        $this->assertSame(StockDispenseRequest::STATUS_REJECTED, $first->fresh()->status);

        $second = $service->submit($ctx['bom']->fresh(), $ctx['barcodes'], $ctx['technical']);
        $this->assertSame(StockDispenseRequest::STATUS_PENDING, $second->status);
        $this->assertNotSame($first->id, $second->id);

        $this->assertSame(
            1,
            StockDispenseRequest::query()
                ->where('bom_id', $ctx['bom']->id)
                ->where('status', StockDispenseRequest::STATUS_PENDING)
                ->count(),
        );
    }

    public function test_approve_twice_rejected_without_duplicate_stock_movement(): void
    {
        $ctx = $this->dispenseReadyBom();
        $service = app(StockDispenseRequestService::class);
        $bom = $ctx['bom'];

        $request = $service->submit($bom, $ctx['barcodes'], $ctx['technical']);
        $stockBefore = StockItem::findByOperationalCode($this->testOperationalCode('RM-001'), true);

        $service->approve($request->fresh(), $ctx['admin']);

        try {
            $service->approve($request->fresh(), $ctx['admin']);
            $this->fail('Second approve must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(StockDispenseRequest::STATUS_EXECUTED, $request->fresh()->status);
        $this->assertSame(Bom::STAGE_WIP, $bom->fresh()->stage);
        $this->assertSame(
            1,
            StockMovement::query()
                ->where('reference_type', 'bom')
                ->where('reference_id', $bom->id)
                ->where('movement_type', StockMovement::TYPE_ISSUE)
                ->count(),
        );
        $stockBefore->refresh();
        $this->assertSame(19, $stockBefore->qty);
    }

    public function test_reject_twice_rejected(): void
    {
        $ctx = $this->dispenseReadyBom();
        $service = app(StockDispenseRequestService::class);

        $request = $service->submit($ctx['bom'], $ctx['barcodes'], $ctx['technical']);
        $service->reject($request->fresh(), $ctx['admin'], 'رفض أول');

        try {
            $service->reject($request->fresh(), $ctx['admin'], 'رفض ثانٍ');
            $this->fail('Second reject must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(StockDispenseRequest::STATUS_REJECTED, $request->fresh()->status);
        $this->assertSame(Bom::STAGE_RAW, $ctx['bom']->fresh()->stage);
        $this->assertSame(
            0,
            StockMovement::query()
                ->where('reference_type', 'bom')
                ->where('reference_id', $ctx['bom']->id)
                ->count(),
        );
    }
}
