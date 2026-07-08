<?php

namespace Tests\Feature\Reports;

use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\ReturnNoteService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdminReturnsPageTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_returns_page_lists_return_notes_items_and_line_details(): void
    {
        $this->seedStock();

        $company = $this->civilianCompany();
        $patient = $this->civilianPatient($company);
        $ops = $this->userWithRole('operations');
        $admin = $this->userWithRole('admin');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_MANUFACTURING, CaseRecord::MFG_WAREHOUSE);
        $case->update(['work_order_no' => 'WO-2026-0900']);

        $this->actingAs($ops);
        // إصدار 3 وحدات حتى يُسمح بارتجاع وحدتين (تبقى وحدة في الورشة).
        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 3],
        ]);
        $bom->items()->update(['unit_cost' => 200]);
        app(BomService::class)->releaseToWip($bom->fresh(), ['BC-RM-001', 'BC-RM-001', 'BC-RM-001']);

        $note = app(ReturnNoteService::class)->create(
            $bom->fresh(),
            [['stock_item_code' => 'RM-001', 'qty' => 2, 'name' => 'صنف RM-001']],
            'فائض عن الحاجة',
            $ops,
        );

        $this->actingAs($this->userWithRole('technical'));
        app(ReturnNoteService::class)->complete($note, [
            ['line_id' => $note->lines->first()->id, 'barcode' => 'BC-RM-001', 'qty_returned' => 2],
        ]);

        $this->actingAs($admin)
            ->get('/admin/returns')
            ->assertOk()
            ->assertSee('سجل طلبات الارتجاع')
            ->assertSee('تفاصيل البنود — سجل كامل')
            ->assertSee($note->fresh()->return_no)
            ->assertSee('RM-001')
            ->assertSee('فائض عن الحاجة')
            ->assertDontSee('الأصناف المرتجعة — ملخص تراكمي')
            ->assertSee('exportAdminReturnLinesDetail')
            ->assertSee('__ADMIN_RETURN_NOTE_LINES', false)
            ->assertSee('"returned":2', false)
            ->assertSee('تم الاستلام');
    }

    public function test_admin_returns_page_is_read_only_without_create_actions(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/returns')
            ->assertOk()
            ->assertDontSee('إنشاء إذن')
            ->assertDontSee('btnNewReturn')
            ->assertDontSee('تأكيد الاستلام');
    }

    private function seedStock(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
