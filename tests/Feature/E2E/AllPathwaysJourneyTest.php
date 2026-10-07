<?php

namespace Tests\Feature\E2E;

use App\Models\CaseRecord;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\WorkshopSection;
use App\Services\StockPriceService;
use App\Support\Journeys\CaseJourneyRunner;
use App\Support\Journeys\JourneyStepFailed;
use Tests\Support\ProstheticTestCase;

/**
 * حالة كاملة لكل نوع مريض — من التسجيل حتى التسليم — بنفس مسارات الشاشات وبمستخدم كل قسم.
 */
class AllPathwaysJourneyTest extends ProstheticTestCase
{
    private StockItem $knee;

    private StockItem $tape;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([Role::SLUG_RECEPTION, Role::SLUG_DOCTOR, Role::SLUG_SPEC, Role::SLUG_ADJUSTMENTS, Role::SLUG_COSTING,
            Role::SLUG_OPERATIONS, Role::SLUG_CASHIER, Role::SLUG_WORKSHOP, Role::SLUG_TECHNICAL, 'admin'] as $role) {
            $this->userWithRole($role);
        }

        $section = WorkshopSection::create(['name' => 'قسم الأطراف', 'code' => 'limbs', 'sort' => 1, 'active' => true]);
        $section->technicians()->attach(CaseJourneyRunner::userFor(Role::SLUG_WORKSHOP)->id);

        $supplier = $this->makeSupplier();

        // صنف بالعدد + صنف بالمتر يُصرف بكسر.
        $this->knee = $this->stockItem('1101', qty: 0, wac: 0);
        $this->knee->update(['uom' => 'عدد', 'price' => 2500]);
        app(StockPriceService::class)->addBatch($this->knee->fresh(), 20, 2500, $supplier, 'INV-J-1', now());
        $this->knee->update(['qty' => 20]);

        $this->tape = $this->stockItem('1102', qty: 0, wac: 0);
        // كود فيه «=» و«/» مثل أكواد شيت الأصناف (617S3=H5) — كان الصرف يرفض صيغة الباركود.
        $this->tape->update(['uom' => 'متر', 'price' => 40, 'alt_codes' => '617S3=H5/2', 'barcode' => 'BC-617S3=H5/2']);
        app(StockPriceService::class)->addBatch($this->tape->fresh(), 30.5, 40, $supplier, 'INV-J-2', now());
        $this->tape->update(['qty' => 30.5]);
    }

    public function test_every_patient_type_goes_from_reception_to_delivery(): void
    {
        $runner = new CaseJourneyRunner([
            ['item' => $this->knee->fresh(), 'qty' => 1],
            ['item' => $this->tape->fresh(), 'qty' => 0.75],
        ]);

        $failures = [];
        foreach (CaseJourneyRunner::journeys() as $i => $journey) {
            try {
                $case = $runner->run($journey, 'مريض تجريبي '.($i + 1));
                $this->assertSame(CaseRecord::STAGE_DELIVERED, $case->stage_key);
            } catch (JourneyStepFailed $e) {
                $last = collect($runner->log())->where('journey', $journey)->last();
                $failures[] = CaseJourneyRunner::label($journey).' — '.($last['step'] ?? '?').': '.$e->getMessage();
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));

        $steps = collect($runner->log())->where('ok', true)->groupBy('journey')->map->pluck('step');
        $this->assertContains('إدارة الخدمات — التصديق', $steps[CaseJourneyRunner::JOURNEY_MILITARY_SERVICES]);
        $this->assertNotContains('إدارة الخدمات — التصديق', $steps[CaseJourneyRunner::JOURNEY_MILITARY]);
        $this->assertContains('الخزنة — تحصيل المبلغ كاش', $steps[CaseJourneyRunner::JOURNEY_CASH]);
        $this->assertContains('الاستقبال — طباعة العرض وتسجيل خطاب الموافقة', $steps[CaseJourneyRunner::JOURNEY_ENTITY_NON_CONTRACTED]);

        $count = count(CaseJourneyRunner::journeys());
        $this->assertEqualsWithDelta(20 - $count, (float) $this->knee->fresh()->qty, 0.0001);
        $this->assertEqualsWithDelta(30.5 - 0.75 * $count, (float) $this->tape->fresh()->qty, 0.0001);
    }
}
