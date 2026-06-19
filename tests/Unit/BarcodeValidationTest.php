<?php

namespace Tests\Unit;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Services\BarcodeValidationService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * Unit — BarcodeValidationService (الفصل الخامس: الإنذار الإجباري).
 *
 * A wrong barcode must:
 *   1. Return false
 *   2. Never change any stock
 *   3. Write a 'blocked' audit entry
 */
class BarcodeValidationTest extends TestCase
{
    use ProstheticTestHelper;

    private BarcodeValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BarcodeValidationService::class);
    }

    private function bomItemForCode(string $code): BomItem
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);

        $bom = Bom::create([
            'bom_no'       => 'BOM-0001',
            'case_id'      => $case->id,
            'order_ref'    => 'ORD-001',
            'patient_name' => $patient->name,
            'stage'        => Bom::STAGE_RAW,
        ]);

        return BomItem::create([
            'bom_id'          => $bom->id,
            'stock_item_code' => $code,
            'name'            => "Bom Item {$code}",
            'qty'             => 2,
            'unit_cost'       => 100.00,
            'issued_qty'      => 0,
            'returned_qty'    => 0,
        ]);
    }

    public function test_correct_barcode_returns_true(): void
    {
        $this->stockItem('RM-001');          // barcode = BC-RM-001
        $bomItem = $this->bomItemForCode('RM-001');

        $result = $this->service->validateScan('BC-RM-001', $bomItem);

        $this->assertTrue($result);
    }

    /** الفصل الخامس: إنذار حاد — قطعة مش في الـ BOM */
    public function test_wrong_barcode_returns_false(): void
    {
        $this->stockItem('RM-002');
        $this->stockItem('RM-099');
        $bomItem = $this->bomItemForCode('RM-002');

        $result = $this->service->validateScan('BC-RM-099', $bomItem);

        $this->assertFalse($result);
    }

    public function test_wrong_barcode_writes_blocked_audit_entry(): void
    {
        $this->stockItem('RM-003');
        $this->stockItem('RM-099');
        $bomItem = $this->bomItemForCode('RM-003');

        $this->service->validateScan('BC-RM-099', $bomItem);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'blocked',
            'tag'    => 'warehouse',
        ]);
    }

    public function test_non_existent_barcode_returns_false(): void
    {
        $bomItem = $this->bomItemForCode('RM-003');

        $result = $this->service->validateScan('TOTALLY-FAKE-BARCODE', $bomItem);

        $this->assertFalse($result);
    }

    public function test_stock_qty_unchanged_after_wrong_scan(): void
    {
        $item    = $this->stockItem('RM-004', qty: 10);
        $this->stockItem('RM-005');
        $bomItem = $this->bomItemForCode('RM-004');

        $this->service->validateScan('BC-RM-005', $bomItem); // wrong barcode

        $item->refresh();
        $this->assertEquals(10, $item->qty, 'Stock must not change on a blocked scan');
    }
}
