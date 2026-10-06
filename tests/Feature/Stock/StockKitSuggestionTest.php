<?php

namespace Tests\Feature\Stock;

use App\Models\Bom;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Models\Patient;
use App\Models\StockItem;
use App\Services\StockKitService;
use App\Services\StockKitSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class StockKitSuggestionTest extends TestCase
{
    use ProstheticTestHelper;
    use RefreshDatabase;

    /** @var array<string, StockItem> */
    private array $items = [];

    private ?Patient $patient = null;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['1101', '1102', '1103', '1104'] as $code) {
            $this->items[$code] = $this->stockItem($code);
        }
    }

    public function test_materials_used_together_in_more_than_one_case_are_suggested(): void
    {
        $this->caseWithBom(['1101' => 1, '1102' => 2, '1103' => 1]);
        $this->caseWithBom(['1101' => 1, '1102' => 2, '1103' => 1, '1104' => 1]);
        $this->caseWithBom(['1101' => 1, '1104' => 1]);

        $suggestions = app(StockKitSuggestionService::class)->suggestions();

        $sets = collect($suggestions)->map(fn ($s) => collect($s['items'])->pluck('code')->sort()->values()->all())->all();

        $this->assertContains(['1101', '1102', '1103'], $sets);
        $this->assertContains(['1101', '1104'], $sets);
        // الأجزاء ({1101,1102}) لا تُقترح منفصلة لأنها دائماً مع 1103 — نفس الحالات.
        $this->assertNotContains(['1101', '1102'], $sets);

        $abc = collect($suggestions)->first(fn ($s) => count($s['items']) === 3);
        $this->assertSame(2, $abc['case_count']);
        $this->assertSame(2, collect($abc['items'])->firstWhere('code', '1102')['qty']);
    }

    public function test_single_use_combinations_are_not_suggested(): void
    {
        $this->caseWithBom(['1101' => 1, '1102' => 1]);
        $this->caseWithBom(['1103' => 1, '1104' => 1]);

        $this->assertSame([], app(StockKitSuggestionService::class)->suggestions());
    }

    public function test_existing_kit_and_dismissed_suggestion_are_not_suggested_again(): void
    {
        $this->caseWithBom(['1101' => 1, '1102' => 1, '1103' => 1]);
        $this->caseWithBom(['1101' => 1, '1102' => 1, '1103' => 1, '1104' => 1]);
        $this->caseWithBom(['1101' => 1, '1104' => 1]);

        app(StockKitService::class)->create([
            'name' => 'طقم يدوي',
            'items' => collect(['1101', '1102', '1103'])->map(fn ($c) => ['stock_item_id' => $this->items[$c]->id, 'qty' => 1])->all(),
        ]);

        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('admin.stock-kits.suggestions.dismiss'), ['codes' => ['1104', '1101'], 'case_count' => 2])
            ->assertOk();

        $this->actingAs($admin)
            ->getJson(route('admin.stock-kits.suggestions'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_adjustments_desk_gets_its_own_suggestions_from_adjustment_items_only(): void
    {
        // بنود الفني (spec) متكررة لكنها لا تخص مكتب المعدلات.
        $this->caseWithBom(['1101' => 1, '1102' => 1]);
        $this->caseWithBom(['1101' => 1, '1102' => 1]);
        // بنود المعدلات: نفس التركيبة في حالتين بكمية عشرية.
        $this->caseWithBom(['1103' => 1.5, '1104' => 2], BomItem::SOURCE_ADJUSTMENT);
        $this->caseWithBom(['1103' => 1.5, '1104' => 2], BomItem::SOURCE_ADJUSTMENT);

        $operator = $this->userWithRole('adjustments');

        $response = $this->actingAs($operator)
            ->getJson(route('adjustments.item-groups.suggestions'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.case_count', 2);

        $items = collect($response->json('data.0.items'))->keyBy('code');
        $this->assertEqualsWithDelta(1.5, $items['1103']['qty'], 0.0001);
        $this->assertEqualsWithDelta(2, $items['1104']['qty'], 0.0001);

        // «نعم»: إنشاء المجموعة اليدوية من الاقتراح ← لا يُقترح ثانية.
        $this->actingAs($operator)
            ->postJson(route('adjustments.item-groups.store'), [
                'name' => $response->json('data.0.suggested_name'),
                'items' => [
                    ['stock_item_code' => '1103', 'qty' => 1.5],
                    ['stock_item_code' => '1104', 'qty' => 2],
                ],
            ])
            ->assertSuccessful();

        $this->actingAs($operator)
            ->getJson(route('adjustments.item-groups.suggestions'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_dismissal_in_one_scope_does_not_hide_the_other(): void
    {
        $this->caseWithBom(['1101' => 1, '1102' => 1], BomItem::SOURCE_ADJUSTMENT);
        $this->caseWithBom(['1101' => 1, '1102' => 1], BomItem::SOURCE_ADJUSTMENT);

        $this->actingAs($this->userWithRole('adjustments'))
            ->postJson(route('adjustments.item-groups.suggestions.dismiss'), ['codes' => ['1101', '1102']])
            ->assertOk();

        $service = app(StockKitSuggestionService::class);
        $this->assertSame([], $service->suggestions(StockKitSuggestionService::SCOPE_ADJUSTMENTS));
        $this->assertCount(1, $service->suggestions(StockKitSuggestionService::SCOPE_KITS));
    }

    /** @param  array<string, int|float>  $lines */
    private function caseWithBom(array $lines, string $source = BomItem::SOURCE_SPEC): CaseRecord
    {
        $this->patient ??= $this->cashPatient();
        $case = $this->caseAtStage($this->patient, CaseRecord::STAGE_MANUFACTURING);

        $bom = Bom::create([
            'bom_no' => 'BOM-T-'.$case->id,
            'case_id' => $case->id,
            'order_ref' => $case->order_ref,
            'patient_name' => 'مريض اختبار',
            'stage' => Bom::STAGE_RAW,
        ]);

        foreach ($lines as $code => $qty) {
            BomItem::create([
                'bom_id' => $bom->id,
                'stock_item_code' => (string) $code,
                'name' => $this->items[$code]->name,
                'source' => $source,
                'qty' => $qty,
                'unit_cost' => 0,
                'issued_qty' => 0,
                'returned_qty' => 0,
            ]);
        }

        return $case;
    }
}
