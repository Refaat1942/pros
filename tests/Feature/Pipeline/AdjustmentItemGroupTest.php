<?php

namespace Tests\Feature\Pipeline;

use App\Models\AdjustmentItemGroup;
use App\Models\BomItem;
use App\Models\CaseRecord;
use App\Services\BomService;
use App\Services\StockPriceService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class AdjustmentItemGroupTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_adjustments_user_can_crud_saved_item_groups(): void
    {
        $this->seedStockWithPriceBatch();
        $user = $this->userWithRole('adjustments');

        $this->actingAs($user)
            ->postJson('/adjustments/item-groups', [
                'name' => 'ركبة — ثوابت',
                'notes' => 'للأطراف السفلية',
                'items' => [
                    ['stock_item_code' => 'RM-001', 'qty' => 2],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('group.name', 'ركبة — ثوابت');

        $groupId = AdjustmentItemGroup::query()->value('id');
        $this->assertNotNull($groupId);

        $this->actingAs($user)
            ->getJson('/adjustments/item-groups')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.stock_item_code', 'RM-001');

        $this->actingAs($user)
            ->putJson('/adjustments/item-groups/'.$groupId, [
                'name' => 'ركبة محدّثة',
                'items' => [
                    ['stock_item_code' => 'RM-001', 'qty' => 3],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('group.name', 'ركبة محدّثة');

        $this->actingAs($user)
            ->deleteJson('/adjustments/item-groups/'.$groupId)
            ->assertOk();

        $this->assertDatabaseMissing('adjustment_item_groups', ['id' => $groupId]);
    }

    public function test_applying_group_lines_via_add_items_sets_group_label(): void
    {
        $this->seedStockWithPriceBatch();
        $this->stockItem('RM-002', qty: 10);

        $patient = $this->civilianPatient($this->civilianCompany());
        $user = $this->userWithRole('adjustments');
        $case = $this->caseAtStage($patient, CaseRecord::STAGE_ADJUSTMENTS);

        $bom = app(BomService::class)->createSpecRaw($case, [
            ['stock_item_code' => 'RM-001', 'qty' => 1],
        ]);

        $this->actingAs($user)
            ->postJson("/adjustments/adjustments/{$case->id}/items", [
                'items' => [
                    [
                        'stock_item_code' => 'RM-002',
                        'name' => 'مكوّن',
                        'qty' => 1,
                        'group_label' => 'ثوابت الركبة',
                    ],
                ],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('bom_items', [
            'bom_id' => $bom->id,
            'stock_item_code' => 'RM-002',
            'source' => BomItem::SOURCE_ADJUSTMENT,
            'group_label' => 'ثوابت الركبة',
        ]);
    }

    private function seedStockWithPriceBatch(): void
    {
        $item = $this->stockItem('RM-001', qty: 20);
        $supplier = $this->makeSupplier();
        app(StockPriceService::class)->addBatch($item, 20, 200.00, $supplier, 'INV-001', now());
    }
}
