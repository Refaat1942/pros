<?php

namespace Tests\Feature\Pipeline;

use App\Models\CaseRecord;
use App\Models\Quote;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/** مكتب التشغيل يطبع عرض السعر «مسودة» قبل إصداره للاستقبال — والاستقبال مايقدرش. */
class OperationsDraftQuotePrintTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_operations_can_print_pending_quote_as_draft_before_release(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->operationsReadyCase($this->civilianPatient($this->civilianCompany()));
        $this->assertSame(CaseRecord::STAGE_OPERATIONS, $case->fresh()->stage_key);
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $this->assertSame(Quote::STATUS_PENDING, $quote->status);

        $html = $this->actingAs($this->userWithRole('operations'))
            ->get('/operations/quote/'.$quote->id.'/print?embed=1')
            ->assertOk()->getContent();
        $this->assertStringContainsString('مسودة للمراجعة', $html);

        // الاستقبال مايطبعش العرض قبل ما التشغيل يصدّره.
        $this->actingAs($this->userWithRole('reception'))
            ->get('/reception/quote/'.$quote->id.'/print')
            ->assertForbidden();
    }

    public function test_adjustments_show_sends_final_price_rates(): void
    {
        $this->stockItem('RM-001', qty: 10);
        $case = $this->caseAtStage($this->cashPatient(), CaseRecord::STAGE_TECHNICAL);
        app(\App\Services\BomService::class)->createSpecRaw($case, [['stock_item_code' => $this->testOperationalCode('RM-001'), 'name' => 'x', 'qty' => 1]]);
        app(\App\Services\WorkflowService::class)->advance($case, \App\Enums\WorkflowEvent::SpecSaved->value);

        $this->actingAs($this->userWithRole('adjustments'))->getJson("/adjustments/adjustments/{$case->id}")
            ->assertOk()
            ->assertJsonPath('pricing_rates.manufacturing', 87.66)
            ->assertJsonPath('pricing_rates.quick', 40);
    }
}
