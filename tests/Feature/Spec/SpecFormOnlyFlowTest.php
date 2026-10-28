<?php

namespace Tests\Feature\Spec;

use App\Models\CaseRecord;
use App\Models\Quote;
use App\Models\TechOrderSpec;
use App\Services\CostingService;
use App\Services\WorkflowPolicyService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * التوصيف بالاستمارة فقط (زي الإكسل، من غير أصناف مخزن) → المعدلات تضيف الأصناف المسعّرة
 * (لا تُتخطّى ولا تُقفل من غيرها) → عرض السعر بعمود «البيان / المكونات».
 */
class SpecFormOnlyFlowTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_spec_form_only_goes_to_adjustments_which_must_add_priced_items(): void
    {
        $item = $this->stockItem('RM-001', 20, 100.00);
        $item->update(['price' => 1200]);
        $case = $this->caseAtStage($this->cashPatient(), CaseRecord::STAGE_TECHNICAL);
        $spec = $this->userWithRole('spec');

        $form = "السوكيت الخارجي: بلاستيك\nنوع الركبة: حرة اير\nنوع القدم: ساش اير يمني\nمقاس القدم: 27";

        // الشاشة الجديدة بتبعت items فاضية + الاستمارة.
        $id = $this->actingAs($spec)->postJson('/spec/spec', [
            'case_id' => $case->id, 'written_items' => $form, 'items' => [],
        ])->assertSuccessful()->json('id');
        $this->actingAs($spec)->postJson("/spec/spec/{$id}/submit")->assertOk();

        $case = $case->fresh();
        $this->assertSame(CaseRecord::STAGE_ADJUSTMENTS, $case->stage_key);
        $this->assertTrue(app(WorkflowPolicyService::class)->adjustmentsMustAddItems($case));
        $this->assertFalse(app(WorkflowPolicyService::class)->shouldAutoSkip($case), 'لا تُتخطّى المعدلات من غير أصناف');
        $this->assertFalse(app(WorkflowPolicyService::class)->canManualSkip($case, CaseRecord::STAGE_ADJUSTMENTS, $this->userWithRole('admin')));

        $adj = $this->userWithRole('adjustments');
        $this->actingAs($adj)->postJson("/adjustments/adjustments/{$case->id}/complete")->assertStatus(422);
        $this->assertSame(CaseRecord::STAGE_ADJUSTMENTS, $case->fresh()->stage_key);

        $this->actingAs($adj)->postJson("/adjustments/adjustments/{$case->id}/items", [
            'items' => [['stock_item_code' => $item->pickerCode(), 'qty' => 1]],
        ])->assertSuccessful();
        $this->actingAs($adj)->postJson("/adjustments/adjustments/{$case->id}/complete")->assertOk();
        $this->assertSame(CaseRecord::STAGE_COST_CALC, $case->fresh()->stage_key);

        app(CostingService::class)->confirmAndIssueQuote($case->fresh());
        $quote = Quote::where('case_id', $case->id)->firstOrFail();
        $this->assertGreaterThan(0, (float) $quote->total);

        $html = $this->actingAs($this->userWithRole('admin'))->get("/admin/cases/{$case->id}/quote?embed=1")->assertOk()->getContent();
        $this->assertStringContainsString('<th>البيان / المكونات</th>', $html);
        $this->assertMatchesRegularExpression('/<td class="label">نوع الركبة<\/td>\s*<td class="value">حرة اير<\/td>/u', $html);

        // طباعة التوصيف: الاستمارة جدول، ومن غير جدول أصناف فاضي.
        $print = $this->actingAs($spec)->get('/spec/spec/'.TechOrderSpec::where('case_id', $case->id)->value('id').'/print')->assertOk()->getContent();
        $this->assertStringContainsString('السوكيت الخارجي', $print);
        $this->assertStringNotContainsString('<th style="width:18%;">كود الصنف</th>', $print);
    }

    public function test_empty_spec_is_still_refused(): void
    {
        $case = $this->caseAtStage($this->cashPatient(), CaseRecord::STAGE_TECHNICAL);
        $this->actingAs($this->userWithRole('spec'))->postJson('/spec/spec', [
            'case_id' => $case->id, 'written_items' => '', 'items' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('written_items');
    }

    public function test_spec_screen_shows_the_excel_form_and_hides_stock_items(): void
    {
        $html = $this->actingAs($this->userWithRole('spec'))->get('/spec/orders')->assertOk()->getContent();
        $this->assertStringContainsString('id="specFormSheet"', $html);
        $this->assertStringContainsString('data-spec-field="السوكيت الخارجي"', $html);
        $this->assertStringContainsString('data-spec-field="الأطواق"', $html);
        $this->assertMatchesRegularExpression('/<div id="specCatalogItemsBlock" hidden>/u', $html);
    }
}
