<?php

namespace Tests\Unit;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\StockItem;
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

    private function bomItemForCode(string $operationalCode): BomItem
    {
        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);

        $bom = Bom::create([
            'bom_no' => 'BOM-0001',
            'case_id' => $case->id,
            'order_ref' => 'ORD-001',
            'patient_name' => $patient->name,
            'stage' => Bom::STAGE_RAW,
        ]);

        return BomItem::create([
            'bom_id' => $bom->id,
            'stock_item_code' => $operationalCode,
            'name' => "Bom Item {$operationalCode}",
            'qty' => 2,
            'unit_cost' => 100.00,
            'issued_qty' => 0,
            'returned_qty' => 0,
        ]);
    }

    public function test_correct_barcode_returns_true(): void
    {
        $code = '4821';
        $this->stockItem($code);
        $bomItem = $this->bomItemForCode($code);

        $result = $this->service->validateScan('BC-4821', $bomItem);

        $this->assertTrue($result);
    }

    /** الفصل الخامس: إنذار حاد — قطعة مش في الـ BOM */
    public function test_wrong_barcode_returns_false(): void
    {
        $this->stockItem('4822');
        $this->stockItem('7399');
        $bomItem = $this->bomItemForCode('4822');

        $result = $this->service->validateScan('BC-7399', $bomItem);

        $this->assertFalse($result);
    }

    public function test_wrong_barcode_writes_blocked_audit_entry(): void
    {
        $this->stockItem('4833');
        $this->stockItem('7399');
        $bomItem = $this->bomItemForCode('4833');

        $this->service->validateScan('BC-7399', $bomItem);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'blocked',
            'tag' => 'warehouse',
        ]);
    }

    public function test_non_existent_barcode_returns_false(): void
    {
        $bomItem = $this->bomItemForCode('4844');

        $result = $this->service->validateScan('TOTALLY-FAKE-BARCODE', $bomItem);

        $this->assertFalse($result);
    }

    public function test_stock_qty_unchanged_after_wrong_scan(): void
    {
        $item = $this->stockItem('4855', qty: 10);
        $this->stockItem('4866');
        $bomItem = $this->bomItemForCode('4855');

        $this->service->validateScan('BC-4866', $bomItem);

        $item->refresh();
        $this->assertEquals(10, $item->qty, 'Stock must not change on a blocked scan');
    }

    public function test_operational_code_scan_matches_without_barcode_prefix(): void
    {
        $code = '4877';
        $this->stockItem($code, qty: 5);
        $bomItem = $this->bomItemForCode($code);

        $this->assertTrue($this->service->validateScan($code, $bomItem));
        $this->assertSame($code, $this->service->resolveStockItemCode($code));
        $this->assertSame($code, $this->service->resolveStockItemCode('BC-'.$code));
    }

    public function test_catalog_code_column_does_not_match_as_operational_code(): void
    {
        StockItem::create([
            'code' => 'RM-PRIMARY',
            'name' => 'صنف اختبار',
            'store_class' => 'A',
            'uom' => 'piece',
            'alt_codes' => '4888',
            'barcode' => 'BC-4888',
            'qty' => 10,
            'reserved' => 0,
            'wac' => 50,
            'status' => 'ok',
        ]);

        $bomItem = $this->bomItemForCode('4888');

        $this->assertFalse($this->service->validateScan('RM-PRIMARY', $bomItem));
        $this->assertNull($this->service->resolveStockItemCode('RM-PRIMARY'));
    }

    public function test_alt_codes_is_the_operational_identifier(): void
    {
        $item = StockItem::create([
            'code' => 'RM-PRIMARY',
            'name' => 'صنف اختبار',
            'store_class' => 'A',
            'uom' => 'piece',
            'alt_codes' => '4999',
            'barcode' => 'BC-4999',
            'qty' => 10,
            'reserved' => 0,
            'wac' => 50,
            'status' => 'ok',
        ]);

        $bomItem = $this->bomItemForCode('4999');

        $this->assertTrue($this->service->validateScan('4999', $bomItem));
        $this->assertTrue($this->service->validateScan('BC-4999', $bomItem));
        $this->assertSame('4999', $this->service->resolveStockItemCode('4999'));
    }
}
