<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\Quote;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * شاشة قرار مكتب التشغيل (الخطوة 8) — موافقة / إرجاع / طباعة العرض.
 */
class OperationsPendingDeskTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_pending_list_shows_civilian_operations_cases_with_quote(): void
    {
        $this->stockItem('RM-001', qty: 20, wac: 100.00);
        app(\App\Services\StockPriceService::class)->addBatch(
            \App\Models\StockItem::first(),
            10,
            200.00,
            $this->makeSupplier(),
            'INV-001',
            now()
        );

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);

        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key);

        $ops = $this->userWithRole('operations');

        $response = $this->actingAs($ops)
            ->getJson('/operations/pending/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $case->id);

        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $response->assertJsonPath('data.0.quote.quote_no', $quote->quote_no);
        $response->assertJsonStructure(['data' => [['quote' => ['print_url']]]]);
    }

    public function test_pending_approve_reserves_stock_and_moves_to_warehouse(): void
    {
        $item = $this->stockItem('RM-001', qty: 10, wac: 100.00);
        app(\App\Services\StockPriceService::class)->addBatch(
            $item, 10, 200.00, $this->makeSupplier(), 'INV-001', now()
        );

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/approve")
            ->assertOk();

        $case->refresh();
        $item->refresh();

        $this->assertEquals(CaseRecord::STAGE_MANUFACTURING, $case->stage_key);
        $this->assertEquals(CaseRecord::MFG_WAREHOUSE, $case->manufacturing_stage);
        $this->assertGreaterThan(0, (int) $item->reserved);
    }

    public function test_pending_return_to_adjustments(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/return", [
                'target' => CaseRecord::STAGE_ADJUSTMENTS,
                'reason' => 'تعديل كميات',
            ])
            ->assertOk();

        $this->assertEquals(CaseRecord::STAGE_ADJUSTMENTS, $case->fresh()->stage_key);
    }

    public function test_pending_return_to_technical(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/return", [
                'target' => CaseRecord::STAGE_TECHNICAL,
            ])
            ->assertOk();

        $this->assertEquals(CaseRecord::STAGE_TECHNICAL, $case->fresh()->stage_key);
    }

    public function test_operations_can_print_quote(): void
    {
        $this->stockItem('RM-001', qty: 10);
        app(\App\Services\StockPriceService::class)->addBatch(
            \App\Models\StockItem::first(),
            10,
            200.00,
            $this->makeSupplier(),
            'INV-001',
            now()
        );

        $patient = $this->civilianPatient($this->civilianCompany());
        $case = $this->operationsReadyCase($patient);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->get("/operations/quote/{$quote->id}/print")
            ->assertOk()
            ->assertSee($quote->quote_no, false);
    }

    public function test_pending_page_renders_in_operations_dashboard(): void
    {
        $ops = $this->userWithRole('operations');

        $this->actingAs($ops)
            ->get('/operations/pending')
            ->assertOk()
            ->assertSee('id="pendingTable"', false)
            ->assertSee('موافقة واعتماد الصرف', false)
            ->assertSee('إرجاع للتعديل', false);
    }
}
