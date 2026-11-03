<?php

namespace Tests\Feature\Spec;

use App\Models\CaseRecord;
use App\Models\StockItem;
use App\Models\TechOrderSpec;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * التوصيف بيكتب منتجات الصرف السريع كتابة (من غير المخزن) → المعدلات تشوفها لوحدها وتحددها
 * من المخزن، وتقدر تعلّم الصنف «تصنيع/صرف سريع» فيتسجّل في الكتالوج ويتسمّع في الاعتماد.
 */
class SpecQuickDispenseWrittenTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_spec_writes_quick_items_only_and_adjustments_sees_them(): void
    {
        $case = $this->caseAtStage($this->cashPatient(), CaseRecord::STAGE_TECHNICAL);
        $spec = $this->userWithRole('spec');

        $id = $this->actingAs($spec)->postJson('/spec/spec', [
            'case_id' => $case->id, 'written_items' => '', 'items' => [],
            'quick_dispense_items' => [['name' => 'عكاز إبط ألومنيوم', 'qty' => 2], ['name' => '  ', 'qty' => 1]],
        ])->assertSuccessful()->json('id');
        $this->assertSame([['name' => 'عكاز إبط ألومنيوم', 'qty' => 2]], TechOrderSpec::find($id)->quick_dispense_items);

        $this->actingAs($spec)->postJson("/spec/spec/{$id}/submit")->assertOk();
        $this->assertSame(CaseRecord::STAGE_ADJUSTMENTS, $case->fresh()->stage_key);

        $this->actingAs($this->userWithRole('adjustments'))->getJson("/adjustments/adjustments/{$case->id}")
            ->assertOk()
            ->assertJsonPath('case.quick_dispense_items.0.name', 'عكاز إبط ألومنيوم');

        $print = $this->actingAs($spec)->get("/spec/spec/{$id}/print")->assertOk()->getContent();
        $this->assertStringContainsString('منتجات الصرف السريع', $print);
        $this->assertStringContainsString('عكاز إبط ألومنيوم', $print);
    }

    public function test_adjustments_toggles_item_kind_in_catalog(): void
    {
        $item = $this->stockItem('RM-001', 20, 100.00);
        $case = $this->caseAtStage($this->cashPatient(), CaseRecord::STAGE_TECHNICAL);
        app(\App\Services\BomService::class)->createSpecRaw($case, [['stock_item_code' => $item->pickerCode(), 'name' => $item->name, 'qty' => 1]]);
        app(\App\Services\WorkflowService::class)->advance($case, \App\Enums\WorkflowEvent::SpecSaved->value);
        $adj = $this->userWithRole('adjustments');

        $bomItems = $this->actingAs($adj)->getJson("/adjustments/adjustments/{$case->id}")->assertOk()->json('case.bom.items');
        $bomItemId = $bomItems[0]['id'];
        $this->assertFalse($bomItems[0]['is_quick_dispense']);

        $res = $this->actingAs($adj)->postJson("/adjustments/adjustments/{$case->id}/items/{$bomItemId}/kind", ['quick' => true])->assertOk();
        $this->assertTrue($res->json('bom.items.0.is_quick_dispense'));
        $this->assertTrue((bool) StockItem::find($item->id)->is_quick_dispense);

        $this->actingAs($adj)->postJson("/adjustments/adjustments/{$case->id}/items/{$bomItemId}/kind", ['quick' => false])->assertOk();
        $this->assertFalse((bool) StockItem::find($item->id)->is_quick_dispense);

        // بعد ما الحالة تخرج من المعدلات مايتغيرش.
        $case->update(['stage_key' => CaseRecord::STAGE_COST_CALC]);
        $this->actingAs($adj)->postJson("/adjustments/adjustments/{$case->id}/items/{$bomItemId}/kind", ['quick' => true])->assertStatus(422);
        $this->assertFalse((bool) StockItem::find($item->id)->is_quick_dispense);
    }
}
