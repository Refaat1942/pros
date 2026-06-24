<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\Quote;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * تأكيد مسار عرض السعر:
 *   التكاليف (تأكيد) → مكتب التشغيل (طابور pending) → إصدار للاستقبال → قسم عروض الأسعار.
 */
class QuoteVisibilityPipelineTest extends TestCase
{
    use ProstheticTestHelper;

    private function seedStock(): void
    {
        $item = $this->stockItem('RM-001', qty: 20, wac: 100.00);
        app(\App\Services\StockPriceService::class)->addBatch(
            $item, 10, 200.00, $this->makeSupplier(), 'INV-001', now()
        );
    }

    private function caseWaitingAtCosting(): CaseRecord
    {
        $this->seedStock();
        $patient = $this->civilianPatient($this->civilianCompany());
        $case    = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        app(\App\Services\BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 2],
        ]);

        $adjustments = $this->userWithRole('adjustments');
        $this->actingAs($adjustments)
            ->postJson("/adjustments/adjustments/{$case->id}/complete")
            ->assertOk();

        $case->refresh();
        $this->assertEquals(CaseRecord::STAGE_COST_CALC, $case->stage_key);

        return $case;
    }

    public function test_full_quote_visibility_chain_costing_to_operations_to_reception(): void
    {
        $case    = $this->caseWaitingAtCosting();
        $costing = $this->userWithRole('costing');
        $ops     = $this->userWithRole('operations');
        $recep   = $this->userWithRole('reception');

        // ── قبل تأكيد التكاليف: لا عرض، لا استقبال، لا تشغيل ───────────────
        $this->assertDatabaseMissing('quotes', ['case_id' => $case->id]);

        $this->actingAs($recep)
            ->getJson('/reception/quote/list')
            ->assertJsonPath('total', 0);

        $this->actingAs($ops)
            ->getJson('/operations/pending/list')
            ->assertJsonPath('total', 0);

        // ── التكاليف: تأكيد وإصدار → عرض داخلي + طابور التشغيل فقط ────────
        $this->actingAs($costing)
            ->postJson("/costing/queue/{$case->id}/confirm")
            ->assertOk()
            ->assertJsonPath('case.stage_key', CaseRecord::STAGE_OPERATIONS);

        $case->refresh();
        $quote = Quote::where('case_id', $case->id)->firstOrFail();

        $this->assertEquals(CaseRecord::STAGE_OPERATIONS, $case->stage_key);
        $this->assertEquals(Quote::STATUS_PENDING, $quote->status);

        $this->actingAs($recep)
            ->getJson('/reception/quote/list')
            ->assertJsonPath('total', 0);

        $opsPending = $this->actingAs($ops)
            ->getJson('/operations/pending/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $case->id)
            ->assertJsonPath('data.0.quote.quote_no', $quote->quote_no)
            ->assertJsonPath('data.0.quote.status', Quote::STATUS_PENDING);

        // ── مكتب التشغيل: إصدار عرض سعر → يظهر في الاستقبال ───────────────
        $this->actingAs($ops)
            ->postJson("/operations/pending/{$case->id}/release-quote")
            ->assertOk()
            ->assertJsonPath('quote.status', Quote::STATUS_ISSUED);

        $quote->refresh();
        $this->assertEquals(Quote::STATUS_ISSUED, $quote->status);

        $this->actingAs($recep)
            ->getJson('/reception/quote/list')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.quote_no', $quote->quote_no)
            ->assertJsonPath('data.0.patient_name', $quote->patient_name)
            ->assertJsonStructure(['data' => [['print_url']]]);

        $preview = $this->actingAs($recep)
            ->get('/reception/quote/' . $quote->id . '/print?embed=1');
        $preview->assertOk()
            ->assertSee('عرض سعر', false)
            ->assertSee('وزارة الدفاع', false)
            ->assertSee($quote->quote_no, false)
            ->assertSee('<svg', false)
            ->assertSee('embed-preview', false);

        // بعد الإصدار + اعتماد الصرف: الحالة تخرج من طابور التشغيل
        $this->actingAs($ops)
            ->getJson('/operations/pending/list')
            ->assertJsonPath('total', 0);
    }
}
