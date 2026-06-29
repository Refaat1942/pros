<?php

namespace Database\Seeders;

use App\Models\StockCategory;
use App\Services\StockCategorySchemaService;
use Illuminate\Database\Seeder;

class StockCategorySeeder extends Seeder
{
    public function run(): void
    {
        $schema = app(StockCategorySchemaService::class);

        $sections = [
            'أقمشة ومواد خام' => [
                ['label' => 'وحدة القياس', 'type' => 'list', 'field_key' => 'uom', 'required' => true, 'options' => [
                    ['value' => 'كيلو', 'label' => 'كيلو'],
                    ['value' => 'متر', 'label' => 'متر'],
                    ['value' => 'لفة', 'label' => 'لفة'],
                ]],
                ['label' => 'اللون', 'type' => 'color', 'field_key' => 'color', 'required' => false],
            ],
            'مسامير وربط' => [
                ['label' => 'وحدة القياس', 'type' => 'list', 'field_key' => 'uom', 'required' => true, 'options' => [
                    ['value' => 'قطعة', 'label' => 'قطعة'],
                    ['value' => 'طقم', 'label' => 'طقم'],
                ]],
                ['label' => 'المقاس', 'type' => 'text', 'field_key' => 'size', 'required' => false],
            ],
            'مفاصل' => [],
            'أقدام' => [],
            'بطانات' => [],
            'محولات' => [],
            'إكسسوارات' => [],
        ];

        foreach ($sections as $name => $fields) {
            $category = StockCategory::query()->firstOrCreate(['name' => $name]);
            if ($fields) {
                $schema->syncFields($category, $fields);
            }
        }
    }
}
