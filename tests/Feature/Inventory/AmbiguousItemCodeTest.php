<?php

namespace Tests\Feature\Inventory;

use App\Enums\WorkflowEvent;
use App\Models\CaseRecord;
use App\Models\StockItem;
use App\Services\BomService;
use App\Services\WorkflowService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * نفس الرقم («17») موجود في صنفين في أعمدة مختلفة (رقم الصنف / الأكواد / رقم الكتالوج).
 * الصنف اللي اتختار في المعدلات لازم يفضل هو نفسه: وحدته وسعره ونوعه — مش صنف تاني.
 */
class AmbiguousItemCodeTest extends TestCase
{
    use ProstheticTestHelper;

    private function item(array $attrs): StockItem
    {
        static $n = 0;
        $n++;

        return StockItem::create(array_merge([
            'code' => 'ITM-AMB-'.$n, 'barcode' => 'BC-AMB-'.$n, 'qty' => 10, 'reserved' => 0,
            'price' => 1, 'wac' => 1, 'status' => 'ok', 'uom' => 'عدد',
        ], $attrs));
    }

    private function adjustmentsCase(): CaseRecord
    {
        $case = $this->caseAtStage($this->cashPatient(), CaseRecord::STAGE_TECHNICAL);
        $other = $this->stockItem('RM-001', 20, 100.00);
        app(BomService::class)->createSpecRaw($case, [['stock_item_code' => $other->pickerCode(), 'name' => $other->name, 'qty' => 1]]);
        app(WorkflowService::class)->advance($case, WorkflowEvent::SpecSaved->value);

        return $case->fresh();
    }

    /** @return array<string, mixed> */
    private function addFromPicker(CaseRecord $case, string $search, string $name): array
    {
        $row = collect(StockItem::searchPickerRows($search))->firstWhere('name', $name);
        $this->assertNotNull($row, 'الصنف ظاهر في نافذة الاختيار');
        $adj = $this->userWithRole('adjustments');
        $items = $this->actingAs($adj)->postJson("/adjustments/adjustments/{$case->id}/items", [
            'items' => [['stock_item_code' => $row['code'], 'qty' => 1]],
        ])->assertSuccessful()->json('bom.items');

        return collect($items)->firstWhere('name', $name) ?? collect($items)->last();
    }

    public function test_internal_code_17_vs_other_items_operational_code_17(): void
    {
        // A: رقم الصنف (الكود الداخلي) = 17 — B: الأكواد = 17 (أولوية أعلى في الربط).
        $a = $this->item(['code' => '17', 'name' => 'غطاء ركبة اطراف', 'uom' => 'عدد', 'price' => 97.325]);
        $b = $this->item(['alt_codes' => '17', 'name' => 'شريط لاصق', 'uom' => 'سم2', 'price' => 0.22]);

        $line = $this->addFromPicker($this->adjustmentsCase(), 'غطاء', 'غطاء ركبة اطراف');
        $this->assertSame('عدد', $line['uom']);
        $this->assertEqualsWithDelta(97.325, $line['price'], 0.001);
        $this->assertSame($a->id, StockItem::findByOperationalCode($line['stock_item_code'])->id);

        // زرار «صرف سريع» يغيّر الصنف المختار نفسه — مش التاني.
        $case = CaseRecord::latest('id')->first();
        $this->actingAs($this->userWithRole('adjustments'))
            ->postJson("/adjustments/adjustments/{$case->id}/items/{$line['id']}/kind", ['quick' => true])->assertOk();
        $this->assertTrue((bool) $a->fresh()->is_quick_dispense);
        $this->assertFalse((bool) $b->fresh()->is_quick_dispense);
    }

    public function test_operational_code_17_vs_other_items_catalog_number_17(): void
    {
        // A: الأكواد = 17 — B (اتضاف بعده): رقم الكتالوج = 17. قبل الإصلاح كان العرض بياخد B.
        $a = $this->item(['alt_codes' => '17', 'name' => 'غطاء ركبة اطراف', 'uom' => 'عدد', 'price' => 97.325]);
        $this->item(['catalog_number' => '17', 'name' => 'شريط لاصق', 'uom' => 'سم2', 'price' => 0.22]);

        $line = $this->addFromPicker($this->adjustmentsCase(), 'غطاء', 'غطاء ركبة اطراف');
        $this->assertSame('17', $line['stock_item_code']);
        $this->assertSame('عدد', $line['uom']);
        $this->assertEqualsWithDelta(97.325, $line['price'], 0.001);
        $this->assertSame($a->id, StockItem::findByOperationalCode('17')->id);
    }

    public function test_unique_codes_keep_their_normal_code(): void
    {
        $a = $this->item(['alt_codes' => '5001', 'name' => 'عكاز']);
        $this->assertSame('5001', $a->pickerCode());
    }
}
