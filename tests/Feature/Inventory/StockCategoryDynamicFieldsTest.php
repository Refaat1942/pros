<?php

namespace Tests\Feature\Inventory;

use App\Models\StockCategory;
use App\Services\StockCatalogService;
use App\Services\StockCategorySchemaService;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

class StockCategoryDynamicFieldsTest extends TestCase
{
    use ProstheticTestHelper;

    public function test_admin_can_create_category_with_dynamic_fields(): void
    {
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->postJson('/admin/stock-categories', [
            'name'   => 'أقمشة',
            'fields' => [
                [
                    'label'    => 'وحدة القياس',
                    'type'     => 'list',
                    'field_key'=> 'uom',
                    'required' => true,
                    'options'  => [
                        ['value' => 'كيلو', 'label' => 'كيلو'],
                        ['value' => 'قطعة', 'label' => 'قطعة'],
                    ],
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('name', 'أقمشة');
        $this->assertCount(1, $response->json('fields'));
    }

    public function test_catalog_item_stores_category_attributes(): void
    {
        $schema = app(StockCategorySchemaService::class);
        $category = StockCategory::create(['name' => 'مسامير']);
        $schema->syncFields($category, [
            [
                'label'    => 'وحدة القياس',
                'type'     => 'list',
                'field_key'=> 'uom',
                'required' => true,
                'options'  => [['value' => 'قطعة', 'label' => 'قطعة']],
            ],
        ]);

        $item = app(StockCatalogService::class)->create([
            'name'        => 'مسامير M6',
            'code'        => 'ITM-BOLT1',
            'qty'         => 100,
            'price'       => 5,
            'category_id' => $category->id,
            'attributes'  => ['uom' => 'قطعة'],
        ]);

        $formatted = app(StockCatalogService::class)->formatItem($item);
        $this->assertSame('مسامير', $formatted['category']);
        $this->assertSame('قطعة', $formatted['attributes_map']['uom'] ?? null);
        $this->assertSame('قطعة', $item->fresh()->uom);
    }
}
